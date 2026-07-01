<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Itemexpiry;
use App\Models\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemExpiryDismissTest extends TestCase
{
  use RefreshDatabase;

  public function test_dismiss_uses_user_expiry_when_inventory_expiry_is_missing(): void
  {
    $item = $this->createItem();
    $usage = Usage::create([
      'name' => 'Station',
      'could_expire' => true,
    ]);
    $entry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage->id,
      'expiryAt' => '2026-07-31',
      'expiryQuantity' => 2,
      'status' => 'reserved',
      'note' => null,
    ]);

    $response = $this->putJson("/api/item-expiry/{$entry->id}/dismiss", [
      'nextExpiryAt' => '2026-09-30',
    ]);

    $response->assertOk();
    $this->assertSame('2026-09-30', $entry->fresh()->expiryAt->toDateString());
  }

  public function test_dismiss_uses_inventory_expiry_when_it_is_nearer(): void
  {
    $item = $this->createItem();
    $usage = Usage::create([
      'name' => 'Station',
      'could_expire' => true,
    ]);
    Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => null,
      'expiryAt' => '2026-08-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => null,
    ]);
    $entry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage->id,
      'expiryAt' => '2026-07-31',
      'expiryQuantity' => 2,
      'status' => 'reserved',
      'note' => null,
    ]);

    $response = $this->putJson("/api/item-expiry/{$entry->id}/dismiss", [
      'nextExpiryAt' => '2026-12-31',
    ]);

    $response->assertOk();
    $this->assertSame('2026-08-31', $entry->fresh()->expiryAt->toDateString());
  }

  public function test_stock_expiry_rows_can_be_dismissed(): void
  {
    $item = $this->createItem();
    $entry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => null,
      'expiryAt' => '2026-07-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => null,
    ]);

    $response = $this->putJson("/api/item-expiry/{$entry->id}/dismiss", [
      'nextExpiryAt' => '2026-10-31',
    ]);

    $response->assertOk();
    $this->assertSame('2026-10-31', $entry->fresh()->expiryAt->toDateString());
  }

  public function test_dismissing_stock_expiry_for_usage_updates_stock_and_usage_expiry(): void
  {
    $item = $this->createItem();
    $usage = Usage::create([
      'name' => 'Station',
      'could_expire' => true,
    ]);
    $stockEntry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => null,
      'expiryAt' => '2026-07-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => 'Stock note',
    ]);

    $response = $this->putJson("/api/item-expiry/{$stockEntry->id}/dismiss", [
      'nextExpiryAt' => '2026-10-31',
      'usage_id' => $usage->id,
    ]);

    $response->assertOk();
    $this->assertSame('2026-10-31', $stockEntry->fresh()->expiryAt->toDateString());
    $this->assertDatabaseHas('itemexpiry', [
      'item_id' => $item->id,
      'usage_id' => $usage->id,
      'expiryAt' => '2026-10-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => null,
    ]);
  }

  public function test_dismissing_red_usage_expiry_updates_stock_and_usage_expiry(): void
  {
    $item = $this->createItem();
    $usage = Usage::create([
      'name' => 'Station',
      'could_expire' => true,
    ]);
    $stockEntry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => null,
      'expiryAt' => '2026-07-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => null,
    ]);
    $usageEntry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage->id,
      'expiryAt' => '2026-08-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => null,
    ]);

    $response = $this->putJson("/api/item-expiry/{$usageEntry->id}/dismiss", [
      'nextExpiryAt' => '2026-10-31',
      'update_inventory' => true,
    ]);

    $response->assertOk();
    $this->assertSame('2026-10-31', $stockEntry->fresh()->expiryAt->toDateString());
    $this->assertSame('2026-10-31', $usageEntry->fresh()->expiryAt->toDateString());
  }

  public function test_dismiss_requires_a_valid_next_expiry(): void
  {
    $item = $this->createItem();
    $entry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => null,
      'expiryAt' => '2026-07-31',
      'expiryQuantity' => 1,
      'status' => 'reserved',
      'note' => null,
    ]);

    $response = $this->putJson("/api/item-expiry/{$entry->id}/dismiss", [
      'nextExpiryAt' => 'not-a-date',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['nextExpiryAt']);
  }

  private function createItem(array $attributes = []): Item
  {
    $demandId = DB::table('demands')->insertGetId([
      'name' => 'Default',
      'sp_name' => 'Default',
    ]);

    return Item::create([
      'name' => $attributes['name'] ?? 'Test item',
      'demand_id' => $demandId,
      'location' => $attributes['location'] ?? [],
      'min_stock' => $attributes['min_stock'] ?? 0,
      'max_stock' => $attributes['max_stock'] ?? 0,
      'current_expiry' => $attributes['current_expiry'] ?? null,
      'current_quantity' => $attributes['current_quantity'] ?? 0,
    ]);
  }
}
