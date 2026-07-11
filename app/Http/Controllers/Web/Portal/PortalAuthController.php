<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Mail\PortalLoginLinkEmail;
use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PortalAuthController extends Controller
{
    private const GENERIC_LOGIN_MESSAGE = 'Nếu email này đã đăng ký, chúng tôi đã gửi một liên kết đăng nhập tới hộp thư của bạn.';

    public function showLoginForm(string $tenantSlug): View
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        return view('portal.login', ['tenant' => $tenant]);
    }

    public function sendLoginLink(Request $request, string $tenantSlug): RedirectResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        $account = Account::query()
            ->where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->first();

        if ($account instanceof Account) {
            $this->issueAndSendLoginToken($account, $tenant);
        }

        return redirect()
            ->route('portal.login', ['tenantSlug' => $tenantSlug])
            ->with('status', self::GENERIC_LOGIN_MESSAGE);
    }

    private function issueAndSendLoginToken(Account $account, Tenant $tenant): void
    {
        $rawToken = Str::random(64);

        \App\Models\PortalLoginToken::query()->create([
            'account_id' => (string) $account->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(20),
        ]);

        Mail::to($account->email)->send(new PortalLoginLinkEmail($tenant, $rawToken));
    }
}
