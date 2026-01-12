<?php

/**
 * InventoryNoticesController - controller
 *
 * Controller for InventoryNotices page.
 *
 */

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;

class InventoryNoticesController extends Controller
{

  public function index()
  {
    return Inertia::render('InventoryNotices');
  }

  public function hygiene(Request $request)
  {
    return response()->noContent();
  }

  public function onvehicle(Request $request)
  {
    return response()->noContent();
  }

}
