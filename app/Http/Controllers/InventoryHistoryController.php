<?php

namespace App\Http\Controllers;

use App\Models\Item;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryHistoryController extends Controller
{
  private const PER_PAGE = 50;

  private const INTERNAL_PROCESSES = [
    -1 => 'Abweichung',
    -2 => 'Rückbuchung',
    -3 => 'Verfall',
  ];

  public function index(Item $item): JsonResponse
  {
    $bookings = DB::table('bookings as booking')
      ->leftJoin('usages as item_usage', 'item_usage.id', '=', 'booking.usage_id')
      ->where('booking.item_id', $item->id)
      ->selectRaw("'booking' as source")
      ->selectRaw('booking.id as source_id')
      ->selectRaw("CASE WHEN booking.usage_id = -4 THEN 'bookin' ELSE 'bookout' END as type")
      ->selectRaw('booking.created_at as occurred_at')
      ->selectRaw("'datetime' as occurred_at_precision")
      ->selectRaw('booking.item_amount as amount')
      ->selectRaw('booking.usage_id as usage_id')
      ->selectRaw('item_usage.name as usage_name')
      ->selectRaw('1 as source_sort');

    $orders = DB::table('orders as item_order')
      ->where('item_order.item_id', $item->id)
      ->selectRaw("'order' as source")
      ->selectRaw('item_order.id as source_id')
      ->selectRaw("'order' as type")
      ->selectRaw('item_order.order_date as occurred_at')
      ->selectRaw("'date' as occurred_at_precision")
      ->selectRaw('item_order.amount_desired as amount')
      ->selectRaw('NULL as usage_id')
      ->selectRaw('NULL as usage_name')
      ->selectRaw('0 as source_sort');

    $history = DB::query()
      ->fromSub($bookings->unionAll($orders), 'item_history')
      ->orderByDesc('occurred_at')
      ->orderByDesc('source_sort')
      ->orderByDesc('source_id')
      ->paginate(self::PER_PAGE)
      ->through(fn ($event) => $this->mapEvent($event));

    return response()->json($history);
  }

  private function mapEvent(object $event): array
  {
    $precision = (string) $event->occurred_at_precision;

    return [
      'key' => "{$event->source}:{$event->source_id}",
      'type' => (string) $event->type,
      'amount' => (int) $event->amount,
      'occurred_at' => $this->formatOccurredAt((string) $event->occurred_at, $precision),
      'occurred_at_precision' => $precision,
      'context' => $event->type === 'bookout'
        ? $this->mapBookoutContext((int) $event->usage_id, $event->usage_name)
        : null,
    ];
  }

  private function formatOccurredAt(string $occurredAt, string $precision): string
  {
    if ($occurredAt === '') {
      return '';
    }

    if ($precision === 'date') {
      return substr($occurredAt, 0, 10);
    }

    return CarbonImmutable::parse($occurredAt)->toIso8601String();
  }

  private function mapBookoutContext(int $usageId, ?string $usageName): array
  {
    if ($usageId >= 0) {
      return [
        'kind' => 'usage',
        'label' => $usageName ?: 'Unbekannte Verwendung',
      ];
    }

    return [
      'kind' => 'internal',
      'label' => self::INTERNAL_PROCESSES[$usageId] ?? 'Interner Vorgang',
    ];
  }
}
