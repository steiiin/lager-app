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
        $stats = $statisticService->generateStatistic();
        return response()->json($stats);
    }

    public function logs(Request $request)
    {

        $request->validate([
            'orders' => 'required|array',
            'orders.0' => 'required',
            'orders.*' => 'integer|exists:orders,id',
        ]);

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

}
