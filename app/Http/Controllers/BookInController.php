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

        $openOrders = Order::open()->get();
        $openOrders->each(function ($order)
        {
            $order->amount_delivered = $order->amount_desired;
        });
        return Inertia::render('BookIn', [
            'openOrders' => $openOrders,
        ]);

    }

    public function store(Request $request)
    {

        $request->validate([
            'orders' => 'required|array',
            'order.*.id' => 'required|integer|exists:orders,id',
            'order.*.amount_delivered' => 'required|integer|min:0',
        ]);

        DB::transaction(function () use ($request)
        {

            $revisedData = $request['orders'];
            foreach($revisedData as $revisedDatum)
            {
                $order = Order::findOrFail($revisedDatum['id']);
                $order->update([
                    'amount_delivered' => $revisedDatum['amount_delivered'],
                    'is_order_open' => false,
                ]);
                $order->item->increment('current_quantity', $revisedDatum['amount_delivered']);
            }

        });

        return redirect()->route('welcome');

    }

}
