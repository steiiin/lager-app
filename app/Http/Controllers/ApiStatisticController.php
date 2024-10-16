<?php

/**
 * ApiStatisticController - controller
 *
 * Controller to handle statistic-api-endpoint.
 * Index: Create stats for each item.
 * Logs: Fetch logs in the order table.
 * Truncate: Remove saved logs, to save space.
 *
 */

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StatisticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ApiStatisticController extends Controller
{

    public function index()
    {
        $statisticService = new StatisticService();
        $stats = $statisticService->generateAll();
        return response()->json($stats);
    }

    public function logs()
    {

        $mergedLogs = [];
        $orderIds = [];

        $orders = Order::withLogs()->get();
        if ($orders->count() > 0)
        {
            $mergedLogs = $orders->groupBy('item_id')->map(function ($groupedOrders, $itemId) {
                $itemName = $groupedOrders->first()->item->name;
                $mergedLogs = $groupedOrders->reduce(function ($carry, $order) {
                    return array_merge($carry, $order->log);
                }, []);
                return [
                    'id' => $itemId,
                    'name' => $itemName,
                    'logs' => array_values($mergedLogs),
                ];
            })->values(); // Reset the keys
            $orderIds = $orders->pluck('id');
        }

        return response()->json([
            'logs' => $mergedLogs,
            'orders' => $orderIds,
        ]);

    }

    public function truncate(Request $request)
    {

        $request->validate([
            'orders' => 'required|array',
            'orders.0' => 'required',
            'orders.*' => 'integer|exists:orders,id',
        ]);

        DB::transaction(function () use ($request)
        {
            foreach ($request->orders as $orderId)
            {
                $order = Order::findOrFail($orderId);
                $order->update([
                    'log' => [],
                ]);
            }
        });

    }

}
