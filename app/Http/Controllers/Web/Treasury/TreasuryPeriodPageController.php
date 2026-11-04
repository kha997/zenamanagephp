<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\User;
use App\Services\Treasury\TreasuryPeriodLockService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-107 L-1 / U-1 — lock a project's Treasury period through a date, or move it back / clear it with a reason.
 * Route middleware: rbac:treasury.manage_period_lock; the Treasury project policy still applies.
 */
class TreasuryPeriodPageController extends Controller
{
    public function __construct(private readonly TreasuryPeriodLockService $locks)
    {
    }

    public function lock(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $data = $request->validate([
            'locked_through' => ['required', 'date_format:Y-m-d'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            $this->locks->lock($model, $this->user(), (string) $data['locked_through'], $data['reason'] ?? null);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'locked_through' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã khoá kỳ.');
    }

    public function unlock(Request $request, string $project): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $data = $request->validate([
            'locked_through' => ['nullable', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        try {
            $this->locks->unlock($model, $this->user(), $data['locked_through'] ?? null, (string) $data['reason']);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'locked_through' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.show', $model->id)->with('success', 'Đã mở khoá kỳ.');
    }

    private function findProject(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $project;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
