<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Console\Commands\ProductionRbacCommand;
use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Admin\StaffAccountException;
use App\Services\Admin\StaffAccountService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * GAP-129 U-1 — staff accounts of the signed-in user's company.
 *
 * A generated password is flashed once (`one_time_password`) and rendered by the next page view only.
 */
final class UserAdminController extends Controller
{
    public function __construct(private readonly StaffAccountService $accounts)
    {
    }

    public function index(Request $request): View
    {
        $users = User::query()
            ->where('tenant_id', $this->tenantId($request))
            ->with('roles:id,name')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'email', 'is_active', 'created_at']);

        return view('app.admin.users', [
            'users' => $users,
            'roles' => ProductionRbacCommand::ROLES,
            'actorId' => (string) $request->user()?->id,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
            'role' => ['required', Rule::in(array_keys(ProductionRbacCommand::ROLES))],
        ]);
        /** @var Tenant $tenant */
        $tenant = Tenant::query()->findOrFail($this->tenantId($request));

        try {
            [$user, $password] = $this->accounts->create($tenant, $data['email'], $data['name'], $data['role'], $request->user(), 'web');
        } catch (StaffAccountException $e) {
            return back()->withErrors(['email' => $e->getMessage()])->withInput($request->except('password'));
        }

        return $this->withPassword($user, $password, 'Đã tạo tài khoản.');
    }

    public function updateRole(Request $request, string $user): RedirectResponse
    {
        $target = $this->target($request, $user);
        $data = $request->validate(['role' => ['required', Rule::in(array_keys(ProductionRbacCommand::ROLES))]]);

        return $this->attempt(fn () => $this->accounts->changeRole($target, $data['role'], $this->actor($request)), 'Đã đổi vai trò.');
    }

    public function deactivate(Request $request, string $user): RedirectResponse
    {
        $target = $this->target($request, $user);

        return $this->attempt(fn () => $this->accounts->deactivate($target, $this->actor($request)), 'Đã khoá tài khoản.');
    }

    public function reactivate(Request $request, string $user): RedirectResponse
    {
        $target = $this->target($request, $user);

        return $this->attempt(fn () => $this->accounts->reactivate($target, $this->actor($request)), 'Đã mở khoá tài khoản.');
    }

    public function resetPassword(Request $request, string $user): RedirectResponse
    {
        $target = $this->target($request, $user);
        $password = $this->accounts->resetPassword($target, $this->actor($request), 'web');

        return $this->withPassword($target, $password, 'Đã cấp mật khẩu mới.');
    }

    private function attempt(callable $action, string $success): RedirectResponse
    {
        try {
            $action();
        } catch (StaffAccountException $e) {
            return redirect()->route('app.admin.users')->withErrors(['account' => $e->getMessage()]);
        }

        return redirect()->route('app.admin.users')->with('success', $success);
    }

    private function withPassword(User $user, string $password, string $message): RedirectResponse
    {
        return redirect()->route('app.admin.users')
            ->with('success', $message)
            ->with('one_time_password', ['email' => $user->email, 'password' => $password]);
    }

    /**
     * A user of another company is reported as not found.
     */
    private function target(Request $request, string $id): User
    {
        /** @var User $user */
        $user = User::query()->where('tenant_id', $this->tenantId($request))->whereKey($id)->firstOrFail();

        return $user;
    }

    private function actor(Request $request): User
    {
        /** @var User $actor */
        $actor = $request->user();

        return $actor;
    }

    private function tenantId(Request $request): string
    {
        return (string) $request->user()?->tenant_id;
    }
}
