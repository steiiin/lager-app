<?php

/**
 * BookInController - controller
 *
 * Controller for BookIn page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Itemexpiry;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class BookInController extends Controller
{

  public function index(Request $request)
  {

    $open = Order::open()->get()
      ->each(fn($order) => $order->amount_delivered = $order->amount_desired);

    return Inertia::render('BookIn', [
      'openOrders' => $open,
    ]);
  }

  public function store(Request $request)
  {

    $validated = $request->validate([
      'orders' => 'required|array',
      'orders.*.id' => 'required|integer|distinct|exists:orders,id',
      'orders.*.amount_delivered' => 'required|integer|min:0',
    ]);

    try
    {
      $orders = collect($validated['orders'])->sortBy(fn ($order) => (int) $order['id']);

      DB::transaction(function () use ($orders) {

        foreach ($orders as $openOrder)
        {
          $id = (int) $openOrder['id'];
          $delivered = (int) $openOrder['amount_delivered'];

          // Claim before reading: only the request that closes an open order may book it.
          $claimed = Order::whereKey($id)->open()->update([
            'amount_delivered' => $delivered,
            'is_order_open' => false,
          ]);

          if ($claimed === 0) {
            continue;
          }

          if ($delivered > 0) {
            $order = Order::findOrFail($id);
            // Calculate against the database value, not an eager-loaded stock snapshot.
            DB::update(
              'UPDATE items SET current_quantity = CASE WHEN current_quantity < 0 THEN 0 ELSE current_quantity END + ?, updated_at = ? WHERE id = ?',
              [$delivered, $order->item->freshTimestampString(), $order->item_id]
            );

            $order->item->bookings()->create([
              'usage_id' => -4,
              'order_id' => $order->id,
              'item_amount' => $delivered,
            ]);

            Itemexpiry::query()
              ->where('item_id', $order->item_id)
              ->whereNull('usage_id')
              ->update([ 'is_modified' => true ]);
          }

        }

      }, 3);
    }
    catch (\Throwable $e)
    {
      report($e);

      return back()->withErrors([
        'orders' => 'Failed to book in deliveries. Please try again.',
      ]);
    }

    return redirect()->route('welcome');
  }

}
