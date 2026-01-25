<?php

/**
 * ApiOrderController - controller
 *
 * Controller to handle order-api-endpoint.
 * Check: to determine if something is to order
 * Prepare: Create necessary order-amounts
 * Execute: Execute order for prepared order-items.
 *
 */

namespace App\Http\Controllers;

use App\Mail\OrderMail;
use App\Models\Booking;
use App\Models\Item;
use App\Models\Order;
use App\Services\StatisticService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class ApiOrderController extends Controller
{

  public function create(): JsonResponse
  {

    $runId = (string) \Illuminate\Support\Str::uuid();
    $now = CarbonImmutable::now();

    try
    {

      // compute items
      $items = $this->getItemsNeedingRestock();
      if ($items->isEmpty())
      {
        return response()->json([
          'ok'      => true,
          'run_id'  => $runId,
          'message' => 'no items need restock.',
          'counts'  => [ 'orders_opened' => 0, 'bookings_affected' => 0 ]
        ], 200);
      }

      // date metadata
      $fileDate     = $now->isoFormat('Y-MM-DD');
      $readableDate = $now->isoFormat('DD.MM.YY');
      $mailDateFrom = $now->isoFormat('D. MMM Y');
      $mailDateDue  = $now->addDays(7)->isoFormat('D. MMM Y');

      // group items by demand
      $demands = $items->groupBy(fn ($item) => $item->demand?->name ?? 'Sonstige');

      // create demand PDFs
      $attachments = $this->buildDemandPdfAttachmentsStrict($demands, $fileDate, $readableDate);

      // mail + db update
      $result = DB::transaction(function () use ($items, $now, $attachments, $readableDate, $mailDateFrom, $mailDateDue, $runId) {

        // send mail, fail hard if mail cannot be sent
        $this->sendOrderMailOrFail($attachments, $readableDate, $mailDateFrom, $mailDateDue);

        // db update meta
        $ordersOpened    = 0;
        $bookingsAffected = 0;

        // db update per each item
        foreach ($items as $item)
        {

          $amountDesired = (int) ($item->getAttribute('amount_in_basesize') ?? 0);
          if ($amountDesired <= 0) { continue; }

          // create order
          $order = Order::create([
            'item_id'          => $item->id,
            'order_date'       => $now,
            'amount_desired'   => $amountDesired,
            'amount_delivered' => 0,
            'is_order_open'    => true,
          ]);
          $ordersOpened++;

          // update existing bookings
          $updated = Booking::query()
            ->whereNull('order_id')
            ->where('item_id', $item->id)
            ->where('created_at', '<=', $now)
            ->update(['order_id' => $order->id]);
          $bookingsAffected += (int) $updated;

        }

        return [
          'orders_opened'     => $ordersOpened,
          'bookings_affected' => $bookingsAffected
        ];

      }, 3);

      return response()->json([
        'ok'      => true,
        'run_id'  => $runId,
        'message' => 'Demand mail sent and orders booked.',
        'counts'  => [
          'orders_opened'     => $result['orders_opened'],
          'bookings_affected' => $result['bookings_affected'],
        ],
      ], 200);

    }
    catch (\Throwable $e)
    {

      Log::error('Order.Create endpoint failed.', [
        'run_id'    => $runId,
        'exception' => $e
      ]);

      return response()->json([
        'ok'         => false,
        'run_id'     => $runId,
        'error_code' => $this->classifyError($e),
        'message'    => $e->getMessage()
      ], 500);

    }

  }

  // ####################################################################################

  private function getItemsNeedingRestock(): Collection
  {

    $items = Item::query()
      ->withPending()
      ->with(['ordersize', 'basesize', 'demand'])
      ->whereColumn('current_quantity', '<=', 'min_stock')
      ->get();

    return $items
      ->filter(fn ($item) =>
        (float) $item->pending_quantity <= (float) $item->min_stock &&
        (float) $item->pending_quantity <  (float) $item->max_stock
      )
      ->map(function ($item) {

        $neededForMaxStock = max((float) $item->max_stock - (float) $item->pending_quantity, 0.0);

        $orderSizeAmount = (float) ($item->ordersize?->amount ?? 0);
        if ($orderSizeAmount <= 0) {
          throw new \RuntimeException("Item {$item->id} has no valid ordersize amount.");
        }

        $orderUnits = $neededForMaxStock > 0
          ? max((int) floor($neededForMaxStock / $orderSizeAmount), 1)
          : 0;

        $maxOrderQty = (int) ($item->max_order_quantity ?? 0);
        if ($maxOrderQty > 0) {
          $orderUnits = min($orderUnits, $maxOrderQty);
        }

        $baseAmount = (int) round($orderUnits * $orderSizeAmount);

        $item->setAttribute('amount_neededForMaxStock', $neededForMaxStock);
        $item->setAttribute('amount_in_ordersize', $orderUnits);
        $item->setAttribute('amount_in_basesize', $baseAmount);

        return $item;

      })
      ->filter(fn ($item) => (int) $item->getAttribute('amount_in_basesize') > 0)
      ->values();

  }

  // ####################################################################################

  private function buildDemandPdfAttachmentsStrict(Collection $demands, string $fileDate, string $readableDate): array
  {

    $attachments = [];
    foreach ($demands as $demandName => $demandItems)
    {

      $filename = Str::slug(strtolower($demandName))."_{$fileDate}.pdf";

      // generate PDF
      $pdfBinary = Pdf::loadView('pdf.demand', [
        'filename'     => $filename,
        'demand_title' => $demandName,
        'demand_date'  => $readableDate,
        'items'        => $this->mapItemsForPdf($demandItems),
      ])
      ->setPaper('a4')
      ->output();

      // throw if DomPDF failed
      if (!is_string($pdfBinary) || $pdfBinary === '') {
        throw new \RuntimeException("PDF output empty for demand: {$demandName}");
      }

      // return email friednly
      $attachments[] = [
        'filename' => $filename,
        'data'     => $pdfBinary,
        'mime'     => 'application/pdf',
      ];

    }

    if (empty($attachments)) {
      throw new \RuntimeException('No PDF attachments were generated.');
    }

    return $attachments;

  }

  private function mapItemsForPdf(Collection $items): array
  {
    return $items
      ->sortBy(fn ($item) => Str::lower((string) $item->name))
      ->values()
      ->map(function ($item) {

      $orderUnit = (int) ($item->getAttribute('amount_in_ordersize') ?? 0);
      $baseUnit  = (int) ($item->getAttribute('amount_in_basesize') ?? 0);

      $orderUnitLabel = $item->ordersize?->unit ?? '';
      $baseUnitLabel  = $item->basesize?->unit ?? '';

      $baseText = ($baseUnit > 0 && $baseUnit !== $orderUnit && $baseUnitLabel !== '')
        ? " = {$baseUnit} {$baseUnitLabel}"
        : '';

      $amountText = trim("{$orderUnit} {$orderUnitLabel}") . $baseText;

      return [
        'name'   => (string) $item->name,
        'amount' => trim($amountText),
      ];

    })->all();
  }

  // ####################################################################################

  private function sendOrderMailOrFail(array $attachments, string $readableDate, string $mailDateFrom, string $mailDateDue): void
  {

    $to = config('mail.order_mailer_to_address');
    $cc = config('mail.order_mailer_cc_addresses', []);

    if (empty($to)) {
      throw new \RuntimeException('Mail config missing: mail.order_mailer_to_address');
    }

    try
    {

      // add recipients
      $mailer = Mail::to($to);
      if (!empty($cc)) { $mailer->cc($cc); }

      // send mail
      $mailer->send(new OrderMail(
        $attachments,
        $readableDate,
        $mailDateFrom,
        $mailDateDue)
      );

      // check for errors
      if (method_exists(Mail::getFacadeRoot(), 'failures')) {
        $failures = Mail::failures();
        if (!empty($failures)) {
          throw new \RuntimeException('Mail sending reported failures: ' . implode(', ', $failures));
        }
      }

    }
    catch (\Throwable $e)
    {
      throw new \RuntimeException('Demand mail could not be sent: ' . $e->getMessage(), previous: $e);
    }

  }

  // ####################################################################################

  private function classifyError(\Throwable $e): string
  {
    // Keep this stable for cron parsing.
    $msg = strtolower($e->getMessage());
    if (str_contains($msg, 'mail'))   return 'MAIL_FAILED';
    if (str_contains($msg, 'pdf'))    return 'PDF_FAILED';
    if (str_contains($msg, 'config')) return 'CONFIG_ERROR';
    return 'UNEXPECTED_ERROR';
  }

}
