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
    $currentMonthEnd = $today->endOfMonth();
    $visibleUntil = $today->startOfMonth()->addMonthsNoOverflow(2)->endOfMonth();
    $items = Item::query()
      ->with(['basesize', 'expiryEntries.usage'])
      ->get();

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

      if ($stockEntry !== null && $this->isVisibleExpiry($stockEntry, $visibleUntil)) {
        foreach ($expiringUsages as $usage) {
          if (!$this->hasActiveUsageExpiry($entries, $usage->id)) {
            $this->addStockExpiryGroup($groups, $item, $usage, $stockEntry, $stock, $currentMonthEnd, $visibleUntil);
          }
        }
      }

      foreach ($usageEntries as $entry) {
        $usageId = $entry->usage_id;
        $usageName = $entry->usage?->name ?? 'Unbekannt';
        $date = $entry->expiryAt->toDateString();
        $state = $this->getEntryState($entry, $currentMonthEnd, $visibleUntil);

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
          'is_ordered' => (bool) $entry->is_ordered,
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

  private function addStockExpiryGroup(array &$groups, Item $item, Usage $usage, $stockEntry, array $stock, CarbonImmutable $currentMonthEnd, CarbonImmutable $visibleUntil): void
  {
    $usageId = $usage->id;
    $date = $stockEntry->expiryAt->toDateString();
    $state = $this->getEntryState($stockEntry, $currentMonthEnd, $visibleUntil);

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
      'state' => $state,
      'state_label' => $this->getStateLabel($state),
      'note' => $stockEntry->note,
      'status' => $stockEntry->status,
      'is_ordered' => (bool) $stockEntry->is_ordered,
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

  private function getEntryState($entry, CarbonImmutable $currentMonthEnd, CarbonImmutable $visibleUntil): string
  {
    if ($entry->expiryAt->lte($currentMonthEnd)) {
      return 'red';
    }
    if ($entry->expiryAt->lte($visibleUntil)) {
      return 'yellow';
    }
    return 'green';
  }

  private function getStateLabel(string $state): string
  {
    return match ($state) {
      'red' => 'Abgelaufen',
      'green' => 'OK',
      default => 'Läuft bald ab',
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
