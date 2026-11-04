<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Payroll;

use App\Http\Controllers\Controller;
use App\Models\Payroll\StaffMember;
use App\Models\Treasury\TreasuryFinancialParty;
use App\Models\User;
use App\Services\Payroll\PayrollRuleViolation;
use App\Services\Payroll\StaffService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * GAP-109 Q-1 — staff register pages (rbac:payroll.manage); rules live in StaffService.
 */
class StaffPageController extends Controller
{
    private const RULES = [
        'code' => ['required', 'string', 'max:50'],
        'name' => ['required', 'string', 'max:255'],
        'staff_type' => ['required', 'string'],
        'is_overhead' => ['nullable', 'boolean'],
        'user_id' => ['nullable', 'string'],
        'financial_party_id' => ['nullable', 'string'],
        'standard_hours_override' => ['nullable', 'numeric'],
        'annual_bonus_estimate' => ['nullable', 'numeric'],
        'start_date' => ['required', 'date_format:Y-m-d'],
        'end_date' => ['nullable', 'date_format:Y-m-d'],
    ];

    public function __construct(private readonly StaffService $staff)
    {
    }

    public function index(): View
    {
        abort_unless($this->staff->canManage($this->user()), 403);

        return view('payroll.staff', $this->formData() + [
            'members' => StaffMember::query()->where('tenant_id', (string) $this->user()->tenant_id)->orderBy('code')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->staff->canManage($this->user()), 403);
        $data = $request->validate(self::RULES);

        try {
            $this->staff->create($this->user(), $data);
        } catch (PayrollRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'code' => $e->getMessage()]);
        }

        return redirect()->route('operator.payroll.staff.index')->with('success', 'Đã thêm nhân sự.');
    }

    public function edit(string $staff): View
    {
        abort_unless($this->staff->canManage($this->user()), 403);

        return view('payroll.staff-edit', $this->formData() + ['member' => $this->find($staff)]);
    }

    public function update(Request $request, string $staff): RedirectResponse
    {
        abort_unless($this->staff->canManage($this->user()), 403);
        $member = $this->find($staff);
        $data = $request->validate(self::RULES);

        try {
            $this->staff->update($this->user(), $member, $data);
        } catch (PayrollRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'code' => $e->getMessage()]);
        }

        return redirect()->route('operator.payroll.staff.index')->with('success', 'Đã cập nhật nhân sự.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        $tenantId = (string) $this->user()->tenant_id;

        return [
            'staffTypes' => StaffMember::TYPES,
            'users' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->pluck('name', 'id')->all(),
            'parties' => TreasuryFinancialParty::query()->where('tenant_id', $tenantId)->orderBy('name')->pluck('name', 'id')->all(),
        ];
    }

    private function find(string $id): StaffMember
    {
        /** @var StaffMember $member */
        $member = StaffMember::query()->withoutGlobalScopes()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $member;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
