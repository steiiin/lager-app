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
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Validator;

class ApiStatisticController extends Controller
{

  public function index(Request $request)
  {
    $statisticService = new StatisticService();
    $type = $request->query('type');

    switch ($type) {
      case StatisticService::STATS_RANEMPTY:
        return response()->json($statisticService->getItemsRanEmpty());
      case StatisticService::STATS_PERITEM:
        $request->validate([
          'id' => 'required|integer|exists:items,id',
        ]);
        return response()->json($statisticService->getItemStats($request->query('item')));
      default:
        return response()->json();
    }
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
