<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\StatisticService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatisticController extends Controller
{

    public function index()
    {
        $statisticService = new StatisticService();
        $stats = $statisticService->generateAll();
        return response()->json($stats);
    }

    public function logs()
    {
        $orders = Order::withLogs()->get();

        $mergedLogs = $orders->map(function ($order) {
            return $order->log;
        })->reduce(function ($carry, $item) {
            return array_merge($carry, $item);
        }, []);
        $orderIds = $orders->pluck('id');

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
