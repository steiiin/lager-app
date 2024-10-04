<?php

namespace App\Http\Controllers;

use App\Services\StatisticService;

class StatisticController extends Controller
{

    public function index()
    {
        $statisticService = new StatisticService();
        $stats = $statisticService->generateAll();
        return response()->json($stats);
    }

}
