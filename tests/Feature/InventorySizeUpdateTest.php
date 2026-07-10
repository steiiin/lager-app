<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Itemsize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InventorySizeUpdateTest extends TestCase
{
  use RefreshDatabase;

  public function test_update_deletes_removed_size_and_keeps_a_default_size(): void
  {
    $item = $this->createItem();

    $baseSize = Itemsize::create([
      'item_id' => $item->id,
      'unit' => 'Stk.',
      'amount' => 1,
      'is_default' => false,
    ]);

    $opSize = Itemsize::create([
      'item_id' => $item->id,
      'unit' => 'OP',
      'amount' => 10,
      'is_default' => true,
    ]);

    $response = $this->put("/inventory/{$item->id}", [
      'id' => $item->id,
      'name' => $item->name,
      'name_alt' => $item->name_alt,
      'search_size' => $item->search_size,
      'demand_id' => $item->demand_id,
      'location' => $item->location,
      'min_stock' => $item->min_stock,
      'max_stock' => $item->max_stock,
      'onvehicle_stock' => $item->onvehicle_stock,
      'sizes' => [
        [
          'id' => $baseSize->id,
          'unit' => 'Stk.',
          'amount' => 1,
          'is_default' => false,
        ],
      ],
      'expiry_entries' => [],
      'checked_at' => null,
      'current_quantity' => $item->current_quantity,
      'max_order_quantity' => $item->max_order_quantity,
      'max_bookin_quantity' => $item->max_bookin_quantity,
      'dont_order' => $item->dont_order,
      'stockchangeReason' => -1,
    ]);

    $response->assertRedirect(route('inventory.index'));

    $this->assertDatabaseMissing('itemsizes', [
      'id' => $opSize->id,
      'unit' => 'OP',
    ]);

    $this->assertDatabaseHas('itemsizes', [
      'id' => $baseSize->id,
      'unit' => 'Stk.',
      'amount' => 1,
      'is_default' => true,
    ]);
  }

  private function createItem(): Item
  {
    $demandId = DB::table('demands')->insertGetId([
      'name' => 'Default',
    ]);

    return Item::create([
      'name' => 'Test item',
      'name_alt' => '',
      'search_size' => '',
      'demand_id' => $demandId,
      'location' => [
        'room' => 'Lagerraum',
        'cab' => '',
        'exact' => '',
      ],
      'min_stock' => 0,
      'max_stock' => 10,
      'onvehicle_stock' => 1,
      'current_quantity' => 5,
      'checked_at' => null,
      'max_order_quantity' => 0,
      'max_bookin_quantity' => 0,
      'dont_order' => false,
    ]);
  }
}
