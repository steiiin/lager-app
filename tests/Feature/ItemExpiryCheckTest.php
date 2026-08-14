<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Itemexpiry;
use App\Models\Usage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemExpiryCheckTest extends TestCase
{
  use RefreshDatabase;

  public function test_checking_expiry_clears_is_modified_without_changing_expiry_data(): void
  {
    $item = $this->createItem();
    $usage = Usage::create([
      'name' => 'Station',
      'could_expire' => true,
    ]);
    $entry = Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage->id,
      'expiryAt' => '2026-08-31',
      'expiryQuantity' => 4,
      'status' => 'reserved',
      'is_ordered' => true,
      'is_modified' => true,
      'note' => 'Unchanged note',
    ]);

    $response = $this->putJson("/api/item-expiry/{$entry->id}/check");

    $response
      ->assertOk()
      ->assertJsonPath('id', $entry->id)
      ->assertJsonPath('is_modified', false);

    $entry->refresh();

    $this->assertFalse($entry->is_modified);
    $this->assertSame($item->id, $entry->item_id);
    $this->assertSame($usage->id, $entry->usage_id);
    $this->assertSame('2026-08-31', $entry->expiryAt->toDateString());
    $this->assertSame(4, $entry->expiryQuantity);
    $this->assertSame('reserved', $entry->status);
    $this->assertTrue($entry->is_ordered);
    $this->assertSame('Unchanged note', $entry->note);
  }

  public function test_checking_unknown_expiry_returns_not_found(): void
  {
    $this->putJson('/api/item-expiry/999999/check')->assertNotFound();
  }

  private function createItem(): Item
  {
    $demandId = DB::table('demands')->insertGetId([
      'name' => 'Default',
      'sp_name' => 'Default',
    ]);

    return Item::create([
      'name' => 'Test item',
      'demand_id' => $demandId,
      'location' => [],
      'min_stock' => 0,
      'max_stock' => 0,
      'current_quantity' => 0,
    ]);
  }
}
