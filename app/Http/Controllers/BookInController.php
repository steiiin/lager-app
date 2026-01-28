<?php

/**
 * BookInController - controller
 *
 * Controller for BookIn page.
 *
 */

namespace App\Http\Controllers;

use App\Models\Booking;
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

    $request->validate([
      'orders' => 'required|array',
      'orders.*.id' => 'required|integer|exists:orders,id',
      'orders.*.amount_delivered' => 'required|integer|min:0',
    ]);

    try
    {
      DB::transaction(function () use ($request) {

        foreach ($request->orders as $openOrder)
        {
          $id = $openOrder['id'];
          $delivered = $openOrder['amount_delivered'];

          $order = Order::findOrFail($id);
          $order->update([
            'amount_delivered' => $delivered,
            'is_order_open' => false,
          ]);
          $order->item->increment('current_quantity', $delivered);

          if ($delivered > 0) {
            $order->item->bookings()->create([
              'usage_id' => -4,
              'order_id' => $order->id,
              'item_amount' => $delivered,
            ]);
          }

        }

      });
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
