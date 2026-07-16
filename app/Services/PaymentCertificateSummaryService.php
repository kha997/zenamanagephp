<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoqLineItem;
use App\Models\PaymentCertificate;
use App\Models\PaymentCertificateLine;

final class PaymentCertificateSummaryService
{
    /**
     * Compute per-line summaries for a certificate.
     *
     * @return array<string, array{contract_qty: float, unit_price: float|null, prev_qty: float, this_qty: float, remaining_qty: float, percent_done: float, over_quantity: bool, amount_this_period: float}>
     *         keyed by boq_line_item_id
     */
    public function lineSummaries(PaymentCertificate $certificate): array
    {
        $contract = $certificate->contract;
        $boq = $contract->boq;

        if (! $boq) {
            return [];
        }

        // 1. All BOQ line items for this contract's BOQ
        $boqLines = BoqLineItem::query()
            ->where('boq_id', $boq->id)
            ->get()
            ->keyBy('id');

        // 2. All APPROVED certificates for same contract with period_no < this one
        $approvedCertIds = PaymentCertificate::query()
            ->where('contract_id', $contract->id)
            ->where('status', PaymentCertificate::STATUS_APPROVED)
            ->where('period_no', '<', $certificate->period_no)
            ->pluck('id');

        // 3. All lines from approved previous certificates, grouped by boq_line_item_id
        /** @var array<string, float> $prevQtyByItem */
        $prevQtyByItem = [];
        if ($approvedCertIds->isNotEmpty()) {
            $prevLines = PaymentCertificateLine::query()
                ->whereIn('payment_certificate_id', $approvedCertIds)
                ->get();

            foreach ($prevLines as $line) {
                $itemId = (string) $line->boq_line_item_id;
                $prevQtyByItem[$itemId] = ($prevQtyByItem[$itemId] ?? 0.0) + $line->qty_this_period;
            }
        }

        // 4. Lines from the current certificate, grouped by boq_line_item_id
        /** @var array<string, array{qty: float, amount: float}> $thisQtyByItem */
        $thisQtyByItem = [];
        $currentLines = $certificate->lines;
        foreach ($currentLines as $line) {
            $itemId = (string) $line->boq_line_item_id;
            $thisQtyByItem[$itemId] = [
                'qty' => ($thisQtyByItem[$itemId]['qty'] ?? 0.0) + $line->qty_this_period,
                'amount' => ($thisQtyByItem[$itemId]['amount'] ?? 0.0) + $line->amount_this_period,
            ];
        }

        // 5. Build summaries for every BOQ line
        /** @var array<string, array{contract_qty: float, unit_price: float|null, prev_qty: float, this_qty: float, remaining_qty: float, percent_done: float, over_quantity: bool, amount_this_period: float}> */
        $result = [];

        foreach ($boqLines as $item) {
            $id = (string) $item->id;
            $contractQty = (float) $item->quantity;
            $unitPrice = $item->unit_price !== null ? (float) $item->unit_price : null;
            $prevQty = $prevQtyByItem[$id] ?? 0.0;
            $thisQty = $thisQtyByItem[$id]['qty'] ?? 0.0;
            $thisAmount = $thisQtyByItem[$id]['amount'] ?? 0.0;
            $totalQty = $prevQty + $thisQty;
            $remainingQty = $contractQty - $totalQty;
            $percentDone = $contractQty > 0 ? round($totalQty / $contractQty * 100, 2) : 0.0;
            $overQuantity = $contractQty > 0 && $totalQty > $contractQty;

            $result[$id] = [
                'contract_qty' => $contractQty,
                'unit_price' => $unitPrice,
                'prev_qty' => $prevQty,
                'this_qty' => $thisQty,
                'remaining_qty' => $remainingQty,
                'percent_done' => $percentDone,
                'over_quantity' => $overQuantity,
                'amount_this_period' => $thisAmount,
            ];
        }

        return $result;
    }
}
