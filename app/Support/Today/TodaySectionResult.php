<?php declare(strict_types=1);

namespace App\Support\Today;

use App\Support\Dashboard\Availability;
use App\Support\Dashboard\Reliability;

final class TodaySectionResult
{
    /**
     * @param array<int, mixed> $items
     */
    public function __construct(
        public readonly array $items,
        public readonly Availability $availability,
        public readonly Reliability $reliability,
        public readonly ?string $explanation,
    ) {
    }
}
