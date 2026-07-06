<?php

namespace Tests\Feature;

use App\Models\Item;
use App\Models\Itemexpiry;
use App\Models\Itemsize;
use App\Models\Usage;
use App\Services\ExpiryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ExpiryServiceTest extends TestCase
{
  use RefreshDatabase;

  protected function tearDown(): void
  {
    CarbonImmutable::setTestNow();

    parent::tearDown();
  }

  public function test_generate_includes_expiries_within_next_30_days(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem(['current_quantity' => 10]);

    $this->createExpiry($item, $usage, '2026-07-01');
    $this->createExpiry($item, $usage, '2026-07-11');
    $this->createExpiry($item, $usage, '2026-07-31');
    $this->createExpiry($item, $usage, '2026-08-01');

    $data = app(ExpiryService::class)->generate();

    $dates = collect($data)
      ->flatMap(fn($usageGroup) => collect($usageGroup['dates'])->pluck('expiry_date'))
      ->values()
      ->all();

    $this->assertSame(['2026-07-01', '2026-07-11', '2026-07-31'], $dates);
  }

  public function test_expiry_within_next_10_days_is_red_and_abgelaufen(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem(['current_quantity' => 10]);

    $usageExpiry = $this->createExpiry($item, $usage, '2026-07-11');

    $row = $this->findExpiryRow(app(ExpiryService::class)->generate(), $usageExpiry->id);

    $this->assertSame('red', $row['state']);
    $this->assertSame('Abgelaufen', $row['state_label']);
  }

  public function test_expiry_after_10_days_and_within_30_days_is_yellow_and_laeuft_bald_ab(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem(['current_quantity' => 10]);

    $eleventhDayExpiry = $this->createExpiry($item, $usage, '2026-07-12');
    $thirtiethDayExpiry = $this->createExpiry($item, $usage, '2026-07-31');

    $data = app(ExpiryService::class)->generate();
    $eleventhDayRow = $this->findExpiryRow($data, $eleventhDayExpiry->id);
    $thirtiethDayRow = $this->findExpiryRow($data, $thirtiethDayExpiry->id);

    $this->assertSame('yellow', $eleventhDayRow['state']);
    $this->assertSame('Läuft bald ab', $eleventhDayRow['state_label']);
    $this->assertSame('yellow', $thirtiethDayRow['state']);
    $this->assertSame('Läuft bald ab', $thirtiethDayRow['state_label']);
  }

  public function test_is_ordered_is_included_and_does_not_affect_calendar_state(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem(['current_quantity' => 10]);

    $usageExpiry = $this->createExpiry($item, $usage, '2026-07-31', ['is_ordered' => true]);

    $row = $this->findExpiryRow(app(ExpiryService::class)->generate(), $usageExpiry->id);

    $this->assertSame('yellow', $row['state']);
    $this->assertTrue($row['is_ordered']);
  }

  public function test_later_usage_expiry_does_not_suppress_stock_row(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $handledUsage = $this->createUsage(['name' => 'Handled']);
    $openUsage = $this->createUsage(['name' => 'Open']);
    $item = $this->createItem(['current_quantity' => 10]);

    $stockExpiry = $this->createExpiry($item, null, '2026-07-01');
    $this->createExpiry($item, $handledUsage, '2026-07-31');

    $stockRows = collect(app(ExpiryService::class)->generate())
      ->flatMap(fn($usageGroup) => collect($usageGroup['dates'])
        ->flatMap(fn($dateGroup) => collect($dateGroup['items'])
          ->where('expiry_entry_id', $stockExpiry->id)
          ->map(fn($row) => [
            'usage_group_id' => $usageGroup['usage_id'],
            'row' => $row,
          ])))
      ->values();

    $this->assertCount(2, $stockRows);
    $this->assertSame([$handledUsage->id, $openUsage->id], $stockRows->pluck('usage_group_id')->sort()->values()->all());
  }

  public function test_earlier_usage_expiry_suppresses_stock_row_only_for_that_usage(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $handledUsage = $this->createUsage(['name' => 'Handled']);
    $openUsage = $this->createUsage(['name' => 'Open']);
    $item = $this->createItem(['current_quantity' => 10]);

    $stockExpiry = $this->createExpiry($item, null, '2026-07-31');
    $this->createExpiry($item, $handledUsage, '2026-07-11');

    $stockRows = collect(app(ExpiryService::class)->generate())
      ->flatMap(fn($usageGroup) => collect($usageGroup['dates'])
        ->flatMap(fn($dateGroup) => collect($dateGroup['items'])
          ->where('expiry_entry_id', $stockExpiry->id)
          ->map(fn($row) => [
            'usage_group_id' => $usageGroup['usage_id'],
            'row' => $row,
          ])))
      ->values();

    $this->assertCount(1, $stockRows);
    $this->assertSame($openUsage->id, $stockRows->first()['usage_group_id']);
  }

  public function test_usage_expiry_uses_stock_note_when_usage_note_is_empty(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem(['current_quantity' => 10]);

    $this->createExpiry($item, null, '2026-07-31', ['note' => 'Stock note']);
    $usageExpiry = $this->createExpiry($item, $usage, '2026-07-12');

    $row = $this->findExpiryRow(app(ExpiryService::class)->generate(), $usageExpiry->id);

    $this->assertSame('Stock note', $row['note']);
  }

  public function test_usage_expiry_keeps_own_note_over_stock_note(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem(['current_quantity' => 10]);

    $this->createExpiry($item, null, '2026-07-31', ['note' => 'Stock note']);
    $usageExpiry = $this->createExpiry($item, $usage, '2026-07-12', ['note' => 'Usage note']);

    $row = $this->findExpiryRow(app(ExpiryService::class)->generate(), $usageExpiry->id);

    $this->assertSame('Usage note', $row['note']);
  }

  public function test_inventory_expiry_is_empty_without_stock_expiry(): void
  {
    CarbonImmutable::setTestNow('2026-07-01');

    $usage = $this->createUsage();
    $item = $this->createItem([
      'current_quantity' => 10,
    ]);

    $usageExpiry = $this->createExpiry($item, $usage, '2026-07-12');

    $row = $this->findExpiryRow(app(ExpiryService::class)->generate(), $usageExpiry->id);

    $this->assertNull($row['inventory_expiry']);
    $this->assertSame('Kein MHD', $row['inventory_expiry_label']);
  }

  private function createUsage(array $attributes = []): Usage
  {
    return Usage::create([
      'name' => $attributes['name'] ?? 'Station',
      'could_expire' => $attributes['could_expire'] ?? true,
    ]);
  }

  private function createItem(array $attributes = []): Item
  {
    $demandId = DB::table('demands')->insertGetId([
      'name' => $attributes['demand_name'] ?? 'Default',
      'sp_name' => $attributes['demand_name'] ?? 'Default',
    ]);

    $item = Item::create([
      'name' => $attributes['name'] ?? 'Test item',
      'demand_id' => $demandId,
      'location' => $attributes['location'] ?? [],
      'min_stock' => $attributes['min_stock'] ?? 0,
      'max_stock' => $attributes['max_stock'] ?? 0,
      'current_quantity' => $attributes['current_quantity'] ?? 0,
    ]);

    Itemsize::create([
      'item_id' => $item->id,
      'unit' => 'Stk',
      'amount' => 1,
      'is_default' => true,
    ]);

    return $item;
  }

  private function createExpiry(Item $item, ?Usage $usage, string $expiryAt, array $attributes = []): Itemexpiry
  {
    return Itemexpiry::create([
      'item_id' => $item->id,
      'usage_id' => $usage?->id,
      'expiryAt' => $expiryAt,
      'expiryQuantity' => $attributes['expiryQuantity'] ?? 1,
      'status' => $attributes['status'] ?? 'reserved',
      'is_ordered' => $attributes['is_ordered'] ?? false,
      'note' => $attributes['note'] ?? null,
    ]);
  }

  private function findExpiryRow(array $data, int $entryId): array
  {
    return collect($data)
      ->flatMap(fn($usageGroup) => $usageGroup['dates'])
      ->flatMap(fn($dateGroup) => $dateGroup['items'])
      ->firstWhere('expiry_entry_id', $entryId);
  }
}
