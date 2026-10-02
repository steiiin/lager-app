<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Order;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\InputStream;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class BookInConcurrencyTest extends TestCase
{
  public function test_overlapping_batches_book_each_order_once_and_preserve_the_first_quantities(): void
  {
    // Separate workers need committed fixtures in a shared file, not RefreshDatabase's transaction.
    $database = tempnam(sys_get_temp_dir(), 'bookin-concurrency-');
    $first = $second = null;

    try {
      config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => $database,
        'database.connections.sqlite.url' => null,
      ]);
      DB::purge('sqlite');
      Artisan::call('migrate', ['--force' => true]);

      $demandId = DB::table('demands')->insertGetId(['name' => 'Concurrency']);
      $item = Item::create([
        'name' => 'Concurrent delivery', 'demand_id' => $demandId,
        'location' => [], 'min_stock' => 0, 'max_stock' => 20, 'current_quantity' => 0,
      ]);
      $orders = [];
      foreach (['2026-07-10', '2026-07-11'] as $date) {
        $orders[] = Order::create([
          'item_id' => $item->id, 'order_date' => $date,
          'amount_desired' => 8, 'amount_delivered' => 0, 'is_order_open' => true,
        ]);
      }

      $input = new InputStream();
      $first = $this->worker($database, 'hold', [
        ['id' => $orders[1]->id, 'amount_delivered' => 3],
        ['id' => $orders[0]->id, 'amount_delivered' => 8],
      ]);
      $first->setInput($input);
      $first->start();
      $this->assertTrue($first->waitUntil(fn ($type, $output) => str_contains($output, 'CLAIMED')),
        $first->getOutput().$first->getErrorOutput());

      $second = $this->worker($database, 'compete', [
        ['id' => $orders[0]->id, 'amount_delivered' => 99],
        ['id' => $orders[1]->id, 'amount_delivered' => 99],
      ]);
      $second->start();
      $this->assertTrue($second->waitUntil(fn ($type, $output) => str_contains($output, 'ATTEMPTING')),
        $second->getOutput().$second->getErrorOutput());

      // Request B has passed validation and reached its claim while A still owns the write lock.
      $input->write("release\n");
      $input->close();
      $this->assertSame(0, $first->wait(), $first->getErrorOutput());
      $this->assertSame(0, $second->wait(), $second->getErrorOutput());
      $this->assertStringContainsString('SUCCESS', $first->getOutput());
      $this->assertStringContainsString('SUCCESS', $second->getOutput());

      $this->assertSame(11, $item->fresh()->current_quantity);
      $this->assertSame(0, Order::open()->count());
      $this->assertSame(8, $orders[0]->fresh()->amount_delivered);
      $this->assertSame(3, $orders[1]->fresh()->amount_delivered);
      $this->assertDatabaseCount('bookings', 2);
      foreach ([$orders[0]->id => 8, $orders[1]->id => 3] as $id => $amount) {
        $this->assertDatabaseHas('bookings', ['order_id' => $id, 'usage_id' => -4, 'item_amount' => $amount]);
      }
    } finally {
      $first?->stop(0);
      $second?->stop(0);
      DB::purge('sqlite');
      foreach ([$database, $database.'-journal', $database.'-wal', $database.'-shm'] as $file) {
        if (is_file($file)) {
          unlink($file);
        }
      }
    }
  }

  private function worker(string $database, string $mode, array $orders): Process
  {
    return new Process([
      PHP_BINARY, base_path('tests/Support/bookin-worker.php'), $database, $mode,
      json_encode(['orders' => $orders], JSON_THROW_ON_ERROR),
    ], base_path(), [
      'APP_ENV' => 'testing',
      'APP_KEY' => 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
      'DB_CONNECTION' => 'sqlite', 'DB_DATABASE' => $database, 'DB_URL' => '',
      'SESSION_DRIVER' => 'array', 'CACHE_STORE' => 'array', 'LOG_CHANNEL' => 'stderr',
    ], timeout: 15);
  }
}
