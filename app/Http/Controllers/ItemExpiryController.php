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
    $data['is_modified'] = false;

    $expiry = DB::transaction(function () use ($data, $request) {
      $expiry = Itemexpiry::create($data);

      if ($expiry->usage_id !== null && $request->boolean('update_inventory')) {
        $this->updateInventoryExpiryIfOlder($expiry, CarbonImmutable::parse($expiry->expiryAt)->startOfDay());
      }

      return $expiry;
    });

    return response()->json($expiry->fresh(), 201);
  }

  public function update(Request $request, $id): JsonResponse
  {
    $expiry = Itemexpiry::findOrFail($id);
    $data = $this->validateExpiry($request, $expiry);
    $data['status'] = $request->input('status', $expiry->status);
    $data['is_modified'] = false;

    DB::transaction(function () use ($expiry, $data, $request) {
      $expiry->update($data);

      if ($expiry->usage_id !== null && $request->boolean('update_inventory')) {
        $this->updateInventoryExpiryIfOlder($expiry, CarbonImmutable::parse($expiry->expiryAt)->startOfDay());
      }
    });

    return response()->json($expiry->fresh());
  }

  public function destroy($id): JsonResponse
  {
    $expiry = Itemexpiry::findOrFail($id);

    DB::transaction(function () use ($expiry) {
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

    $expiry->expiryAt = $nextExpiry;
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
      'is_modified' => 'sometimes|boolean',
      'note' => 'nullable|string|max:255',
    ]);

    $data['is_ordered'] = $request->has('is_ordered')
      ? $request->boolean('is_ordered')
      : (bool) ($existingExpiry?->is_ordered ?? false);

    $data['is_modified'] = $request->has('is_modified')
      ? $request->boolean('is_modified')
      : (bool) ($existingExpiry?->is_modified ?? false);

    if (($data['usage_id'] ?? null) === null) {
      $data['expiryQuantity'] = 1;
    } else {
      if (!isset($data['expiryQuantity'])) {
        throw ValidationException::withMessages([
          'expiryQuantity' => ['Die Menge muss ausgefüllt werden.'],
        ]);
      }
    }

    return $data;
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
  }

  private function updateInventoryExpiryIfOlder(Itemexpiry $expiry, CarbonImmutable $nextExpiry): void
  {
    $stockEntry = $this->getStockEntry($expiry);

    if ($stockEntry === null || $stockEntry->expiryAt === null) {
      return;
    }

    $stockExpiry = CarbonImmutable::parse($stockEntry->expiryAt)->startOfDay();

    if ($stockExpiry->lt($nextExpiry)) {
      $stockEntry->expiryAt = $nextExpiry;
      $stockEntry->save();
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
