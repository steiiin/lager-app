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

use App\Services\StatisticService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ApiStatisticController extends Controller
{

  public function index(Request $request)
  {

    $data = $request->validate([
      'item' => 'required|integer|exists:items,id',
    ]);

    $statisticService = new StatisticService();
    return response()->json($statisticService->getStats($data['item']));

  }

  public function logs(Request $request)
  {

    $data = $request->validate([
      'month' => 'sometimes|integer|min:1|max:12',
      'year' => 'required_with:month|integer|min:2024|max:2050',
    ]);

    $statisticService = new StatisticService();

    if ($request->has('month') && $request->has('year'))
    {
      return response()->json($statisticService->getLog($data['year'], $data['month']));
    }
    else
    {
      $lastMonth = Carbon::now()->previous(Carbon::MONDAY)->subMonth();
      return response()->json($statisticService->getLog($lastMonth->year, $lastMonth->month));
    }

  }

}
