<?php

/**
 * BookInController - controller
 *
 * Controller for BookIn page.
 *
 */

namespace App\Http\Controllers;

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
      'order.*.id' => 'required|integer|exists:orders,id',
      'order.*.amount_delivered' => 'required|integer|min:0',
    ]);

    DB::transaction(function () use ($request) {

      foreach ($request->orders as $openOrder)
      {

        try
        {

          $id = $openOrder['id'];
          $delivered = $openOrder['amount_delivered'];

          $order = Order::findOrFail($id);
          $order->update([
            'amount_delivered' => $delivered,
            'is_order_open' => false,
          ]);
          $order->item->increment('current_quantity', $delivered);

        }
        catch (\Throwable $e)
        {
          // continue loop
        }

      }

    });

    return redirect()->route('welcome');
  }

}
