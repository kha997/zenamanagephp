<?php declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\MetricResult;
use App\Support\Dashboard\Reliability;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class MetricResultTest extends TestCase
{
    public function test_to_array_serializes_enums_to_their_string_value_and_carbon_to_iso8601(): void
    {
        $asOf = Carbon::create(2026, 7, 25, 9, 14, 0, 'Asia/Ho_Chi_Minh');

        $result = new MetricResult(
            value: 40.0,
            availability: Availability::AVAILABLE,
            reliability: Reliability::RELIABLE,
            freshness: Freshness::UNKNOWN,
            asOf: $asOf,
            label: 'Tiến độ công việc (Task)',
            explanation: null,
        );

        $this->assertSame([
            'value' => 40.0,
            'availability' => 'AVAILABLE',
            'reliability' => 'RELIABLE',
            'freshness' => 'UNKNOWN',
            'as_of' => $asOf->toIso8601String(),
            'label' => 'Tiến độ công việc (Task)',
            'explanation' => null,
        ], $result->toArray());
    }

    public function test_to_array_serializes_null_value_and_null_as_of(): void
    {
        $result = new MetricResult(
            value: null,
            availability: Availability::NO_DATA,
            reliability: Reliability::RELIABLE,
            freshness: Freshness::UNKNOWN,
            asOf: null,
            label: 'Tiến độ công việc (Task)',
            explanation: 'Dự án chưa có công việc (Task) nào được tạo.',
        );

        $this->assertNull($result->toArray()['value']);
        $this->assertNull($result->toArray()['as_of']);
        $this->assertSame('NO_DATA', $result->toArray()['availability']);
        $this->assertSame('Dự án chưa có công việc (Task) nào được tạo.', $result->toArray()['explanation']);
    }
}
