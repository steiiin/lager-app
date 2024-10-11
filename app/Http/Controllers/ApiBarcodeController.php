<?php

namespace App\Http\Controllers;

use App\Services\BarcodeService;

class ApiBarcodeController extends Controller
{

    public function index()
    {
        $BarcodeService = new BarcodeService();
        $barcodes = $BarcodeService->generateAll();
        return response()->json($barcodes);
    }

}
