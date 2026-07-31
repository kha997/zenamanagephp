<?php declare(strict_types=1);

namespace Tests\Unit\Support\Dashboard;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Freshness;
use App\Support\Dashboard\Reliability;
use PHPUnit\Framework\TestCase;

class DashboardEnumsTest extends TestCase
{
    public function test_availability_has_exactly_four_string_backed_cases(): void
    {
        $this->assertSame('AVAILABLE', Availability::AVAILABLE->value);
        $this->assertSame('NO_DATA', Availability::NO_DATA->value);
        $this->assertSame('NOT_APPLICABLE', Availability::NOT_APPLICABLE->value);
        $this->assertSame('ERROR', Availability::ERROR->value);
        $this->assertCount(4, Availability::cases());
    }

    public function test_reliability_has_exactly_four_string_backed_cases(): void
    {
        $this->assertSame('RELIABLE', Reliability::RELIABLE->value);
        $this->assertSame('LIMITED', Reliability::LIMITED->value);
        $this->assertSame('LEGACY', Reliability::LEGACY->value);
        $this->assertSame('UNKNOWN', Reliability::UNKNOWN->value);
        $this->assertCount(4, Reliability::cases());
    }

    public function test_freshness_has_exactly_three_string_backed_cases(): void
    {
        $this->assertSame('CURRENT', Freshness::CURRENT->value);
        $this->assertSame('STALE', Freshness::STALE->value);
        $this->assertSame('UNKNOWN', Freshness::UNKNOWN->value);
        $this->assertCount(3, Freshness::cases());
    }
}
