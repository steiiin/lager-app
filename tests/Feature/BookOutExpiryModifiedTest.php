<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Itemexpiry;
use App\Models\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BookOutExpiryModifiedTest extends TestCase
{
  use RefreshDatabase;

  public function test_booking_out_to_usage_marks_matching_usage_expiry_as_modified(): void
  {
    $item = $this->createItem();
    $usage = $this->createUsage();
    $entry = $this->createExpiry($item, $usage);

    $response = $this->post('/bookout', [
      'usage_id' => $usage->id,
      'entries' => [
        [
          'item_id' => $item->id,
          'item_amount' => 1,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertTrue((bool) $entry->fresh()->is_modified);
  }

  public function test_booking_out_without_matching_usage_expiry_does_not_create_one(): void
  {
    $item = $this->createItem();
    $usage = $this->createUsage();

    $response = $this->post('/bookout', [
      'usage_id' => $usage->id,
      'entries' => [
        [
          'item_id' => $item->id,
          'item_amount' => 1,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertDatabaseMissing('itemexpiry', [
      'item_id' => $item->id,
      'usage_id' => $usage->id,
    ]);
  }

  public function test_internal_booking_does_not_mark_usage_expiry_as_modified(): void
  {
    $item = $this->createItem();
    $usage = $this->createUsage();
    $entry = $this->createExpiry($item, $usage);

    $response = $this->post('/bookout', [
      'usage_id' => -3,
      'entries' => [
        [
          'item_id' => $item->id,
          'item_amount' => 1,
        ],
      ],
    ]);

    $response->assertRedirect(route('welcome'));
    $this->assertFalse((bool) $entry->fresh()->is_modified);
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
      'current_expiry' => $attributes['current_expiry'] ?? null,
      'current_quantity' => $attributes['current_quantity'] ?? 10,
    ]);
  }

  private function createExpiry(Item $item, Usage $usage): Itemexpiry
  {
    return Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage->id,
      'expiryAt' => '2026-08-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'is_modified' => false,
      'note' => null,
    ]);
  }
}
