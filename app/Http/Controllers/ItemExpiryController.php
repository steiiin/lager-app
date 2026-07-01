<?php

namespace App\Http\Controllers;

use App\Models\Itemexpiry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ItemExpiryController extends Controller
{

  public function store(Request $request): JsonResponse
  {
    $data = $this->validateExpiry($request);
    $data['status'] = 'reserved';

    $expiry = Itemexpiry::create($data);

    return response()->json($expiry->fresh(), 201);
  }

  public function update(Request $request, $id): JsonResponse
  {
    $expiry = Itemexpiry::findOrFail($id);
    $data = $this->validateExpiry($request);
    $data['status'] = $request->input('status', $expiry->status);

    $expiry->update($data);

    return response()->json($expiry->fresh());
  }

  public function destroy($id): JsonResponse
  {
    $expiry = Itemexpiry::findOrFail($id);
    $expiry->delete();

    return response()->json([ 'ok' => true ]);
  }

  public function dismiss(Request $request, $id): JsonResponse
  {
    $data = $request->validate([
      'nextExpiryAt' => 'required|date',
    ]);

    $expiry = Itemexpiry::with('item.expiryEntries')->findOrFail($id);
    $nextExpiry = CarbonImmutable::parse($data['nextExpiryAt'])->startOfDay();
    $inventoryExpiry = $this->getInventoryExpiry($expiry);

    $expiry->expiryAt = $inventoryExpiry !== null && $inventoryExpiry->lt($nextExpiry)
      ? $inventoryExpiry
      : $nextExpiry;
    $expiry->save();

    return response()->json($expiry->fresh());
  }

  private function validateExpiry(Request $request): array
  {
    $data = $request->validate([
      'item_id' => 'required|integer|exists:items,id',
      'usage_id' => 'nullable|integer|exists:usages,id',
      'expiryAt' => 'required|date',
      'expiryQuantity' => 'nullable|integer|min:1|max:99999',
      'status' => 'nullable|string|max:255',
      'note' => 'nullable|string|max:255',
    ]);

    if (($data['usage_id'] ?? null) === null) {
      $data['expiryQuantity'] = 1;
    } else if (!isset($data['expiryQuantity'])) {
      throw ValidationException::withMessages([
        'expiryQuantity' => ['Die Menge muss ausgefüllt werden.'],
      ]);
    }

    return $data;
  }

  private function getInventoryExpiry(Itemexpiry $expiry): ?CarbonImmutable
  {
    if ($expiry->usage_id === null) {
      return null;
    }

    $stockEntry = $expiry->item?->expiryEntries
      ->where('status', 'reserved')
      ->where('usage_id', null)
      ->where('expiryQuantity', '>', 0)
      ->whereNotNull('expiryAt')
      ->sortBy('expiryAt')
      ->first();

    if ($stockEntry?->expiryAt) {
      return CarbonImmutable::parse($stockEntry->expiryAt)->startOfDay();
    }

    if ($expiry->item?->current_expiry) {
      return CarbonImmutable::parse($expiry->item->current_expiry)->startOfDay();
    }

    return null;
  }

}
