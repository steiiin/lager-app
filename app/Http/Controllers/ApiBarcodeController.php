<?php

namespace App\Http\Controllers;

use App\Services\BarcodeService;

class ApiBarcodeController extends Controller
{

    public function __construct(private BarcodeService $barcodeService) {}

    public function index()
    {
        $barcodes = $this->barcodeService->generateAll();
        return response()->json($barcodes);
    }

}
