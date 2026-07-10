<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Itemexpiry;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookInExpiryModifiedTest extends TestCase
{
  use RefreshDatabase;

  public function test_booking_in_delivered_amount_marks_stock_expiry_as_modified(): void
  {
    $item = $this->createItem();
    $usage = $this->createUsage();
    $order = $this->createOrder($item);
    $stockEntry = $this->createExpiry($item);
    $usageEntry = $this->createExpiry($item, $usage);

    $response = $this->post('/bookin', [
      'orders' => [
        [
          'id' => $order->id,
          'amount_delivered' => 2,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertTrue((bool) $stockEntry->fresh()->is_modified);
    $this->assertFalse((bool) $usageEntry->fresh()->is_modified);
  }

  public function test_booking_in_zero_delivered_amount_does_not_mark_stock_expiry_as_modified(): void
  {
    $item = $this->createItem();
    $order = $this->createOrder($item);
    $stockEntry = $this->createExpiry($item);

    $response = $this->post('/bookin', [
      'orders' => [
        [
          'id' => $order->id,
          'amount_delivered' => 0,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertFalse((bool) $stockEntry->fresh()->is_modified);
  }

  private function createUsage(array $attributes = []): Usage
  {
    return Usage::create([
      'name' => $attributes['name'] ?? 'Station',
      'could_expire' => $attributes['could_expire'] ?? true,
    ]);
  }

  private function createItem(array $attributes = []): Item
  {
    $demandId = DB::table('demands')->insertGetId([
      'name' => $attributes['demand_name'] ?? 'Default',
      'sp_name' => $attributes['demand_name'] ?? 'Default',
    ]);

    return Item::create([
      'name' => $attributes['name'] ?? 'Test item',
      'demand_id' => $demandId,
      'location' => $attributes['location'] ?? [],
      'min_stock' => $attributes['min_stock'] ?? 0,
      'max_stock' => $attributes['max_stock'] ?? 0,
      'current_quantity' => $attributes['current_quantity'] ?? 10,
    ]);
  }

  private function createOrder(Item $item, array $attributes = []): Order
  {
    return Order::create([
      'item_id' => $item->id,
      'order_date' => $attributes['order_date'] ?? '2026-07-10',
      'amount_desired' => $attributes['amount_desired'] ?? 2,
      'amount_delivered' => $attributes['amount_delivered'] ?? 0,
      'is_order_open' => $attributes['is_order_open'] ?? true,
    ]);
  }

  private function createExpiry(Item $item, ?Usage $usage = null): Itemexpiry
  {
    return Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage?->id,
      'expiryAt' => '2026-08-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'is_modified' => false,
      'note' => null,
    ]);
  }
}
