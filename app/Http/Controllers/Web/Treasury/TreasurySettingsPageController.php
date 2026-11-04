<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Treasury\TreasuryRuleViolation;
use App\Services\Treasury\TreasurySettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-108 (Gate 2 N-1, S-1) — the tenant-wide Treasury thresholds form on the company Treasury page; owner-level only.
 */
class TreasurySettingsPageController extends Controller
{
    public function __construct(private readonly TreasurySettingsService $settings)
    {
    }

    public function updateThresholds(Request $request): RedirectResponse
    {
        abort_unless($this->settings->canChange($this->user()), 403);
        $rule = ['required', 'integer', 'min:' . TreasurySettingsService::MIN_DAYS, 'max:' . TreasurySettingsService::MAX_DAYS];
        $data = $request->validate([
            'unreconciled_funding_days' => $rule,
            'advance_default_term_days' => $rule,
        ]);

        try {
            $this->settings->setThresholds($this->user(), (int) $data['unreconciled_funding_days'], (int) $data['advance_default_term_days']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'unreconciled_funding_days' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.index')->with('success', 'Đã cập nhật ngưỡng cảnh báo.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
