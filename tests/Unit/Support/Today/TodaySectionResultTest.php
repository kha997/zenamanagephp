<?php declare(strict_types=1);

namespace Tests\Unit\Support\Today;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;
use App\Support\Today\TodaySectionResult;
use PHPUnit\Framework\TestCase;

class TodaySectionResultTest extends TestCase
{
    public function test_available_result_carries_items_and_no_explanation_required(): void
    {
        $result = new TodaySectionResult(
            items: [['name' => 'Việc A']],
            availability: Availability::AVAILABLE,
            reliability: Reliability::RELIABLE,
            explanation: null,
        );

        $this->assertCount(1, $result->items);
        $this->assertSame(Availability::AVAILABLE, $result->availability);
        $this->assertNull($result->explanation);
    }

    public function test_error_result_carries_explanation_and_empty_items(): void
    {
        $result = new TodaySectionResult(
            items: [],
            availability: Availability::ERROR,
            reliability: Reliability::UNKNOWN,
            explanation: 'Không thể tải mục này lúc này.',
        );

        $this->assertSame([], $result->items);
        $this->assertSame(Availability::ERROR, $result->availability);
        $this->assertSame('Không thể tải mục này lúc này.', $result->explanation);
    }
}
