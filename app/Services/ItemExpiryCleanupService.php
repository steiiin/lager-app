<?php

namespace App\Services;

use App\Models\Itemexpiry;

class ItemExpiryCleanupService
{

  public function cleanupItem(int $itemId): void
  {
    $stockEntry = Itemexpiry::query()
      ->where('item_id', $itemId)
      ->whereNull('usage_id')
      ->where('status', 'reserved')
      ->where('expiryQuantity', '>', 0)
      ->whereNotNull('expiryAt')
      ->orderBy('expiryAt')
      ->first();

    if ($stockEntry === null) {
      return;
    }

    Itemexpiry::query()
      ->where('item_id', $itemId)
      ->whereNotNull('usage_id')
      ->where('status', 'reserved')
      ->where('expiryQuantity', '>', 0)
      ->whereNotNull('expiryAt')
      ->whereDate('expiryAt', '>=', $stockEntry->expiryAt->toDateString())
      ->delete();
  }

}
