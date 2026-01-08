<?php

/**
 * InventoryInsightsController - controller
 *
 * Controller for InventoryInsights page.
 *
 */

namespace App\Http\Controllers;

use App\Services\StatisticService;
use Inertia\Inertia;

class InventoryInsightsController extends Controller
{

  public function index()
  {
    $statisticService = new StatisticService();

    return Inertia::render('InventoryInsights', [
      'lowScanSignals' => $statisticService->findLowScanShiftSignals(),
    ]);
  }
}
