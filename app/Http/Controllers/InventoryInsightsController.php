<?php

/**
 * InventoryInsightsController - controller
 *
 * Controller for InventoryInsights page.
 *
 */

namespace App\Http\Controllers;

use Inertia\Inertia;

class InventoryInsightsController extends Controller
{

  public function index()
  {
    return Inertia::render('InventoryInsights');
  }
}
