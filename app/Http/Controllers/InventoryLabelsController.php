<?php

/**
 * InventoryLabelssController - controller
 *
 * Controller for InventoryLabelss page.
 *
 */

namespace App\Http\Controllers;

use App\Services\BarcodeService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Barryvdh\DomPDF\Facade\Pdf;
use Inertia\Inertia;

class InventoryLabelsController extends Controller
{

  public function index()
  {
    $barcodeService = new BarcodeService();
    return Inertia::render('InventoryLabels', [
      'ctrl' => $barcodeService->generateCtrlBatch(),
      'usages' => $barcodeService->generateUsagesBatch(),
      'items' => $barcodeService->generateItemsBatch(),
    ]);
  }

  public function store(Request $request)
  {

    $request->validate([
      'ctrl' => 'sometimes|array',
      'usage' => 'sometimes|array',
      'item' => 'sometimes|array',
    ]);

    $labels = $this->mapSelectionForPdf($request->ctrl, $request->usage, $request->item);

    $pdfBinary = Pdf::loadView('pdf.labels', [
      'filename' => "Labels",
      'labels'   => $labels,
    ])
    ->setPaper('a4');
    return $pdfBinary->download('invoice.pdf');

  }

  // ####################################################################################

  private function mapBatch(?Collection $batch, array $entries, callable $mapFn): Collection
  {
    $map = collect([]);
    if (count($entries) > 0)
    {
      foreach ($entries as $entry)
      {
        $code = is_array($entry) ? ($entry['code'] ?? null) : $entry;
        $symbol = is_array($entry) ? ($entry['symbol'] ?? null) : null;
        if ($code == null) { continue; }

        $org = $batch->firstWhere('code', $code);
        if ($org != null)
        {
          $label = $mapFn($org, $symbol);
          if ($label != null) {
            $map->push($label);
          }
        }
      }
    }
    return $map;
  }

  private function mapCtrl(array $ctrl, ?string $symbol = null): array|null
  {

    $name = "";
    if ($ctrl['name'] == "finish") { $name = "Buchung abschließen"; }
    else if ($ctrl['name'] == "expired") { $name = "Als Verfall buchen"; }
    else { return null; }

    return [
      "type" => "ctrl",
      "name" => $name,
      "code" => $ctrl['code'],
      "symbol" => $this->resolveSymbol($symbol, "check"),
    ];

  }

  private function mapUsage(array $usage, ?string $symbol = null): array
  {
    return [
      "type" => "ctrl",
      "name" => $usage['name'],
      "code" => $usage['code'],
      "symbol" => $this->resolveSymbol($symbol, "truck"),
    ];
  }

  private function mapItem(array $item, ?string $symbol = null): array
  {
    return [
      "type" => "item",
      "name" => $item['name'],
      "code" => $item['code'],
      "size" => $item['unit'],
    ];
  }


  private function mapSelectionForPdf(array $ctrl, array $usage, array $item): Collection
  {

    $barcodeService = new BarcodeService();
    $ctrlBatch = $barcodeService->generateCtrlBatch();
    $usagesBatch = $barcodeService->generateUsagesBatch();
    $itemsBatch = $barcodeService->generateItemsBatch();

    $ctrlLabels = $this->mapBatch($ctrlBatch, $ctrl, [ $this, 'mapCtrl' ]);
    $usageLabels = $this->mapBatch($usagesBatch, $usage, [ $this, 'mapUsage' ]);
    $itemLabels = $this->mapBatch($itemsBatch, $item, [ $this, 'mapItem' ]);

    return $ctrlLabels->merge($usageLabels)->merge($itemLabels);

  }

  private function resolveSymbol(?string $symbol, string $fallback): string
  {
    $map = [
      "check" => "check-bold",
      "home" => "home-city-outline",
      "expiry" => "timer-alert-outline",
      "truck" => "truck-outline",
    ];

    if ($symbol == null || $symbol === "") {
      return $map[$fallback] ?? $fallback;
    }

    return $map[$symbol] ?? $symbol;
  }

}
