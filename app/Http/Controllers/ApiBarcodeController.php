<?php

namespace App\Http\Controllers;

use App\Services\BarcodeGenerator;

class ApiBarcodeController extends Controller
{

    public function index()
    {
        $barcodeGenerator = new BarcodeGenerator();
        $barcodes = $barcodeGenerator->generateAll();
        return response()->json($barcodes);
    }

}
