<?php

namespace App\Http\Controllers;

use App\Services\BarcodeGenerator;
use App\Services\BookingService;
use App\Services\OrderService;

class OrderController extends Controller
{

    protected $orderService;
    protected $bookingService;

    public function __construct(OrderService $orderService, BookingService $bookingService)
    {
        $this->orderService = $orderService;
        $this->bookingService = $bookingService;
    }

    public function index()
    {
        $orderData = $this->orderService->prepareData();
        return response()->json([
            'hasSome' => !empty($orderData),
            'orderdata' => $orderData,
        ]);
    }

}
