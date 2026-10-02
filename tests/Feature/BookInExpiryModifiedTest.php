<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Booking;
use App\Models\Itemexpiry;
use App\Models\Order;
use App\Models\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookInExpiryModifiedTest extends TestCase
{
  use RefreshDatabase;

  public function test_repeated_requests_preserve_the_first_delivery_and_do_not_modify_expiry_again(): void
  {
    $item = $this->createItem(['current_quantity' => 0]);
    $order = $this->createOrder($item, ['amount_desired' => 8]);
    $expiry = $this->createExpiry($item);

    $this->deliver([$order->id => 8])->assertRedirect(route('welcome'));
    $expiry->refresh()->update(['is_modified' => false]);
    $updatedAt = $item->fresh()->updated_at;
    $this->travel(1)->minutes();

    $this->deliver([$order->id => 8])->assertRedirect(route('welcome'));
    $this->deliver([$order->id => 99])->assertRedirect(route('welcome'));

    $this->assertSame(8, $item->fresh()->current_quantity);
    $this->assertTrue($updatedAt->equalTo($item->fresh()->updated_at));
    $this->assertSame(8, $order->fresh()->amount_delivered);
    $this->assertFalse((bool) $order->fresh()->is_order_open);
    $this->assertFalse((bool) $expiry->fresh()->is_modified);
    $this->assertDatabaseCount('bookings', 1);
    $this->assertDatabaseHas('bookings', ['order_id' => $order->id, 'usage_id' => -4, 'item_amount' => 8]);
  }

  public function test_mixed_batches_skip_completed_orders_and_add_each_open_order_for_the_same_item(): void
  {
    $item = $this->createItem(['current_quantity' => 2]);
    $closed = $this->createOrder($item);
    $first = $this->createOrder($item, ['order_date' => '2026-07-11']);
    $second = $this->createOrder($item, ['order_date' => '2026-07-12']);
    $this->deliver([$closed->id => 3])->assertRedirect(route('welcome'));

    // Deliberately submit out of order, including a conflicting completed delivery.
    $payload = [$second->id => 4, $closed->id => 99, $first->id => 2];
    $this->deliver($payload)->assertRedirect(route('welcome'));
    $this->deliver($payload)->assertRedirect(route('welcome'));

    $this->assertSame(11, $item->fresh()->current_quantity);
    $this->assertSame(3, $closed->fresh()->amount_delivered);
    $this->assertSame(2, $first->fresh()->amount_delivered);
    $this->assertSame(4, $second->fresh()->amount_delivered);
    $this->assertSame(0, Order::open()->count());
    $this->assertSame([$closed->id, $first->id, $second->id], Booking::orderBy('id')->pluck('order_id')->all());
  }

  public function test_duplicate_order_ids_reject_the_whole_payload_before_any_writes(): void
  {
    $item = $this->createItem();
    $order = $this->createOrder($item);
    $other = $this->createOrder($item, ['order_date' => '2026-07-11']);
    $expiry = $this->createExpiry($item);

    $this->postJson('/bookin', ['orders' => [
      ['id' => $other->id, 'amount_delivered' => 2],
      ['id' => $order->id, 'amount_delivered' => 2],
      ['id' => (string) $order->id, 'amount_delivered' => 3],
    ]])->assertUnprocessable()->assertJsonValidationErrors(['orders.1.id', 'orders.2.id']);

    $this->assertSame(10, $item->fresh()->current_quantity);
    $this->assertSame(2, Order::open()->count());
    $this->assertDatabaseCount('bookings', 0);
    $this->assertFalse((bool) $expiry->fresh()->is_modified);
  }

  public function test_zero_delivery_cannot_be_reopened_by_a_later_positive_delivery(): void
  {
    $item = $this->createItem(['current_quantity' => -2]);
    $order = $this->createOrder($item);
    $expiry = $this->createExpiry($item);

    $this->deliver([$order->id => 0])->assertRedirect(route('welcome'));
    $this->deliver([$order->id => 5])->assertRedirect(route('welcome'));

    $this->assertSame(-2, $item->fresh()->current_quantity);
    $this->assertSame(0, $order->fresh()->amount_delivered);
    $this->assertFalse((bool) $order->fresh()->is_order_open);
    $this->assertDatabaseCount('bookings', 0);
    $this->assertFalse((bool) $expiry->fresh()->is_modified);
  }

  public function test_failure_after_claiming_rolls_back_the_entire_batch_and_allows_retry(): void
  {
    $item = $this->createItem(['current_quantity' => -1]);
    $first = $this->createOrder($item);
    $second = $this->createOrder($item, ['order_date' => '2026-07-11']);
    $expiry = $this->createExpiry($item);
    $fail = true;
    Booking::creating(function (Booking $booking) use ($second, &$fail) {
      if ($fail && $booking->order_id === $second->id) {
        throw new \RuntimeException('Injected delivery failure');
      }
    });

    try {
      $payload = [$first->id => 3, $second->id => 4];
      $this->from('/bookin')->deliver($payload)
        ->assertRedirect('/bookin')->assertSessionHasErrors('orders');

      $this->assertSame(-1, $item->fresh()->current_quantity);
      $this->assertSame(2, Order::open()->count());
      $this->assertSame([0, 0], Order::orderBy('id')->pluck('amount_delivered')->all());
      $this->assertDatabaseCount('bookings', 0);
      $this->assertFalse((bool) $expiry->fresh()->is_modified);

      $fail = false;
      $this->deliver($payload)->assertRedirect(route('welcome'));
      $this->assertSame(7, $item->fresh()->current_quantity);
      $this->assertSame(0, Order::open()->count());
      $this->assertDatabaseCount('bookings', 2);
      $this->assertTrue((bool) $expiry->fresh()->is_modified);
    } finally {
      Booking::flushEventListeners();
    }
  }

  private function deliver(array $quantities): \Illuminate\Testing\TestResponse
  {
    return $this->post('/bookin', ['orders' => collect($quantities)
      ->map(fn ($amount, $id) => ['id' => $id, 'amount_delivered' => $amount])
      ->values()->all()]);
  }

  public function test_booking_in_delivery_uses_zero_as_baseline_for_negative_stock(): void
  {
    $item = $this->createItem(['current_quantity' => -1]);
    $order = $this->createOrder($item, ['amount_desired' => 3]);

    $response = $this->post('/bookin', [
      'orders' => [
        [
          'id' => $order->id,
          'amount_delivered' => 3,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertSame(3, $item->fresh()->current_quantity);
    $this->assertDatabaseHas('orders', [
      'id' => $order->id,
      'amount_delivered' => 3,
      'is_order_open' => false,
    ]);
    $this->assertDatabaseHas('bookings', [
      'item_id' => $item->id,
      'order_id' => $order->id,
      'usage_id' => -4,
      'item_amount' => 3,
    ]);
  }

  public function test_booking_in_delivery_is_added_to_non_negative_stock(): void
  {
    $item = $this->createItem(['current_quantity' => 2]);
    $order = $this->createOrder($item, ['amount_desired' => 3]);

    $response = $this->post('/bookin', [
      'orders' => [
        [
          'id' => $order->id,
          'amount_delivered' => 3,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertSame(5, $item->fresh()->current_quantity);
  }

  public function test_booking_in_zero_delivery_preserves_negative_stock(): void
  {
    $item = $this->createItem(['current_quantity' => -1]);
    $order = $this->createOrder($item);

    $response = $this->post('/bookin', [
      'orders' => [
        [
          'id' => $order->id,
          'amount_delivered' => 0,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertSame(-1, $item->fresh()->current_quantity);
    $this->assertDatabaseHas('orders', [
      'id' => $order->id,
      'amount_delivered' => 0,
      'is_order_open' => false,
    ]);
    $this->assertDatabaseMissing('bookings', [
      'order_id' => $order->id,
    ]);
  }

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
