<?php

namespace App\Http\Controllers;

use App\Services\ExpiryService;
use Inertia\Inertia;

class ExpiryController extends Controller
{

  public function index(ExpiryService $expiryService)
  {
    return Inertia::render('Expiry', [
      'expiryData' => $expiryService->generate(),
    ]);
  }

}
