<?php

namespace Tests\Feature;

use App\Http\Controllers\ApiOrderController;
use App\Models\Item;
use App\Models\Itemsize;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\TestCase;

class OrderDontOrderFilterTest extends TestCase
{
  use RefreshDatabase;

  public function test_order_candidates_exclude_items_marked_dont_order(): void
  {
    $orderable = $this->createRestockItem('Orderable item', false);
    $suspended = $this->createRestockItem('Suspended item', true);

    $method = new ReflectionMethod(ApiOrderController::class, 'getItemsNeedingRestock');
    $method->setAccessible(true);

    $items = $method->invoke(new ApiOrderController());
    $itemIds = $items->pluck('id');

    $this->assertTrue($itemIds->contains($orderable->id));
    $this->assertFalse($itemIds->contains($suspended->id));
  }

  private function createRestockItem(string $name, bool $dontOrder): Item
  {
    $demandId = DB::table('demands')->insertGetId([
      'name' => "{$name} demand",
    ]);

    $item = Item::create([
      'name' => $name,
      'name_alt' => '',
      'search_size' => '',
      'demand_id' => $demandId,
      'location' => [
        'room' => 'Lagerraum',
        'cab' => '',
        'exact' => '',
      ],
      'min_stock' => 5,
      'max_stock' => 10,
      'onvehicle_stock' => 1,
      'current_quantity' => 0,
      'checked_at' => null,
      'max_order_quantity' => 0,
      'max_bookin_quantity' => 0,
      'dont_order' => $dontOrder,
    ]);

    Itemsize::create([
      'item_id' => $item->id,
      'unit' => 'Stk.',
      'amount' => 1,
      'is_default' => true,
    ]);

    return $item;
  }
}
