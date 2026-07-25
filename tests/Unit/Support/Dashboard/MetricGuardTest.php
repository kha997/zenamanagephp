<?php declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricGuard;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Tests\TestCase;

class MetricGuardTest extends TestCase
{
    public function test_wrap_returns_the_closure_result_when_no_exception_is_thrown(): void
    {
        $happy = new MetricResult(
            value: 40.0,
            availability: Availability::AVAILABLE,
            reliability: Reliability::RELIABLE,
            freshness: Freshness::UNKNOWN,
            asOf: null,
            label: 'Tiến độ công việc (Task)',
            explanation: null,
        );

        $result = MetricGuard::wrap('overall_progress', [], 'Tiến độ công việc (Task)', fn () => $happy);

        $this->assertSame($happy, $result);
    }

    public function test_wrap_logs_and_returns_error_metric_result_when_closure_throws(): void
    {
        Log::shouldReceive('error')
            ->once()
            ->with('dashboard_metric_error', \Mockery::on(function (array $context) {
                return $context['widget'] === 'overall_progress'
                    && $context['project_id'] === 'proj-123'
                    && $context['tenant_id'] === 'tenant-456'
                    && array_key_exists('request_id', $context)
                    && $context['exception'] === 'boom'
                    && $context['exception_class'] === RuntimeException::class;
            }));

        $result = MetricGuard::wrap(
            'overall_progress',
            ['project_id' => 'proj-123', 'tenant_id' => 'tenant-456'],
            'Tiến độ công việc (Task)',
            function () {
                throw new RuntimeException('boom');
            },
        );

        $this->assertNull($result->value);
        $this->assertSame(Availability::ERROR, $result->availability);
        $this->assertSame(Reliability::UNKNOWN, $result->reliability);
        $this->assertSame(Freshness::UNKNOWN, $result->freshness);
        $this->assertNull($result->asOf);
        $this->assertSame('Tiến độ công việc (Task)', $result->label);
        $this->assertNotNull($result->explanation);
    }
}
