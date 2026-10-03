<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InventoryHistoryTest extends TestCase
{
  use RefreshDatabase;

  private int $fixtureSequence = 0;

  public function test_history_combines_item_events_and_maps_their_values(): void
  {
    $item = $this->createItem();
    $otherItem = $this->createItem();
    $usage = Usage::create(['name' => 'RTW 1']);

    $this->createBooking($item, $usage->id, 3, '2026-07-10 09:30:00');
    Order::create([
      'item_id' => $item->id,
      'order_date' => '2026-07-11',
      'amount_desired' => 7,
      'amount_delivered' => 5,
      'is_order_open' => false,
    ]);
    Order::create([
      'item_id' => $item->id,
      'order_date' => '2026-07-09',
      'amount_desired' => 4,
      'amount_delivered' => 0,
      'is_order_open' => true,
    ]);
    $this->createBooking($item, -4, 5, '2026-07-12 10:45:00');
    $this->createBooking($otherItem, $usage->id, 99, '2026-07-13 12:00:00');

    $response = $this->getJson("/api/inventory/{$item->id}/history");

    $response
      ->assertOk()
      ->assertJsonCount(4, 'data')
      ->assertJsonPath('per_page', 50)
      ->assertJsonPath('total', 4)
      ->assertJsonPath('data.0.type', 'bookin')
      ->assertJsonPath('data.0.amount', 5)
      ->assertJsonPath('data.0.occurred_at_precision', 'datetime')
      ->assertJsonPath('data.0.context', null)
      ->assertJsonPath('data.1.type', 'order')
      ->assertJsonPath('data.1.amount', 7)
      ->assertJsonPath('data.1.occurred_at', '2026-07-11')
      ->assertJsonPath('data.1.occurred_at_precision', 'date')
      ->assertJsonPath('data.2.type', 'bookout')
      ->assertJsonPath('data.2.amount', 3)
      ->assertJsonPath('data.2.context.kind', 'usage')
      ->assertJsonPath('data.2.context.label', 'RTW 1')
      ->assertJsonPath('data.3.type', 'order')
      ->assertJsonPath('data.3.amount', 4);

    $this->assertStringStartsWith('booking:', $response->json('data.0.key'));
    $this->assertStringStartsWith('order:', $response->json('data.1.key'));
    $this->assertStringStartsWith('2026-07-12T10:45:00', $response->json('data.0.occurred_at'));
  }

  public function test_history_maps_internal_processes_signed_amounts_and_fallbacks(): void
  {
    $item = $this->createItem();

    $this->createBooking($item, -1, -4, '2026-07-10 10:00:01');
    $this->createBooking($item, -2, 2, '2026-07-10 10:00:02');
    $this->createBooking($item, -3, 3, '2026-07-10 10:00:03');
    $this->createBooking($item, -99, 4, '2026-07-10 10:00:04');
    $this->createBooking($item, 999, 5, '2026-07-10 10:00:05');

    $events = collect($this->getJson("/api/inventory/{$item->id}/history")
      ->assertOk()
      ->json('data'));

    foreach (['Abweichung' => -4, 'Rückbuchung' => 2, 'Verfall' => 3] as $label => $amount) {
      $event = $events->firstWhere('context.label', $label);
      $this->assertSame('check', $event['type']);
      $this->assertSame('internal', $event['context']['kind']);
      $this->assertSame($amount, $event['amount']);
      $this->assertSame('datetime', $event['occurred_at_precision']);
    }
    $this->assertSame('bookout', $events->firstWhere('context.label', 'Interner Vorgang')['type']);
    $this->assertSame('bookout', $events->firstWhere('context.label', 'Unbekannte Verwendung')['type']);
    $this->assertSame('usage', $events->firstWhere('context.label', 'Unbekannte Verwendung')['context']['kind']);
  }

  public function test_history_includes_the_latest_check_without_creating_a_booking_or_changing_stock(): void
  {
    $item = $this->createItem();
    $item->update(['checked_at' => '2026-07-10']);

    $this->getJson("/api/inventory/{$item->id}/history")
      ->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonPath('total', 1)
      ->assertJsonPath('data.0', [
        'key' => "check:{$item->id}",
        'type' => 'check',
        'amount' => 0,
        'occurred_at' => '2026-07-10',
        'occurred_at_precision' => 'date',
        'context' => null,
      ]);

    $item->update(['checked_at' => '2026-07-11']);

    $this->getJson("/api/inventory/{$item->id}/history")
      ->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonPath('data.0.key', "check:{$item->id}")
      ->assertJsonPath('data.0.occurred_at', '2026-07-11');

    $this->assertDatabaseCount('bookings', 0);
    $this->assertSame(5, $item->fresh()->current_quantity);
  }

  #[DataProvider('adminUsageProvider')]
  public function test_history_suppresses_the_synthetic_check_for_same_day_admin_bookings(int $usageId): void
  {
    $item = $this->createItem();
    $item->update(['checked_at' => '2026-07-10']);
    $this->createBooking($item, $usageId, -2, '2026-07-10 23:59:59');

    $response = $this->getJson("/api/inventory/{$item->id}/history")
      ->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonPath('total', 1)
      ->assertJsonPath('data.0.type', 'check')
      ->assertJsonPath('data.0.amount', -2);

    $this->assertStringStartsWith('booking:', $response->json('data.0.key'));
  }

  public static function adminUsageProvider(): array
  {
    return [[-1], [-2], [-3]];
  }

  public function test_only_same_item_admin_bookings_on_the_checked_date_suppress_the_synthetic_check(): void
  {
    $item = $this->createItem();
    $otherItem = $this->createItem();
    $item->update(['checked_at' => '2026-07-10']);
    $usage = Usage::create(['name' => 'RTW 1']);

    $this->createBooking($item, $usage->id, 1, '2026-07-10 09:00:00');
    $this->createBooking($item, -4, 2, '2026-07-10 10:00:00');
    $this->createBooking($item, -99, 3, '2026-07-10 11:00:00');
    $this->createBooking($item, -1, -1, '2026-07-09 23:59:59');
    $this->createBooking($item, -2, 1, '2026-07-11 00:00:00');
    $this->createBooking($otherItem, -3, -1, '2026-07-10 12:00:00');

    $events = collect($this->getJson("/api/inventory/{$item->id}/history")
      ->assertOk()
      ->assertJsonCount(6, 'data')
      ->assertJsonPath('total', 6)
      ->json('data'));

    $this->assertSame('2026-07-10', $events->firstWhere('key', "check:{$item->id}")['occurred_at']);
    $this->assertSame(1, $events->where('key', "check:{$item->id}")->count());
  }

  public function test_synthetic_checks_are_sorted_and_counted_before_pagination(): void
  {
    $item = $this->createItem();
    $item->update(['checked_at' => '2026-07-10']);

    for ($index = 1; $index <= 49; $index++) {
      $this->createBooking($item, -4, 1, '2026-07-11 10:00:00');
    }
    $this->createBooking($item, -1, -1, '2026-07-09 10:00:00');
    $order = Order::create([
      'item_id' => $item->id,
      'order_date' => '2026-07-10',
      'amount_desired' => 1,
      'amount_delivered' => 0,
      'is_order_open' => true,
    ]);

    $firstPage = $this->getJson("/api/inventory/{$item->id}/history?page=1")
      ->assertOk()
      ->assertJsonCount(50, 'data')
      ->assertJsonPath('total', 52)
      ->assertJsonPath('last_page', 2)
      ->assertJsonPath('data.49.key', "check:{$item->id}");

    $secondPage = $this->getJson("/api/inventory/{$item->id}/history?page=2")
      ->assertOk()
      ->assertJsonCount(2, 'data')
      ->assertJsonPath('total', 52)
      ->assertJsonPath('data.0.key', "order:{$order->id}")
      ->assertJsonPath('data.1.key', 'booking:50');

    $events = collect($firstPage->json('data'))->concat($secondPage->json('data'));
    $this->assertSame(52, $events->pluck('key')->unique()->count());
    $this->assertSame(1, $events->where('key', "check:{$item->id}")->count());
  }

  public function test_history_is_deterministic_and_paginated_at_fifty_events(): void
  {
    $item = $this->createItem();
    $timestamp = '2026-07-10 10:00:00';

    for ($index = 1; $index <= 51; $index++) {
      $this->createBooking($item, -1, $index, $timestamp);
    }

    $firstPage = $this->getJson("/api/inventory/{$item->id}/history?page=1")
      ->assertOk()
      ->assertJsonCount(50, 'data')
      ->assertJsonPath('current_page', 1)
      ->assertJsonPath('last_page', 2)
      ->assertJsonPath('per_page', 50)
      ->assertJsonPath('total', 51);

    $secondPage = $this->getJson("/api/inventory/{$item->id}/history?page=2")
      ->assertOk()
      ->assertJsonCount(1, 'data')
      ->assertJsonPath('current_page', 2);

    $this->assertSame('booking:51', $firstPage->json('data.0.key'));
    $this->assertSame('booking:1', $secondPage->json('data.0.key'));
  }

  public function test_history_supports_empty_results_and_missing_items(): void
  {
    $item = $this->createItem();

    $this->getJson("/api/inventory/{$item->id}/history")
      ->assertOk()
      ->assertJsonCount(0, 'data')
      ->assertJsonPath('total', 0);

    $this->getJson('/api/inventory/999999/history')->assertNotFound();
  }

  private function createItem(): Item
  {
    $this->fixtureSequence++;
    $demandId = DB::table('demands')->insertGetId([
      'name' => "Demand {$this->fixtureSequence}",
    ]);

    return Item::create([
      'name' => "Item {$this->fixtureSequence}",
      'demand_id' => $demandId,
      'location' => [],
      'min_stock' => 0,
      'max_stock' => 10,
      'current_quantity' => 5,
    ]);
  }

  private function createBooking(Item $item, int $usageId, int $amount, string $createdAt): void
  {
    DB::table('bookings')->insert([
      'usage_id' => $usageId,
      'item_id' => $item->id,
      'item_amount' => $amount,
      'created_at' => $createdAt,
      'updated_at' => $createdAt,
    ]);
  }
}
