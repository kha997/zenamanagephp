<?php declare(strict_types=1);

namespace App\Support\Dashboard;

use Carbon\Carbon;

final class MetricResult
{
    public function __construct(
        public readonly mixed $value,
        public readonly Availability $availability,
        public readonly Reliability $reliability,
        public readonly Freshness $freshness,
        public readonly ?Carbon $asOf,
        public readonly string $label,
        public readonly ?string $explanation,
    ) {
    }

    /**
     * @return array{value: mixed, availability: string, reliability: string, freshness: string, as_of: string|null, label: string, explanation: string|null}
     */
    public function toArray(): array
    {
        return [
            'value' => $this->value,
            'availability' => $this->availability->value,
            'reliability' => $this->reliability->value,
            'freshness' => $this->freshness->value,
            'as_of' => $this->asOf?->toIso8601String(),
            'label' => $this->label,
            'explanation' => $this->explanation,
        ];
    }
}
