<?php

namespace App\Services;

use App\Models\Item;
use App\Models\Usage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class ExpiryService
{

  public function generate(): array
  {
    $today = CarbonImmutable::today();
    $visibleUntil = $today->addMonthsNoOverflow(2)->endOfDay();
    $items = Item::query()
      ->with(['basesize', 'expiryEntries.usage'])
      ->get();

    $usageTotals = $this->getUsageTotals($items);
    $expiringUsages = Usage::query()
      ->where('could_expire', true)
      ->orderBy('name')
      ->get(['id', 'name']);
    $groups = [];

    foreach ($items as $item) {
      $entries = $item->expiryEntries
        ->filter(fn($entry) => $this->isActiveExpiry($entry));
      $visibleEntries = $entries
        ->filter(fn($entry) => $this->isVisibleExpiry($entry, $visibleUntil));

      $stockEntry = $entries
        ->whereNull('usage_id')
        ->sortBy('expiryAt')
        ->first();
      $usageEntries = $visibleEntries->whereNotNull('usage_id');

      $stock = $this->getStockState($item, $stockEntry);

      if ($stockEntry !== null && $this->isVisibleExpiry($stockEntry, $visibleUntil) && $stockEntry->expiryAt->lte($today)) {
        foreach ($expiringUsages as $usage) {
          if (!$this->hasActiveUsageExpiry($entries, $usage->id)) {
            $this->addStockExpiryGroup($groups, $item, $usage, $stockEntry, $stock);
          }
        }
      }

      foreach ($usageEntries as $entry) {
        $usageId = $entry->usage_id;
        $usageName = $entry->usage?->name ?? 'Unbekannt';
        $date = $entry->expiryAt->toDateString();
        $totalUsageAmount = $usageTotals[$item->id][$date] ?? 0;
        $state = $this->getEntryState($entry, $stock, $totalUsageAmount);

        $groups[$usageId] ??= [
          'usage_id' => $usageId,
          'usage_name' => $usageName,
          'dates' => [],
        ];

        $groups[$usageId]['dates'][$date] ??= [
          'expiry_date' => $date,
          'expiry_label' => $this->formatExpiry($entry->expiryAt),
          'items' => [],
        ];

        $groups[$usageId]['dates'][$date]['items'][] = [
          'expiry_entry_id' => $entry->id,
          'row_type' => 'usage',
          'item_id' => $item->id,
          'item_name' => $item->name,
          'usage_id' => $entry->usage_id,
          'expiry_date' => $date,
          'amount' => (int) $entry->expiryQuantity,
          'unit' => $item->basesize?->unit ?? 'Stk',
          'inventory_amount' => $stock['amount'],
          'inventory_expiry' => $stock['expiry']?->toDateString(),
          'inventory_expiry_label' => $this->formatExpiry($stock['expiry']),
          'state' => $state,
          'state_label' => $this->getStateLabel($state),
          'note' => $entry->note ?: $stockEntry?->note,
          'status' => $entry->status,
        ];
      }
    }

    return collect($groups)
      ->sortBy('usage_name', SORT_NATURAL | SORT_FLAG_CASE)
      ->map(function ($usage) {
        $usage['dates'] = collect($usage['dates'])
          ->sortBy('expiry_date')
          ->map(function ($dateGroup) {
            $dateGroup['items'] = collect($dateGroup['items'])
              ->sortBy('item_name', SORT_NATURAL | SORT_FLAG_CASE)
              ->values()
              ->all();

            return $dateGroup;
          })
          ->values()
          ->all();

        return $usage;
      })
      ->values()
      ->all();
  }

  private function getUsageTotals(Collection $items): array
  {
    $totals = [];

    foreach ($items as $item) {
      foreach ($item->expiryEntries as $entry) {
        if (!$this->isActiveExpiry($entry) || $entry->usage_id === null) {
          continue;
        }

        $date = $entry->expiryAt->toDateString();
        $totals[$item->id][$date] ??= 0;
        $totals[$item->id][$date] += (int) $entry->expiryQuantity;
      }
    }

    return $totals;
  }

  private function addStockExpiryGroup(array &$groups, Item $item, Usage $usage, $stockEntry, array $stock): void
  {
    $usageId = $usage->id;
    $date = $stockEntry->expiryAt->toDateString();

    $groups[$usageId] ??= [
      'usage_id' => $usageId,
      'usage_name' => $usage->name,
      'dates' => [],
    ];

    $groups[$usageId]['dates'][$date] ??= [
      'expiry_date' => $date,
      'expiry_label' => $this->formatExpiry($stockEntry->expiryAt),
      'items' => [],
    ];

    $groups[$usageId]['dates'][$date]['items'][] = [
      'expiry_entry_id' => $stockEntry->id,
      'row_type' => 'stock',
      'item_id' => $item->id,
      'item_name' => $item->name,
      'usage_id' => null,
      'expiry_date' => $date,
      'amount' => $stock['amount'],
      'unit' => $item->basesize?->unit ?? 'Stk',
      'inventory_amount' => $stock['amount'],
      'inventory_expiry' => $stock['expiry']?->toDateString(),
      'inventory_expiry_label' => $this->formatExpiry($stock['expiry']),
      'state' => 'red',
      'state_label' => 'Bestellt',
      'note' => $stockEntry->note,
      'status' => $stockEntry->status,
    ];
  }

  private function hasActiveUsageExpiry(Collection $entries, int $usageId): bool
  {
    return $entries->contains(fn($entry) => (int) $entry->usage_id === $usageId);
  }

  private function isActiveExpiry($entry): bool
  {
    return $entry->status === 'reserved'
      && $entry->expiryAt !== null
      && (int) $entry->expiryQuantity > 0;
  }

  private function isVisibleExpiry($entry, CarbonImmutable $visibleUntil): bool
  {
    return $entry->expiryAt->lte($visibleUntil);
  }

  private function getStockState(Item $item, $stockEntry): array
  {
    $currentAmount = (int) ($item->current_quantity ?? 0);

    return [
      'amount' => max(0, $currentAmount),
      'expiry' => $stockEntry?->expiryAt
        ?? ($item->current_expiry ? CarbonImmutable::parse($item->current_expiry) : null),
    ];
  }

  private function getEntryState($entry, array $stock, int $totalUsageAmount): string
  {
    if ($stock['amount'] <= 0 || $stock['expiry'] === null) {
      return 'red';
    }
    if ($stock['expiry']->lte($entry->expiryAt)) {
      return $stock['expiry']->lte(CarbonImmutable::today()->addDays(30))
        ? 'red'
        : 'yellow';
    }
    if ($totalUsageAmount < $stock['amount']) {
      return 'green';
    }
    return 'yellow';
  }

  private function getStateLabel(string $state): string
  {
    return match ($state) {
      'red' => 'Bestellt',
      'green' => 'Im Lager',
      default => 'Zu Wenig',
    };
  }

  private function formatExpiry($date): string
  {
    if (!$date) {
      return 'Kein MHD';
    }

    return $date->format('m-Y');
  }

}
