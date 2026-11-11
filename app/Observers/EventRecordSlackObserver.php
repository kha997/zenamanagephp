<?php

declare(strict_types=1);

namespace App\Observers;

use App\Jobs\SendSlackMessage;
use App\Models\EventRecord;
use App\Models\PaymentCertificate;
use App\Models\Submittal;
use Illuminate\Support\Facades\Log;

/**
 * GAP-124 (Gate 2 E-1, Owner amendment 3) — business events whose source is an EventRecord: submittal submitted /
 * resubmitted / approved / rejected (SubmittalLifecycleService) and payment certificate approved (ContractPageController).
 * Each record is one business event, so it is posted once (identity event_record:<id>). Nothing here may break the
 * action that wrote the record.
 */
class EventRecordSlackObserver
{
    private const SUBMITTAL_EVENTS = [
        'submittal.submitted' => 'đã nộp',
        'submittal.resubmitted' => 'đã nộp lại',
        'submittal.approved' => 'được duyệt',
        'submittal.rejected' => 'bị từ chối',
    ];

    public function created(EventRecord $record): void
    {
        try {
            $eventKey = (string) $record->getAttribute('event_key');
            $text = match (true) {
                isset(self::SUBMITTAL_EVENTS[$eventKey]) => $this->submittal($record, self::SUBMITTAL_EVENTS[$eventKey]),
                $eventKey === 'payment_certificate.approved' => $this->paymentCertificate($record),
                default => null,
            };
            if ($text !== null) {
                SendSlackMessage::dispatch((string) $record->getAttribute('tenant_id'), 'event_record:' . $record->getKey(), $text)->afterCommit();
            }
        } catch (\Throwable $e) {
            Log::warning('slack.event_record_failed', ['event_record_id' => (string) $record->getKey(), 'error' => $e->getMessage()]);
        }
    }

    private function submittal(EventRecord $record, string $verb): ?string
    {
        $id = (string) $record->getAttribute('aggregate_id');
        $submittal = Submittal::query()->withoutGlobalScopes()->whereKey($id)->first();
        if (!$submittal instanceof Submittal) {
            return null;
        }
        $label = trim($submittal->getAttribute('submittal_number') . ' – ' . $submittal->getAttribute('title'), ' –');

        return "*Hồ sơ trình duyệt {$label} {$verb}*\n<" . route('operator.submittals.show', $id) . '|Mở trong Zena>';
    }

    private function paymentCertificate(EventRecord $record): ?string
    {
        $certificate = PaymentCertificate::query()->withoutGlobalScopes()->whereKey((string) $record->getAttribute('aggregate_id'))->first();
        if (!$certificate instanceof PaymentCertificate) {
            return null;
        }
        $net = (float) $certificate->getAttribute('net_payable');

        return '*Chứng chỉ thanh toán kỳ ' . $certificate->getAttribute('period_no') . ' đã duyệt*'
            . "\nThực trả: " . number_format($net, 0, ',', '.') . ' đ'
            . "\n<" . route('operator.contracts.show', (string) $certificate->getAttribute('contract_id')) . '|Mở trong Zena>';
    }
}
