<?php

namespace App\Services\Agri;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\User;
use App\Services\Stock\DeliveryReceiver;
use App\Support\CurrentCompany;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Turns a purchase order's lines into stock, through the same
 * `DeliveryReceiver` an ordinary delivery uses — a PO receipt is a delivery
 * that happens to know what it was ordered against, not a second path into
 * the ledger. `reference_type`/`reference_id` point the resulting stock
 * movements at this PurchaseOrder so they can be traced back to it.
 */
class PurchaseOrderReceiver
{
    public function __construct(protected DeliveryReceiver $delivery) {}

    /**
     * @param  array<int, array{purchase_order_line_id: string, quantity: float|string,
     *               batch_number?: ?string, expires_on?: ?string}>  $lines
     * @param  array{received_on?: ?string, location_id?: ?string, record_expense?: bool,
     *               vat_rate?: float, payment_method?: ?string, due_date?: ?string}  $options
     */
    public function receive(PurchaseOrder $po, array $lines, array $options, ?User $actor = null): PurchaseOrder
    {
        if (! in_array($po->status, ['issued', 'partially_received'], true)) {
            throw new RuntimeException('Only an issued purchase order can receive stock.');
        }

        $po->loadMissing('lines');

        $usable = [];

        foreach ($lines as $line) {
            $quantity = round((float) ($line['quantity'] ?? 0), 3);

            if (($line['purchase_order_line_id'] ?? null) === null || $quantity <= 0) {
                continue;
            }

            $poLine = $po->lines->firstWhere('id', $line['purchase_order_line_id']);

            if ($poLine === null || $poLine->item_id === null) {
                continue;
            }

            $remaining = round((float) $poLine->quantity - (float) $poLine->quantity_received, 3);

            if ($remaining <= 0) {
                continue;
            }

            $usable[] = [
                'line' => $poLine,
                'quantity' => min($quantity, $remaining),
                'batch_number' => $line['batch_number'] ?? null,
                'expires_on' => $line['expires_on'] ?? null,
            ];
        }

        if ($usable === []) {
            throw new RuntimeException('Nothing on this receipt matches an outstanding line on the purchase order.');
        }

        return DB::transaction(function () use ($po, $usable, $options, $actor) {
            $company = app(CurrentCompany::class)->get();

            $this->delivery->receive(
                $company,
                collect($usable)->map(fn (array $line) => [
                    'item_id' => $line['line']->item_id,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['line']->unit_cost,
                    'batch_number' => $line['batch_number'],
                    'expires_on' => $line['expires_on'],
                ])->all(),
                [
                    ...$options,
                    'supplier_id' => $po->supplier_id,
                    'reference' => $po->number,
                    'reference_type' => PurchaseOrder::class,
                    'reference_id' => $po->id,
                ],
                $actor,
            );

            foreach ($usable as $line) {
                /** @var PurchaseOrderLine $poLine */
                $poLine = $line['line'];
                $poLine->forceFill([
                    'quantity_received' => round((float) $poLine->quantity_received + $line['quantity'], 3),
                ])->save();
            }

            $po->recalculateTotals();
            $po->refresh()->loadMissing('lines');

            $po->forceFill([
                'status' => $po->lines->every(fn (PurchaseOrderLine $l) => $l->isFullyReceived())
                    ? 'received'
                    : 'partially_received',
            ])->save();

            return $po;
        });
    }
}
