<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Baseline;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Đánh giá trạng thái trễ tiến độ của dự án so với kế hoạch gốc đã chốt
 * (spec: docs/superpowers/specs/2026-07-19-project-baseline-delay-design.md).
 * Thuần PHP, không I/O — mọi so sánh là date-only để không lệch theo giờ/múi giờ.
 */
class ProjectDelayStatus
{
    public const STATE_COMPLETED = 'completed';
    public const STATE_NO_BASELINE = 'no_baseline';
    public const STATE_LATE = 'late';
    public const STATE_FORECAST_LATE = 'forecast_late';
    public const STATE_ON_TRACK = 'on_track';

    /**
     * @return array{state: string, days_late: int|null, baseline: Baseline|null}
     */
    public static function evaluate(Project $project, ?Baseline $baseline): array
    {
        if ((string) $project->status === 'completed') {
            return ['state' => self::STATE_COMPLETED, 'days_late' => null, 'baseline' => $baseline];
        }

        if ($baseline === null || $baseline->end_date === null) {
            return ['state' => self::STATE_NO_BASELINE, 'days_late' => null, 'baseline' => null];
        }

        $today = self::dateOnly(Carbon::now());
        $committedEnd = self::dateOnly($baseline->end_date);

        if ($today->greaterThan($committedEnd)) {
            return [
                'state' => self::STATE_LATE,
                'days_late' => (int) $committedEnd->diffInDays($today),
                'baseline' => $baseline,
            ];
        }

        if ($project->end_date !== null) {
            $currentEnd = self::dateOnly($project->end_date);

            if ($currentEnd->greaterThan($committedEnd)) {
                return [
                    'state' => self::STATE_FORECAST_LATE,
                    'days_late' => (int) $committedEnd->diffInDays($currentEnd),
                    'baseline' => $baseline,
                ];
            }
        }

        return ['state' => self::STATE_ON_TRACK, 'days_late' => null, 'baseline' => $baseline];
    }

    private static function dateOnly(mixed $value): CarbonImmutable
    {
        return CarbonImmutable::parse(substr((string) $value, 0, 10))->startOfDay();
    }
}
