<?php

namespace Tests\Feature;

use App\Mail\OrderMail;
use App\Models\Item;
use App\Models\Itemsize;
use App\Services\InventoryMaintenanceService;
use App\Services\OrderService;
use App\Services\StatisticService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Mockery;
use Tests\TestCase;

class InternalProcessCommandsTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    // Maintenance runs VACUUM, which cannot run inside a test transaction.
    config([
      'database.default' => 'sqlite',
      'database.connections.sqlite.database' => ':memory:',
      'database.connections.sqlite.url' => null,
      'cache.default' => 'array',
      'session.driver' => 'array',
      'mail.default' => 'array',
      'mail.order_mailer_to_address' => 'orders@example.test',
      'mail.order_mailer_cc_addresses' => [],
    ]);
    DB::purge('sqlite');
    Artisan::call('migrate', ['--force' => true]);
    Carbon::setTestNow('2026-10-02 12:00:00');
    CarbonImmutable::setTestNow('2026-10-02 12:00:00');
  }

  protected function tearDown(): void
  {
    Carbon::setTestNow();
    CarbonImmutable::setTestNow();
    parent::tearDown();
  }

  public function test_commands_are_discovered_and_http_triggers_are_removed(): void
  {
    $commands = $this->app->make(Kernel::class)->all();
    $this->assertArrayHasKey('inventory:jobs', $commands);
    $this->assertArrayHasKey('order:create', $commands);

    $this->mock(InventoryMaintenanceService::class)->shouldNotReceive('run');
    $this->mock(OrderService::class)->shouldNotReceive('create');

    foreach (['/api/inventory-jobs', '/api/order'] as $uri) {
      $this->getJson($uri)->assertNotFound();
      $this->head($uri)->assertNotFound();
      $this->postJson($uri)->assertNotFound();
    }
  }

  public function test_maintenance_preserves_recent_data_and_open_orders_and_aggregates(): void
  {
    $item = $this->createItem('Maintenance');
    $oldClosed = $this->createOrder($item, '2026-03-01', false);
    $oldOpen = $this->createOrder($item, '2026-03-02', true);
    $recentClosed = $this->createOrder($item, '2026-09-01', false);
    $oldBooking = $this->createBooking($item, '2026-04-02 11:59:59', $oldClosed);
    $boundaryBooking = $this->createBooking($item, '2026-04-02 12:00:00');
    $weeklyBooking = $this->createBooking($item, '2026-09-23 10:00:00');
    $oldStat = DB::table('itemstats')->insertGetId([
      'item_id' => $item->id, 'week_start' => '2026-03-23',
      'aggregated_at' => '2026-04-02 11:59:59',
    ]);
    $boundaryStat = DB::table('itemstats')->insertGetId([
      'item_id' => $item->id, 'week_start' => '2026-03-30',
      'aggregated_at' => '2026-04-02 12:00:00',
    ]);

    $vacuumTransactions = [];
    DB::listen(function (QueryExecuted $query) use (&$vacuumTransactions) {
      if ($query->sql === 'VACUUM') {
        $vacuumTransactions[] = $query->connection->transactionLevel();
      }
    });

    [$status, $result] = $this->runCommand('inventory:jobs');

    $this->assertSame(0, $status);
    $this->assertTrue($result['ok']);
    $this->assertSame([
      'orders_deleted' => 1, 'bookings_deleted' => 1, 'stats_deleted' => 1,
    ], $result['counts']);
    $this->assertSame([0], $vacuumTransactions);
    $this->assertDatabaseMissing('orders', ['id' => $oldClosed]);
    $this->assertDatabaseHas('orders', ['id' => $oldOpen]);
    $this->assertDatabaseHas('orders', ['id' => $recentClosed]);
    $this->assertDatabaseMissing('bookings', ['id' => $oldBooking]);
    $this->assertDatabaseHas('bookings', ['id' => $boundaryBooking]);
    $this->assertDatabaseHas('bookings', ['id' => $weeklyBooking]);
    $this->assertDatabaseMissing('itemstats', ['id' => $oldStat]);
    $this->assertDatabaseHas('itemstats', ['id' => $boundaryStat]);
    $this->assertDatabaseHas('itemstats', [
      'item_id' => $item->id, 'week_start' => '2026-09-21',
      'consumption_total' => 3, 'booking_count' => 1,
    ]);
  }

  public function test_maintenance_failure_is_logged_and_returns_a_failure_exit_code(): void
  {
    Log::spy();
    $this->mock(StatisticService::class)->shouldReceive('runWeeklyAggregation')
      ->once()->andThrow(new \RuntimeException('Aggregation failed <error>detail</error>'));

    [$status, $result] = $this->runCommand('inventory:jobs');

    $this->assertSame(1, $status);
    $this->assertFalse($result['ok']);
    $this->assertSame('UNEXPECTED_ERROR', $result['error_code']);
    $this->assertSame('Aggregation failed <error>detail</error>', $result['message']);
    $this->assertFailureLogged('Inventory.Jobs command failed.', $result);
  }

  public function test_order_without_restock_is_successful_and_sends_no_mail(): void
  {
    $this->createItem('Suspended', ['dont_order' => true]);
    $this->createItem('Stocked', ['current_quantity' => 20]);
    Mail::fake();
    Pdf::shouldReceive('loadView')->never();

    [$status, $result] = $this->runCommand('order:create');

    $this->assertSame(0, $status);
    $this->assertTrue($result['ok']);
    $this->assertSame('no items need restock.', $result['message']);
    $this->assertSame(['orders_opened' => 0, 'bookings_affected' => 0], $result['counts']);
    $this->assertDatabaseCount('orders', 0);
    Mail::assertNothingSent();
  }

  public function test_order_sends_pdf_mail_and_associates_only_eligible_bookings(): void
  {
    $item = $this->createItem('Orderable', ['max_order_quantity' => 2], 3);
    $suspended = $this->createItem('Suspended', ['dont_order' => true]);
    $covered = $this->createItem('Covered');
    $this->createOrder($covered, '2026-10-01', true, 10);
    $existingOrder = $this->createOrder($item, '2026-09-01', false);
    $eligible = $this->createBooking($item, '2026-10-02 12:00:00');
    $future = $this->createBooking($item, '2026-10-02 12:00:01');
    $associated = $this->createBooking($item, '2026-09-01 12:00:00', $existingOrder);
    $suspendedBooking = $this->createBooking($suspended, '2026-10-01 12:00:00');
    config(['mail.order_mailer_cc_addresses' => ['copy@example.test']]);
    Mail::fake();
    $this->mockPdf('%PDF-test');

    [$status, $result] = $this->runCommand('order:create');

    $this->assertSame(0, $status);
    $this->assertTrue($result['ok']);
    $this->assertSame(['orders_opened' => 1, 'bookings_affected' => 1], $result['counts']);
    $order = DB::table('orders')->where('item_id', $item->id)->where('is_order_open', true)->first();
    $this->assertNotNull($order);
    $this->assertSame(6, $order->amount_desired);
    $this->assertDatabaseHas('bookings', ['id' => $eligible, 'order_id' => $order->id]);
    $this->assertDatabaseHas('bookings', ['id' => $future, 'order_id' => null]);
    $this->assertDatabaseHas('bookings', ['id' => $associated, 'order_id' => $existingOrder]);
    $this->assertDatabaseHas('bookings', ['id' => $suspendedBooking, 'order_id' => null]);
    $this->assertDatabaseMissing('orders', ['item_id' => $suspended->id]);
    $this->assertDatabaseCount('orders', 3);
    Mail::assertSent(OrderMail::class, function (OrderMail $mail) {
      return $mail->hasTo('orders@example.test')
        && $mail->hasCc('copy@example.test')
        && $mail->attachmentsData === [[
          'filename' => 'orderable-demand_2026-10-02.pdf',
          'data' => '%PDF-test', 'mime' => 'application/pdf',
        ]];
    });
    Mail::assertSentCount(1);
  }

  public function test_pdf_failure_does_not_send_mail_or_change_orders_and_bookings(): void
  {
    $item = $this->createItem('Invalid PDF');
    $booking = $this->createBooking($item, '2026-10-01 12:00:00');
    Mail::fake();
    Log::spy();
    $this->mockPdf('');

    [$status, $result] = $this->runCommand('order:create');

    $this->assertSame(1, $status);
    $this->assertFalse($result['ok']);
    $this->assertSame('PDF_FAILED', $result['error_code']);
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseHas('bookings', ['id' => $booking, 'order_id' => null]);
    Mail::assertNothingSent();
    $this->assertFailureLogged('Order.Create command failed.', $result);
  }

  public function test_mail_failure_does_not_change_orders_or_bookings(): void
  {
    $item = $this->createItem('Mail failure');
    $booking = $this->createBooking($item, '2026-10-01 12:00:00');
    Log::spy();
    $this->mockPdf('%PDF-test');
    Mail::shouldReceive('to')->once()->with('orders@example.test')->andReturnSelf();
    Mail::shouldReceive('send')->once()->with(Mockery::type(OrderMail::class))
      ->andThrow(new \RuntimeException('SMTP unavailable'));

    [$status, $result] = $this->runCommand('order:create');

    $this->assertSame(1, $status);
    $this->assertFalse($result['ok']);
    $this->assertSame('MAIL_FAILED', $result['error_code']);
    $this->assertDatabaseCount('orders', 0);
    $this->assertDatabaseHas('bookings', ['id' => $booking, 'order_id' => null]);
    $this->assertFailureLogged('Order.Create command failed.', $result);
  }

  private function runCommand(string $command): array
  {
    $status = Artisan::call($command, ['--no-interaction' => true]);
    $output = trim(Artisan::output());
    $this->assertCount(1, explode("\n", $output));
    $result = json_decode($output, true, flags: JSON_THROW_ON_ERROR);
    $this->assertTrue(Str::isUuid($result['run_id']));

    return [$status, $result];
  }

  private function assertFailureLogged(string $message, array $result): void
  {
    Log::shouldHaveReceived('error')->once()->with($message, Mockery::on(
      fn (array $context) => $context['run_id'] === $result['run_id']
        && $context['exception'] instanceof \Throwable
    ));
  }

  private function mockPdf(string $output): void
  {
    $pdf = Mockery::mock(\Barryvdh\DomPDF\PDF::class);
    $pdf->shouldReceive('setPaper')->once()->with('a4')->andReturnSelf();
    $pdf->shouldReceive('output')->once()->andReturn($output);
    Pdf::shouldReceive('loadView')->once()->with('pdf.demand', Mockery::type('array'))->andReturn($pdf);
  }

  private function createItem(string $name, array $attributes = [], int $orderSize = 1): Item
  {
    $demand = DB::table('demands')->insertGetId(['name' => "{$name} demand"]);
    $item = Item::create(array_merge([
      'name' => $name, 'demand_id' => $demand, 'location' => [],
      'min_stock' => 5, 'max_stock' => 10, 'current_quantity' => 0,
      'dont_order' => false,
    ], $attributes));
    Itemsize::create([
      'item_id' => $item->id, 'unit' => 'Pack', 'amount' => $orderSize, 'is_default' => true,
    ]);
    if ($orderSize !== 1) {
      Itemsize::create([
        'item_id' => $item->id, 'unit' => 'Stk.', 'amount' => 1, 'is_default' => false,
      ]);
    }

    return $item;
  }

  private function createOrder(Item $item, string $date, bool $open, int $amount = 3): int
  {
    return DB::table('orders')->insertGetId([
      'item_id' => $item->id, 'order_date' => $date,
      'amount_desired' => $amount, 'amount_delivered' => 0, 'is_order_open' => $open,
    ]);
  }

  private function createBooking(Item $item, string $date, ?int $order = null): int
  {
    return DB::table('bookings')->insertGetId([
      'item_id' => $item->id, 'usage_id' => 1, 'item_amount' => 3,
      'order_id' => $order, 'created_at' => $date, 'updated_at' => $date,
    ]);
  }
}
