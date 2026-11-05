<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\TimeTracking;

use App\Http\Controllers\Controller;
use App\Models\TimeTracking\TimeEntry;
use App\Models\User;
use App\Services\TimeTracking\TimeReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * GAP-112 (Gate 2 P-1) — hours report by project and by person (hours only), scoped by TimeReportService; CSV export.
 */
class TimeReportPageController extends Controller
{
    public function __construct(private readonly TimeReportService $reports)
    {
    }

    public function index(Request $request): Response
    {
        $user = $this->user();
        abort_unless($this->reports->canView($user), 403);
        $from = self::date((string) $request->query('from', ''), Carbon::now()->startOfMonth());
        $to = self::date((string) $request->query('to', ''), Carbon::now()->endOfMonth());
        if ($to->lessThan($from)) {
            [$from, $to] = [$to, $from];
        }

        if ($request->query('export') === 'csv') {
            $csv = $this->reports->csv($user, $from, $to);

            return response()->streamDownload(static function () use ($csv): void {
                echo $csv;
            }, 'bao-cao-gio-' . $from->toDateString() . '-' . $to->toDateString() . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        return response()->view('time.report', [
            'from' => $from,
            'to' => $to,
            'byProject' => $this->reports->byProject($user, $from, $to),
            'byPerson' => $this->reports->byPerson($user, $from, $to),
            'categories' => TimeEntry::CATEGORIES,
        ]);
    }

    private static function date(string $value, Carbon $default): Carbon
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date === false ? $default->copy()->startOfDay() : Carbon::instance($date);
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
