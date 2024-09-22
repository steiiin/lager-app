<?php

namespace App\Http\Controllers;

use App\Services\BarcodeGenerator;
use App\Services\OrderService;

class OrderController extends Controller
{

    protected $orderService;

    public function __construct(OrderService $orderService)
    {
        $this->orderService = $orderService;
    }

    public function check()
    {
        $hasSome = $this->orderService->hasSome();
        return response()->json([
            'hasSome' => $hasSome
        ]);
    }

    public function execute()
    {
        $orderData = $this->orderService->execute();
        return response()->json([
            'orderdata' => $orderData,
        ]);
    }

    public function getlast()
    {
        $orderData = ""; //$this->orderService->getLatest();
        return response()->json([
            'has_some' => !empty($orderData),
            'orderdata' => $orderData,
        ]);
    }

}
