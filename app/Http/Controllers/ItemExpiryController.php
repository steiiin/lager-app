<?php

namespace App\Http\Controllers;

use App\Models\Itemexpiry;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
    $data = $this->validateExpiry($request, $expiry);
    $data['status'] = $request->input('status', $expiry->status);

    $expiry->update($data);

    return response()->json($expiry->fresh());
  }

  public function destroy($id): JsonResponse
  {
    $expiry = Itemexpiry::findOrFail($id);

    DB::transaction(function () use ($expiry) {
      if ($expiry->usage_id === null) {
        Itemexpiry::where('item_id', $expiry->item_id)
          ->whereNotNull('usage_id')
          ->delete();
      }

      $expiry->delete();
    });

    return response()->json([ 'ok' => true ]);
  }

  public function dismiss(Request $request, $id): JsonResponse
  {
    $data = $request->validate([
      'nextExpiryAt' => 'required|date',
      'usage_id' => 'nullable|integer|exists:usages,id',
      'update_inventory' => 'sometimes|boolean',
    ]);

    $expiry = Itemexpiry::with('item.expiryEntries')->findOrFail($id);
    $nextExpiry = CarbonImmutable::parse($data['nextExpiryAt'])->startOfDay();

    if ($expiry->usage_id === null && isset($data['usage_id'])) {
      $usageExpiry = Itemexpiry::query()
        ->where('item_id', $expiry->item_id)
        ->where('usage_id', $data['usage_id'])
        ->where('status', 'reserved')
        ->orderBy('expiryAt')
        ->first();

      if ($usageExpiry === null) {
        $usageExpiry = new Itemexpiry([
          'item_id' => $expiry->item_id,
          'usage_id' => $data['usage_id'],
          'status' => 'reserved',
        ]);
      }

      $expiry->expiryAt = $nextExpiry;
      $expiry->save();

      $usageExpiry->expiryAt = $nextExpiry;
      $usageExpiry->expiryQuantity = 1;
      $usageExpiry->note = null;
      $usageExpiry->save();

      return response()->json($usageExpiry->fresh());
    }

    if ($request->boolean('update_inventory')) {
      $this->updateInventoryExpiry($expiry, $nextExpiry);

      $expiry->expiryAt = $nextExpiry;
      $expiry->save();

      return response()->json($expiry->fresh());
    }

    $inventoryExpiry = $this->getInventoryExpiry($expiry);

    $expiry->expiryAt = $inventoryExpiry !== null && $inventoryExpiry->lt($nextExpiry)
      ? $inventoryExpiry
      : $nextExpiry;
    $expiry->save();

    return response()->json($expiry->fresh());
  }

  private function validateExpiry(Request $request, ?Itemexpiry $existingExpiry = null): array
  {
    $data = $request->validate([
      'item_id' => 'required|integer|exists:items,id',
      'usage_id' => 'nullable|integer|exists:usages,id',
      'expiryAt' => 'required|date',
      'expiryQuantity' => 'nullable|integer|min:1|max:99999',
      'status' => 'nullable|string|max:255',
      'is_ordered' => 'sometimes|boolean',
      'note' => 'nullable|string|max:255',
    ]);

    $data['is_ordered'] = $request->has('is_ordered')
      ? $request->boolean('is_ordered')
      : (bool) ($existingExpiry?->is_ordered ?? false);

    if (($data['usage_id'] ?? null) === null) {
      $data['expiryQuantity'] = 1;
    } else {
      if (!isset($data['expiryQuantity'])) {
        throw ValidationException::withMessages([
          'expiryQuantity' => ['Die Menge muss ausgefüllt werden.'],
        ]);
      }

      if (!$this->hasStockExpiry($data['item_id'], $existingExpiry)) {
        throw ValidationException::withMessages([
          'usage_id' => ['Bitte erfasse zuerst den Verfall für den Lagerbestand.'],
        ]);
      }
    }

    return $data;
  }

  private function hasStockExpiry(int $itemId, ?Itemexpiry $existingExpiry = null): bool
  {
    $query = Itemexpiry::query()
      ->where('item_id', $itemId)
      ->whereNull('usage_id')
      ->where('status', 'reserved')
      ->where('expiryQuantity', '>', 0)
      ->whereNotNull('expiryAt');

    if ($existingExpiry !== null) {
      $query->where('id', '!=', $existingExpiry->id);
    }

    return $query->exists();
  }

  private function getInventoryExpiry(Itemexpiry $expiry): ?CarbonImmutable
  {
    if ($expiry->usage_id === null) {
      return null;
    }

    $stockEntry = $this->getStockEntry($expiry);

    if ($stockEntry?->expiryAt) {
      return CarbonImmutable::parse($stockEntry->expiryAt)->startOfDay();
    }

    if ($expiry->item?->current_expiry) {
      return CarbonImmutable::parse($expiry->item->current_expiry)->startOfDay();
    }

    return null;
  }

  private function updateInventoryExpiry(Itemexpiry $expiry, CarbonImmutable $nextExpiry): void
  {
    $stockEntry = $expiry->usage_id === null
      ? $expiry
      : $this->getStockEntry($expiry);

    if ($stockEntry !== null) {
      $stockEntry->expiryAt = $nextExpiry;
      $stockEntry->save();
      return;
    }

    if ($expiry->item?->current_expiry) {
      $expiry->item->current_expiry = $nextExpiry;
      $expiry->item->save();
    }
  }

  private function getStockEntry(Itemexpiry $expiry): ?Itemexpiry
  {
    return $expiry->item?->expiryEntries
      ->where('status', 'reserved')
      ->where('usage_id', null)
      ->where('expiryQuantity', '>', 0)
      ->whereNotNull('expiryAt')
      ->sortBy('expiryAt')
      ->first();
  }

}
