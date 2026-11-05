<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\TimeTracking;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\TimeTracking\TimeReviewService;
use App\Services\TimeTracking\TimeRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-112 (Gate 2 R-1) — weekly review queue; approve / reject several entries at once. Rules in TimeReviewService.
 */
class TimeReviewPageController extends Controller
{
    public function __construct(private readonly TimeReviewService $review)
    {
    }

    public function index(Request $request): View
    {
        abort_unless($this->review->isReviewer($this->user()), 403);
        $week = TimesheetPageController::weekStart((string) $request->query('week', ''));

        return view('time.review', [
            'week' => $week,
            'entries' => $this->review->queue($this->user(), $week),
        ]);
    }

    public function act(Request $request): RedirectResponse
    {
        abort_unless($this->review->isReviewer($this->user()), 403);
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['string'],
            'action' => ['required', 'in:approve,reject'],
            'reason' => ['required_if:action,reject', 'nullable', 'string', 'max:500'],
            'week' => ['nullable', 'date_format:Y-m-d'],
        ]);

        try {
            $count = $data['action'] === 'approve'
                ? $this->review->approve($this->user(), array_values($data['ids']))
                : $this->review->reject($this->user(), array_values($data['ids']), (string) ($data['reason'] ?? ''));
        } catch (TimeRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'ids' => $e->getMessage()]);
        }

        return redirect()->route('app.time.review', ['week' => $data['week'] ?? null])
            ->with('success', ($data['action'] === 'approve' ? 'Đã duyệt ' : 'Đã từ chối ') . $count . ' dòng giờ.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
