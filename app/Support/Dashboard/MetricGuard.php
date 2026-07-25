<?php declare(strict_types=1);

namespace App\Support\Dashboard;

use App\Services\ErrorEnvelopeService;
use Illuminate\Support\Facades\Log;
use Throwable;

final class MetricGuard
{
    /**
     * @param array<string, mixed> $logContext
     */
    public static function wrap(string $widget, array $logContext, string $label, \Closure $compute): MetricResult
    {
        try {
            return $compute();
        } catch (Throwable $e) {
            Log::error('dashboard_metric_error', array_merge($logContext, [
                'widget' => $widget,
                'request_id' => ErrorEnvelopeService::getCurrentRequestId(),
                'exception' => $e->getMessage(),
                'exception_class' => $e::class,
            ]));

            return new MetricResult(
                value: null,
                availability: Availability::ERROR,
                reliability: Reliability::UNKNOWN,
                freshness: Freshness::UNKNOWN,
                asOf: null,
                label: $label,
                explanation: "Không thể tính được \"{$label}\" do lỗi truy vấn dữ liệu.",
            );
        }
    }
}
