<?php declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\ServiceLine;
use App\Support\ServiceLineProvenance;
use PHPUnit\Framework\TestCase;

class ServiceLineTest extends TestCase
{
    public function test_service_line_values_are_exactly_the_canonical_three(): void
    {
        $this->assertSame(['DESIGN', 'CONSTRUCTION', 'INSPECTION'], ServiceLine::VALUES);
    }

    public function test_service_line_does_not_include_unknown_or_needs_review(): void
    {
        $this->assertNotContains('UNKNOWN', ServiceLine::VALUES);
        $this->assertNotContains('NEEDS_REVIEW', ServiceLine::VALUES);
    }

    public function test_provenance_values_are_exactly_the_canonical_four(): void
    {
        $this->assertSame(
            ['CONFIRMED', 'INFERRED', 'NEEDS_REVIEW', 'UNKNOWN'],
            ServiceLineProvenance::VALUES
        );
    }
}
