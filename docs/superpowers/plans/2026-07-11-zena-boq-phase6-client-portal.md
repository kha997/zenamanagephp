# Phase 6 — Cổng khách hàng Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** let a client log in via a passwordless magic-link (tied to their `Account.email`) and see a read-only, Account-scoped view of their design progress, approved documents, quote/contract summary, and outstanding balance — with entirely new, separate auth infrastructure from staff RBAC.

**Architecture:** A brand-new Laravel auth guard (`client`, session-based) backed directly by the `Account` model (no new "portal user" model — `Account` itself becomes `Authenticatable`). A new `portal_login_tokens` table holds single-use, hashed, short-lived tokens. A public login flow (email → emailed link → session) sits alongside a protected dashboard flow, both under a new `/portal/{tenant_slug}/...` URL space that resolves the tenant from the URL before ever touching an email address, since a client only ever knows their own email, never a tenant ID.

**Tech Stack:** Laravel 12 session auth (new guard/provider), `Illuminate\Auth\Authenticatable` trait, existing `Mail`/`Mailable` infrastructure (modeled on `app/Mail/InvitationEmail.php`, the one real, functional precedent in this codebase — not `PasswordResetEmail.php`, which is an unfinished scaffold), existing `<x-ui.card>`/`<x-ui.field-value>` Blade components reused in a new, separate portal layout.

## Global Constraints

- **`Account` implements `Authenticatable` via the `Illuminate\Auth\Authenticatable` **trait** (not by changing its base class)** — add `implements \Illuminate\Contracts\Auth\Authenticatable` and `use \Illuminate\Auth\Authenticatable;` to the existing `Account extends Model` class. Do not change `Account`'s parent class to `Illuminate\Foundation\Auth\User` — that would be a much larger, riskier structural change to a model already used extensively elsewhere. `Account` has no `password`/`remember_token` columns; the trait's default method implementations handle this gracefully (return `null`), and magic-link auth never calls `getAuthPassword()` for credential verification.
- **New `client` guard, session driver, `accounts` provider** in `config/auth.php` — entirely separate from the existing `web`/`api` guards (both backed by `User`, both staff-only). Do not add any `rbac:*` middleware to portal routes — that system is staff-only and does not apply here.
- **Tokens are single-use, hashed, and short-lived.** Generate a high-entropy raw token (`Str::random(64)`), store only `hash('sha256', $rawToken)` in the database (never the raw token), with a 20-minute `expires_at` and a `used_at` marked immediately on successful verification (before doing anything else) so a second click on the same link never re-authenticates.
- **No account-existence enumeration.** Whether or not the submitted email matches a real `Account` in the URL's tenant, the login-request endpoint always redirects with the exact same generic message. The only difference between a real and fake email is whether an email actually gets sent — never a difference in the HTTP response.
- **Tenant is resolved from the URL slug first, always, before any email lookup.** `Account::where('tenant_id', $tenant->id)->where('email', $email)->first()` — never a bare `where('email', $email)` search across all tenants.
- **The authenticated-portal middleware checks both the guard AND a tenant match** — `Auth::guard('client')->check()` is necessary but not sufficient; the authenticated `Account`'s `tenant_id` must also match the URL's resolved tenant, or the middleware logs the account out and redirects to login. This is defense in depth, not redundant.
- **`Project` has no direct link to `Account`** — the only path is `Account → Opportunity (account_id) → converted_project_id → Project`. Every portal query for "this client's projects" must go through `Opportunity`, filtering `whereNotNull('converted_project_id')` (an Opportunity that hasn't converted yet has no Project for the client to see).
- **`Document` status filter is `'approved'`** (verified against `Web\DocumentController.php` — the real value in this codebase; there is no `'final'` status despite what an earlier, unverified draft of this spec section guessed).
- **`DesignItem.review_status`** (not a bare `status` column) is the field to display for design progress — confirmed against the actual merged `App\Models\DesignItem` (Phase 1, merged as of this phase's start).
- Rate-limit the login-request endpoint: `throttle:6,1` (mirrors the existing precedent at `routes/web.php`'s `api-tokens.store` route) — prevents both enumeration-by-volume and mailbox-spam abuse.
- `declare(strict_types=1)` at the top of every PHP file touched or created.
- Session regeneration on login (`$request->session()->regenerate()`) and on logout (`invalidate()` + `regenerateToken()`) — standard session-fixation defense, matching how Laravel's own `Illuminate\Auth\SessionGuard::login()` callers are expected to behave.

---

### Task 1: Auth infrastructure — `Account` as `Authenticatable`, `client` guard, login tokens

**Files:**
- Create: `database/migrations/2026_07_11_130000_create_portal_login_tokens_table.php`
- Create: `app/Models/PortalLoginToken.php`
- Modify: `app/Models/Account.php`
- Modify: `config/auth.php`
- Test: `tests/Unit/Models/PortalLoginTokenTest.php`
- Test: `tests/Unit/Models/AccountAuthenticatableTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `Account` is now a valid target for `Auth::guard('client')->login($account)` and `$this->actingAs($account, 'client')` in tests. `PortalLoginToken` model with `account_id`, `token_hash`, `expires_at`, `used_at`. Task 2/3 consume both.

- [ ] **Step 1: Write the failing tests**

Create `tests/Unit/Models/PortalLoginTokenTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\PortalLoginToken;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLoginTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_create_and_read_a_portal_login_token(): void
    {
        $tenant = Tenant::factory()->create();
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang portal',
            'email' => 'client@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $token = PortalLoginToken::query()->create([
            'account_id' => (string) $account->id,
            'token_hash' => hash('sha256', 'raw-token-value'),
            'expires_at' => now()->addMinutes(20),
        ]);

        $token->refresh();

        $this->assertSame((string) $account->id, $token->account_id);
        $this->assertSame(hash('sha256', 'raw-token-value'), $token->token_hash);
        $this->assertNull($token->used_at);
    }
}
```

Create `tests/Unit/Models/AccountAuthenticatableTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\Account;
use App\Models\Tenant;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountAuthenticatableTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_implements_authenticatable_and_can_be_used_with_the_client_guard(): void
    {
        $tenant = Tenant::factory()->create();
        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang guard test',
            'email' => 'guardtest@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->assertInstanceOf(Authenticatable::class, $account);

        $this->actingAs($account, 'client');

        $this->assertTrue(auth('client')->check());
        $this->assertSame((string) $account->id, (string) auth('client')->id());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Unit/Models/PortalLoginTokenTest.php tests/Unit/Models/AccountAuthenticatableTest.php`
Expected: FAIL — `PortalLoginToken` class and `portal_login_tokens` table don't exist; `Account` doesn't implement `Authenticatable`; the `client` guard isn't configured.

- [ ] **Step 3: Create the migration**

Create `database/migrations/2026_07_11_130000_create_portal_login_tokens_table.php`:

```php
<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_login_tokens', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('account_id');
            $table->string('token_hash')->unique();
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('account_id');
            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_login_tokens');
    }
};
```

- [ ] **Step 4: Run migrations**

Run: `php artisan migrate`
Expected: migration runs clean, no errors.

- [ ] **Step 5: Create the `PortalLoginToken` model**

Create `app/Models/PortalLoginToken.php`:

```php
<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalLoginToken extends Model
{
    use HasUlids;

    protected $table = 'portal_login_tokens';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'account_id' => 'string',
        'token_hash' => 'string',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
```

- [ ] **Step 6: Make `Account` implement `Authenticatable`**

In `app/Models/Account.php`, find:

```php
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Account — khách hàng (cá nhân hoặc công ty). Port từ spec crm-zena.
 */
class Account extends Model
{
    use HasUlids;
```

Replace with:

```php
use Illuminate\Auth\Authenticatable;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Account — khách hàng (cá nhân hoặc công ty). Port từ spec crm-zena.
 * Also implements Authenticatable so it can be used directly as the
 * identity for the `client` portal auth guard (Phase 6) — no password,
 * no remember-token columns needed; the trait's defaults handle this.
 */
class Account extends Model implements AuthenticatableContract
{
    use HasUlids;
    use Authenticatable;
```

Do not remove or reorder any existing constants, properties, relationships, or methods elsewhere in this class — this step only touches the `use` import block, the docblock, and the class declaration line plus its first trait-use line.

- [ ] **Step 7: Add the `client` guard and `accounts` provider**

In `config/auth.php`, find:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
    ],
```

Replace with:

```php
    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
        'api' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],
        'client' => [
            'driver' => 'session',
            'provider' => 'accounts',
        ],
    ],
```

Then find:

```php
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],
```

Replace with:

```php
    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        'accounts' => [
            'driver' => 'eloquent',
            'model' => App\Models\Account::class,
        ],
```

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test tests/Unit/Models/PortalLoginTokenTest.php tests/Unit/Models/AccountAuthenticatableTest.php`
Expected: PASS (2/2).

- [ ] **Step 9: Run the full suite to confirm no regression**

Run: `php artisan test`
Expected: PASS — adding an interface/trait to `Account` and a new guard/provider config are additive; nothing existing should break. If anything unrelated fails, investigate before proceeding (do not assume it's pre-existing without checking `git log` on the failing file first).

- [ ] **Step 10: Commit**

```bash
git add database/migrations/2026_07_11_130000_create_portal_login_tokens_table.php app/Models/PortalLoginToken.php app/Models/Account.php config/auth.php tests/Unit/Models/PortalLoginTokenTest.php tests/Unit/Models/AccountAuthenticatableTest.php
git commit -m "feat(portal): add client auth guard, Account as Authenticatable, portal_login_tokens table"
```

---

### Task 2: Login request flow — email form, magic-link email, rate limiting

**Files:**
- Create: `app/Http/Controllers/Web/Portal/PortalAuthController.php` (methods `showLoginForm`, `sendLoginLink` only in this task — `verify`/`logout` are Task 3)
- Create: `app/Mail/PortalLoginLinkEmail.php`
- Create: `resources/views/emails/portal-login-link.blade.php`
- Create: `resources/views/layouts/portal.blade.php`
- Create: `resources/views/portal/login.blade.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/Portal/PortalLoginRequestTest.php`

**Interfaces:**
- Consumes: `PortalLoginToken` (Task 1), `Account` (Task 1, now `Authenticatable`).
- Produces: `GET /portal/{tenantSlug}/login` (route name `portal.login`), `POST /portal/{tenantSlug}/login` (route name `portal.login.send`). Task 3 adds the verification/logout routes to the same controller and route file.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Portal/PortalLoginRequestTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\PortalLoginToken;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PortalLoginRequestTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'zena-test']);
        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang login test',
            'email' => 'realclient@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);
    }

    public function test_login_form_renders_for_a_valid_tenant_slug(): void
    {
        $this->get(route('portal.login', ['tenantSlug' => 'zena-test']))
            ->assertOk()
            ->assertSee('Đăng nhập');
    }

    public function test_login_form_404s_for_an_unknown_tenant_slug(): void
    {
        $this->get(route('portal.login', ['tenantSlug' => 'no-such-tenant']))
            ->assertNotFound();
    }

    public function test_sending_login_link_for_a_real_email_creates_a_token_and_sends_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('portal.login.send', ['tenantSlug' => 'zena-test']), [
            'email' => 'realclient@example.com',
        ]);

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-test']));
        $response->assertSessionHas('status');

        $this->assertSame(1, PortalLoginToken::query()->where('account_id', (string) $this->account->id)->count());
        Mail::assertSent(\App\Mail\PortalLoginLinkEmail::class, 1);
    }

    public function test_sending_login_link_for_unknown_email_shows_same_generic_message_without_sending_mail(): void
    {
        Mail::fake();

        $response = $this->post(route('portal.login.send', ['tenantSlug' => 'zena-test']), [
            'email' => 'nobody-registered@example.com',
        ]);

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-test']));
        $response->assertSessionHas('status');

        $this->assertSame(0, PortalLoginToken::query()->count());
        Mail::assertNothingSent();
    }

    public function test_sending_login_link_for_an_email_registered_under_a_different_tenant_does_not_match(): void
    {
        Mail::fake();

        $otherTenant = Tenant::factory()->create(['slug' => 'other-tenant']);
        Account::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tenant khac',
            'email' => 'crosstenant@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->post(route('portal.login.send', ['tenantSlug' => 'zena-test']), [
            'email' => 'crosstenant@example.com',
        ]);

        $this->assertSame(0, PortalLoginToken::query()->count());
        Mail::assertNothingSent();
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Portal/PortalLoginRequestTest.php`
Expected: FAIL — routes don't exist yet.

- [ ] **Step 3: Create the portal layout**

Create `resources/views/layouts/portal.blade.php`:

```blade
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Cổng khách hàng')</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/operator.css'])
</head>
<body class="bg-slate-50">
    <div class="mx-auto max-w-3xl px-4 py-10">
        <header class="mb-8">
            <h1 class="text-xl font-semibold text-slate-900">Cổng khách hàng</h1>
        </header>
        <main>
            @if (session('status'))
                <div class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ session('error') }}</div>
            @endif
            @yield('content')
        </main>
    </div>
</body>
</html>
```

- [ ] **Step 4: Create the login view**

Create `resources/views/portal/login.blade.php`:

```blade
@extends('layouts.portal')

@section('title', 'Đăng nhập')

@section('content')
    <x-ui.card title="Đăng nhập">
        <p class="mb-4 text-sm text-slate-600">Nhập email đã đăng ký với chúng tôi để nhận liên kết đăng nhập.</p>
        <form method="POST" action="{{ route('portal.login.send', ['tenantSlug' => $tenant->slug]) }}" class="flex flex-wrap items-end gap-3">
            @csrf
            <div class="operator-field flex-1 min-w-64">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" class="operator-input" value="{{ old('email') }}" required>
            </div>
            <button type="submit" class="operator-button operator-button-primary">Gửi liên kết đăng nhập</button>
        </form>
        @error('email')
            <p class="mt-2 text-sm text-rose-600">{{ $message }}</p>
        @enderror
    </x-ui.card>
@endsection
```

- [ ] **Step 5: Create the Mailable and its view**

Create `app/Mail/PortalLoginLinkEmail.php`:

```php
<?php declare(strict_types=1);

namespace App\Mail;

use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class PortalLoginLinkEmail extends Mailable implements ShouldQueue
{
    use Queueable;

    public string $loginUrl;

    public function __construct(Tenant $tenant, string $rawToken)
    {
        $this->loginUrl = route('portal.login.verify', [
            'tenantSlug' => $tenant->slug,
            'token' => $rawToken,
        ]);
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Liên kết đăng nhập cổng khách hàng',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.portal-login-link',
            with: [
                'loginUrl' => $this->loginUrl,
            ],
        );
    }
}
```

Create `resources/views/emails/portal-login-link.blade.php`:

```blade
<!DOCTYPE html>
<html lang="vi">
<head><meta charset="utf-8"></head>
<body style="font-family: sans-serif; color: #1e293b;">
    <p>Xin chào,</p>
    <p>Nhấn vào liên kết bên dưới để đăng nhập cổng khách hàng. Liên kết có hiệu lực trong 20 phút và chỉ dùng được một lần.</p>
    <p><a href="{{ $loginUrl }}">{{ $loginUrl }}</a></p>
    <p>Nếu bạn không yêu cầu email này, vui lòng bỏ qua.</p>
</body>
</html>
```

- [ ] **Step 6: Create the controller (login-request half only)**

Create `app/Http/Controllers/Web/Portal/PortalAuthController.php`:

```php
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
```

- [ ] **Step 7: Add the routes**

In `routes/web.php`, find the end of the `operator` prefix group — the line immediately before `Route::middleware(['web', 'auth:sanctum', 'tenant.isolation', 'rbac'])->prefix('api')->as('api.legacy.')`:

```php
    Route::get('/crm/reports', [App\Http\Controllers\Web\CrmReportController::class, 'index'])->middleware('rbac:crm.view')->name('crm.reports');
});

Route::middleware(['web', 'auth:sanctum', 'tenant.isolation', 'rbac'])->prefix('api')->as('api.legacy.')->group(function () {
```

Replace with:

```php
    Route::get('/crm/reports', [App\Http\Controllers\Web\CrmReportController::class, 'index'])->middleware('rbac:crm.view')->name('crm.reports');
});

Route::prefix('portal/{tenantSlug}')->as('portal.')->middleware(['web'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'sendLoginLink'])->middleware('throttle:6,1')->name('login.send');
    Route::get('/login/{token}', fn () => abort(404))->name('login.verify');
});

Route::middleware(['web', 'auth:sanctum', 'tenant.isolation', 'rbac'])->prefix('api')->as('api.legacy.')->group(function () {
```

**Why the `login.verify` placeholder route is required here, not optional:** `PortalLoginLinkEmail`'s constructor calls `route('portal.login.verify', ...)` to build the link URL. Laravel's `route()` helper resolves and throws immediately if the name isn't registered — `Mail::fake()` (used in this task's tests) intercepts the actual *send*, but the `Mailable` object itself, including its constructor, still runs eagerly before being handed to `Mail::fake()`. Without this placeholder, `test_sending_login_link_for_a_real_email_creates_a_token_and_sends_mail` would throw `RouteNotFoundException`, not fail an assertion. Task 3 replaces this placeholder closure with the real `verify()` method binding — same route name, same position, just a real implementation instead of `abort(404)`.

- [ ] **Step 8: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Portal/PortalLoginRequestTest.php`
Expected: PASS (5/5).

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Web/Portal/PortalAuthController.php app/Mail/PortalLoginLinkEmail.php resources/views/emails/portal-login-link.blade.php resources/views/layouts/portal.blade.php resources/views/portal/login.blade.php routes/web.php tests/Feature/Portal/PortalLoginRequestTest.php
git commit -m "feat(portal): add login-request flow (email form, magic-link email, no enumeration, rate-limited)"
```

---

### Task 3: Magic-link verification, logout, and the portal-auth middleware

**Files:**
- Modify: `app/Http/Controllers/Web/Portal/PortalAuthController.php` (add `verify`, `logout`)
- Create: `app/Http/Middleware/EnsurePortalAccountAuthenticated.php`
- Modify: `routes/web.php` (add `login.verify`/`logout` routes, and a protected route group with a placeholder `dashboard` route for this task's tests — Task 4 replaces the placeholder with the real dashboard)
- Test: `tests/Feature/Portal/PortalLoginVerifyTest.php`

**Interfaces:**
- Consumes: `PortalLoginToken`, `Account` (Task 1); `PortalAuthController::showLoginForm`/`sendLoginLink` (Task 2, unchanged).
- Produces: `GET /portal/{tenantSlug}/login/{token}` (route name `portal.login.verify`), `POST /portal/{tenantSlug}/logout` (route name `portal.logout`), `EnsurePortalAccountAuthenticated` middleware (alias `portal.auth`) — Task 4 applies this middleware to the real dashboard route.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Portal/PortalLoginVerifyTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\PortalLoginToken;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalLoginVerifyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;
    private Account $account;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create(['slug' => 'zena-verify']);
        $this->account = Account::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang verify test',
            'email' => 'verifyme@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);
    }

    private function createToken(string $rawToken, array $overrides = []): PortalLoginToken
    {
        return PortalLoginToken::query()->create(array_merge([
            'account_id' => (string) $this->account->id,
            'token_hash' => hash('sha256', $rawToken),
            'expires_at' => now()->addMinutes(20),
        ], $overrides));
    }

    public function test_valid_unexpired_unused_token_authenticates_and_redirects_to_dashboard(): void
    {
        $this->createToken('valid-raw-token');

        $response = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'valid-raw-token']));

        $response->assertRedirect(route('portal.dashboard', ['tenantSlug' => 'zena-verify']));
        $this->assertTrue(auth('client')->check());
        $this->assertSame((string) $this->account->id, (string) auth('client')->id());
    }

    public function test_token_is_marked_used_after_successful_verification(): void
    {
        $this->createToken('single-use-token');

        $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'single-use-token']));

        $token = PortalLoginToken::query()->where('token_hash', hash('sha256', 'single-use-token'))->first();
        $this->assertNotNull($token->used_at);
    }

    public function test_second_click_on_the_same_link_does_not_authenticate(): void
    {
        $this->createToken('reused-token');

        $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'reused-token']));
        auth('client')->logout();

        $second = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'reused-token']));

        $second->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_expired_token_does_not_authenticate(): void
    {
        $this->createToken('expired-token', ['expires_at' => now()->subMinute()]);

        $response = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'expired-token']));

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_unknown_token_does_not_authenticate(): void
    {
        $response = $this->get(route('portal.login.verify', ['tenantSlug' => 'zena-verify', 'token' => 'never-issued']));

        $response->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_protected_portal_route_redirects_to_login_when_not_authenticated(): void
    {
        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-verify']))
            ->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
    }

    public function test_protected_portal_route_rejects_an_account_authenticated_under_a_different_tenant_slug(): void
    {
        $otherTenant = Tenant::factory()->create(['slug' => 'zena-other']);
        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $otherTenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang tenant khac',
            'email' => 'other@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->actingAs($otherAccount, 'client');

        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-verify']))
            ->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));
        $this->assertFalse(auth('client')->check());
    }

    public function test_logout_ends_the_session(): void
    {
        $this->actingAs($this->account, 'client');
        $this->assertTrue(auth('client')->check());

        $this->post(route('portal.logout', ['tenantSlug' => 'zena-verify']))
            ->assertRedirect(route('portal.login', ['tenantSlug' => 'zena-verify']));

        $this->assertFalse(auth('client')->check());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Portal/PortalLoginVerifyTest.php`
Expected: FAIL — `login.verify`/`logout`/`dashboard` routes and the middleware don't exist yet.

- [ ] **Step 3: Add `verify` and `logout` to the controller**

In `app/Http/Controllers/Web/Portal/PortalAuthController.php`, add this import alongside the existing ones:

```php
use App\Models\PortalLoginToken;
use Illuminate\Support\Facades\Auth;
```

Add these two methods after `sendLoginLink()` (before the closing `}` of the class, and before the private `issueAndSendLoginToken()` helper):

```php
    public function verify(Request $request, string $tenantSlug, string $token): RedirectResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        $loginToken = PortalLoginToken::query()
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->first();

        if (!$loginToken instanceof PortalLoginToken) {
            return redirect()
                ->route('portal.login', ['tenantSlug' => $tenantSlug])
                ->with('error', 'Liên kết đăng nhập không hợp lệ hoặc đã hết hạn.');
        }

        $account = Account::query()
            ->where('tenant_id', $tenant->id)
            ->find($loginToken->account_id);

        if (!$account instanceof Account) {
            return redirect()
                ->route('portal.login', ['tenantSlug' => $tenantSlug])
                ->with('error', 'Liên kết đăng nhập không hợp lệ hoặc đã hết hạn.');
        }

        $loginToken->used_at = now();
        $loginToken->save();

        Auth::guard('client')->login($account);
        $request->session()->regenerate();

        return redirect()->route('portal.dashboard', ['tenantSlug' => $tenantSlug]);
    }

    public function logout(Request $request, string $tenantSlug): RedirectResponse
    {
        Auth::guard('client')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
    }
```

- [ ] **Step 4: Create the portal-auth middleware**

Create `app/Http/Middleware/EnsurePortalAccountAuthenticated.php`:

```php
<?php declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Account;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePortalAccountAuthenticated
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenantSlug = (string) $request->route('tenantSlug');
        $tenant = Tenant::where('slug', $tenantSlug)->first();

        if (!$tenant) {
            abort(404);
        }

        if (!Auth::guard('client')->check()) {
            return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
        }

        $account = Auth::guard('client')->user();

        if (!$account instanceof Account || (string) $account->tenant_id !== (string) $tenant->id) {
            Auth::guard('client')->logout();

            return redirect()->route('portal.login', ['tenantSlug' => $tenantSlug]);
        }

        $request->attributes->set('portalTenant', $tenant);

        return $next($request);
    }
}
```

- [ ] **Step 5: Register the middleware alias**

In `app/Http/Kernel.php`, find the `CANONICAL_MIDDLEWARE_ALIASES` constant (both `$middlewareAliases` and `$routeMiddleware` reference this same constant, so one edit covers both):

```php
    protected const CANONICAL_MIDDLEWARE_ALIASES = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.api' => \App\Http\Middleware\ApiAuthenticationMiddleware::class,
        'auth.session' => \App\Http\Middleware\SessionManagementMiddleware::class,
        'tenant.isolation' => \App\Http\Middleware\TenantIsolationMiddleware::class,
        'rbac' => \App\Http\Middleware\RoleBasedAccessControlMiddleware::class,
```

Replace with:

```php
    protected const CANONICAL_MIDDLEWARE_ALIASES = [
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.api' => \App\Http\Middleware\ApiAuthenticationMiddleware::class,
        'auth.session' => \App\Http\Middleware\SessionManagementMiddleware::class,
        'tenant.isolation' => \App\Http\Middleware\TenantIsolationMiddleware::class,
        'rbac' => \App\Http\Middleware\RoleBasedAccessControlMiddleware::class,
        'portal.auth' => \App\Http\Middleware\EnsurePortalAccountAuthenticated::class,
```

- [ ] **Step 6: Add the routes**

In `routes/web.php`, find the portal route block added in Task 2 (note the `login.verify` line is currently a placeholder `fn () => abort(404)` — Task 2 added it only so `PortalLoginLinkEmail`'s constructor could resolve the route name; this step replaces it with the real binding):

```php
Route::prefix('portal/{tenantSlug}')->as('portal.')->middleware(['web'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'sendLoginLink'])->middleware('throttle:6,1')->name('login.send');
    Route::get('/login/{token}', fn () => abort(404))->name('login.verify');
});
```

Replace with:

```php
Route::prefix('portal/{tenantSlug}')->as('portal.')->middleware(['web'])->group(function () {
    Route::get('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'sendLoginLink'])->middleware('throttle:6,1')->name('login.send');
    Route::get('/login/{token}', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'verify'])->middleware('throttle:10,1')->name('login.verify');
    Route::post('/logout', [App\Http\Controllers\Web\Portal\PortalAuthController::class, 'logout'])->name('logout');

    Route::middleware(['portal.auth'])->group(function () {
        Route::get('/dashboard', function () {
            return 'placeholder — replaced by Task 4';
        })->name('dashboard');
    });
});
```

(Task 4 replaces the placeholder `dashboard` route's closure with the real `PortalDashboardController::index` binding.)

- [ ] **Step 7: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Portal/PortalLoginVerifyTest.php`
Expected: PASS (8/8).

- [ ] **Step 8: Run Task 2's tests together to confirm no regression**

Run: `php artisan test tests/Feature/Portal/PortalLoginRequestTest.php tests/Feature/Portal/PortalLoginVerifyTest.php`
Expected: PASS across both files.

- [ ] **Step 9: Commit**

```bash
git add app/Http/Controllers/Web/Portal/PortalAuthController.php app/Http/Middleware/EnsurePortalAccountAuthenticated.php app/Http/Kernel.php routes/web.php tests/Feature/Portal/PortalLoginVerifyTest.php
git commit -m "feat(portal): add magic-link verification, logout, and the tenant-checking portal-auth middleware"
```

---

### Task 4: Portal dashboard — the real, read-only, Account-scoped content

**Files:**
- Create: `app/Http/Controllers/Web/Portal/PortalDashboardController.php`
- Create: `resources/views/portal/dashboard.blade.php`
- Modify: `routes/web.php` (replace the Task 3 placeholder `dashboard` route)
- Test: `tests/Feature/Portal/PortalDashboardTest.php`

**Interfaces:**
- Consumes: `Account`/`Opportunity`/`Project`/`DesignItem`/`Document`/`Contract`/`ContractPayment` (all existing), `EnsurePortalAccountAuthenticated` middleware (Task 3).
- Produces: `GET /portal/{tenantSlug}/dashboard` (route name `portal.dashboard`, already registered by Task 3 — this task only replaces its closure with a real controller binding).

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/Portal/PortalDashboardTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Portal;

use App\Models\Account;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_shows_projects_design_items_documents_and_balance_for_the_account(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang dashboard',
            'email' => 'dashboard@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $project = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Biet thu Thao Dien',
            'code' => 'PRJ-PORTAL1',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $account->id,
            'opportunity_name' => 'Co hoi da convert',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $project->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        DesignItem::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Phoi canh mat tien',
            'item_type' => 'concept',
            'review_status' => DesignItem::STATUS_SENT_TO_CLIENT,
        ]);

        Document::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Ban ve duyet',
            'status' => 'approved',
        ]);

        Document::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'name' => 'Ban ve nhap',
            'status' => 'draft',
        ]);

        $contract = Contract::query()->create([
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'code' => 'CTR-PORTAL1',
            'title' => 'Hop dong portal test',
            'total_value' => 500000000,
            'currency' => 'VND',
        ]);

        ContractPayment::query()->create([
            'tenant_id' => (string) $tenant->id,
            'contract_id' => (string) $contract->id,
            'name' => 'Dot 1',
            'amount' => 100000000,
            'status' => ContractPayment::STATUS_PLANNED,
            'due_date' => now()->addDays(10),
        ]);

        $this->actingAs($account, 'client');

        $response = $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash']));

        $response->assertOk();
        $response->assertSee('Biet thu Thao Dien');
        $response->assertSee('Phoi canh mat tien');
        $response->assertSee('Ban ve duyet');
        $response->assertDontSee('Ban ve nhap');
        $response->assertSee('100.000.000', false);
    }

    public function test_dashboard_never_shows_another_accounts_data(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash2']);
        $staffUser = User::factory()->create(['tenant_id' => (string) $tenant->id]);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang A',
            'email' => 'a@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $otherAccount = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang B',
            'email' => 'b@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $otherProject = Project::query()->create([
            'tenant_id' => (string) $tenant->id,
            'name' => 'Du an cua khach B',
            'code' => 'PRJ-PORTAL2',
            'status' => 'active',
        ]);

        Opportunity::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_id' => (string) $otherAccount->id,
            'opportunity_name' => 'Co hoi cua B',
            'service_category' => 'architecture',
            'pipeline_stage' => Opportunity::STAGE_WON,
            'converted_project_id' => (string) $otherProject->id,
            'sales_owner_id' => (string) $staffUser->id,
            'created_by' => (string) $staffUser->id,
        ]);

        $this->actingAs($account, 'client');

        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash2']))
            ->assertOk()
            ->assertDontSee('Du an cua khach B');
    }

    public function test_dashboard_shows_project_with_no_opportunity_data_gracefully(): void
    {
        $tenant = Tenant::factory()->create(['slug' => 'zena-dash3']);

        $account = Account::query()->create([
            'tenant_id' => (string) $tenant->id,
            'account_type' => Account::TYPE_INDIVIDUAL,
            'display_name' => 'Khach hang chua co du an',
            'email' => 'noproject@example.com',
            'status' => Account::STATUS_ACTIVE,
        ]);

        $this->actingAs($account, 'client');

        $this->get(route('portal.dashboard', ['tenantSlug' => 'zena-dash3']))
            ->assertOk()
            ->assertSee('Chưa có dự án');
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test tests/Feature/Portal/PortalDashboardTest.php`
Expected: FAIL — the placeholder route returns a plain string, not the real content.

- [ ] **Step 3: Create the controller**

Create `app/Http/Controllers/Web/Portal/PortalDashboardController.php`:

```php
<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Contract;
use App\Models\ContractPayment;
use App\Models\DesignItem;
use App\Models\Document;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;

class PortalDashboardController extends Controller
{
    public function index(string $tenantSlug): View
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $projectIds = Opportunity::query()
            ->where('tenant_id', $tenant->id)
            ->where('account_id', $account->id)
            ->whereNotNull('converted_project_id')
            ->pluck('converted_project_id')
            ->unique()
            ->values();

        $projects = Project::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'code', 'status']);

        $designItems = DesignItem::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('project_id', $projectIds)
            ->orderBy('name')
            ->get(['id', 'project_id', 'name', 'review_status']);

        $documents = Document::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('project_id', $projectIds)
            ->where('status', 'approved')
            ->orderByDesc('created_at')
            ->get(['id', 'project_id', 'name', 'title', 'created_at']);

        $contracts = Contract::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('project_id', $projectIds)
            ->get(['id', 'project_id', 'code', 'total_value', 'currency', 'status']);

        $outstandingBalance = (float) ContractPayment::query()
            ->where('tenant_id', $tenant->id)
            ->whereIn('contract_id', $contracts->pluck('id'))
            ->where('status', '!=', ContractPayment::STATUS_PAID)
            ->sum('amount');

        return view('portal.dashboard', [
            'tenant' => $tenant,
            'projects' => $projects,
            'designItems' => $designItems,
            'documents' => $documents,
            'contracts' => $contracts,
            'outstandingBalance' => $outstandingBalance,
        ]);
    }
}
```

- [ ] **Step 4: Create the dashboard view**

Create `resources/views/portal/dashboard.blade.php`:

```blade
@extends('layouts.portal')

@section('title', 'Cổng khách hàng')

@section('content')
    <div class="space-y-6">
        <form method="POST" action="{{ route('portal.logout', ['tenantSlug' => $tenant->slug]) }}" class="text-right">
            @csrf
            <button type="submit" class="operator-button operator-button-secondary">Đăng xuất</button>
        </form>

        <x-ui.card title="Dự án">
            @if ($projects->isEmpty())
                <p class="text-sm text-slate-500">Chưa có dự án nào.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($projects as $project)
                        <li class="text-sm font-medium text-slate-900">{{ $project->name }} ({{ $project->code }})</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Tiến độ thiết kế">
            @if ($designItems->isEmpty())
                <p class="text-sm text-slate-500">Chưa có hạng mục thiết kế.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($designItems as $item)
                        <li class="text-sm"><span class="font-medium text-slate-900">{{ $item->name }}</span> — {{ $item->review_status }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Tài liệu đã duyệt">
            @if ($documents->isEmpty())
                <p class="text-sm text-slate-500">Chưa có tài liệu.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($documents as $document)
                        <li class="text-sm text-slate-900">{{ $document->title ?? $document->name }}</li>
                    @endforeach
                </ul>
            @endif
        </x-ui.card>

        <x-ui.card title="Hợp đồng">
            @if ($contracts->isEmpty())
                <p class="text-sm text-slate-500">Chưa có hợp đồng.</p>
            @else
                <div class="grid gap-4 sm:grid-cols-2">
                    @foreach ($contracts as $contract)
                        <x-ui.field-value :label="$contract->code" :value="number_format((float) $contract->total_value, 0, ',', '.') . ' ' . $contract->currency" />
                    @endforeach
                </div>
            @endif
        </x-ui.card>

        <x-ui.card title="Công nợ">
            <x-ui.field-value label="Số dư còn lại" :value="number_format($outstandingBalance, 0, ',', '.') . '₫'" />
        </x-ui.card>
    </div>
@endsection
```

- [ ] **Step 5: Replace the placeholder route**

In `routes/web.php`, find:

```php
    Route::middleware(['portal.auth'])->group(function () {
        Route::get('/dashboard', function () {
            return 'placeholder — replaced by Task 4';
        })->name('dashboard');
    });
```

Replace with:

```php
    Route::middleware(['portal.auth'])->group(function () {
        Route::get('/dashboard', [App\Http\Controllers\Web\Portal\PortalDashboardController::class, 'index'])->name('dashboard');
    });
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test tests/Feature/Portal/PortalDashboardTest.php`
Expected: PASS (3/3).

- [ ] **Step 7: Run all portal test files together to confirm no regression**

Run: `php artisan test tests/Feature/Portal/`
Expected: PASS across `PortalLoginRequestTest.php`, `PortalLoginVerifyTest.php`, `PortalDashboardTest.php`.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/Web/Portal/PortalDashboardController.php resources/views/portal/dashboard.blade.php routes/web.php tests/Feature/Portal/PortalDashboardTest.php
git commit -m "feat(portal): add the real, Account-scoped, read-only client dashboard"
```

---

### Task 5: Full suite + Deptrac verification

**Files:** none (verification only)

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: all tests pass, including this plan's new tests (~2 in Task 1, ~5 in Task 2, ~8 in Task 3, ~3 in Task 4 — roughly +18 over the pre-Phase-6 baseline of 1385 passed).

- [ ] **Step 2: Run Deptrac**

Run: `composer deptrac`
Expected: `Violations 0`. `Web\Portal\PortalAuthController`/`Web\Portal\PortalDashboardController` depend only on Models (`Account`, `PortalLoginToken`, `Opportunity`, `Project`, `DesignItem`, `Document`, `Contract`, `ContractPayment`) — already an allowed `WebControllers → Models` edge. `EnsurePortalAccountAuthenticated` depends only on `Account`/`Tenant` (Models) — middleware isn't a layer `deptrac.yaml` currently tracks separately from other classes' dependencies, so this should not introduce a new violation category. If a violation appears, it means a dependency was drawn in the wrong direction — fix the direction, don't add a `skip_violations` entry.

- [ ] **Step 3: Manually verify the login email actually renders**

Run: `php artisan tinker --execute="echo (new \App\Mail\PortalLoginLinkEmail(\App\Models\Tenant::factory()->make(['slug' => 'preview']), 'preview-token'))->render();" 2>&1 | tail -20`
Expected: valid HTML output containing the login URL, no PHP errors. (This is a lightweight manual smoke check of the Mailable's `content()`/view wiring — not a substitute for Task 2's automated test, which already covers the email being queued/sent correctly.)

- [ ] **Step 4: Commit (if any fixes were needed in prior steps)**

```bash
git add -A
git commit -m "test(portal): confirm full suite and Deptrac are green for Phase 6"
```

(Skip this commit if steps 1-3 required no changes.)

---

## Self-Review Notes

**Spec coverage check** (against `docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md`, Phase 6 section as revised through §11):
- Magic-link auth (no password), Account-level visibility scope (all Projects under the Account, no per-contact differentiation), data retention explicitly deferred (documented in the spec, not built or silently dropped in this plan) — all covered.
- New `client` guard, no `rbac:*` reuse, tenant identified via `Tenant.slug` in the URL, no account-existence enumeration, single-use hashed short-lived tokens, defense-in-depth tenant check in the auth middleware — all covered across Tasks 1-3.
- Content: `DesignItem.review_status`, `Document` where `status = 'approved'` (corrected from the spec's originally-guessed `'final'`), quote/contract summary, outstanding `ContractPayment` balance — all covered in Task 4.

**Placeholder scan:** no "TBD"/"TODO"/"add appropriate X" phrases in any step above; every step has complete, real code. Two literal placeholder route closures exist by design, each an intentional, explicitly-labeled bridge between two tasks' TDD cycles, not an unfinished requirement: (1) Task 2's `login.verify` → `fn () => abort(404)`, needed only so `PortalLoginLinkEmail`'s constructor can resolve the route name before Task 3 exists — replaced by Task 3 Step 6; (2) Task 3's `dashboard` → a literal string closure — replaced by Task 4 Step 5. Both replacements are shown explicitly in their respective task's steps, not left implicit.

**Type/signature consistency check:** `Account::where('tenant_id', ...)->where('email', ...)` (Task 2) and `Account::query()->where('tenant_id', $tenant->id)->find(...)` (Task 3) both scope by `tenant_id` identically. `PortalLoginToken`'s `token_hash`/`expires_at`/`used_at` fields (Task 1) are written identically in Task 2's `issueAndSendLoginToken()` and read identically in Task 3's `verify()`. The `EnsurePortalAccountAuthenticated` middleware alias (`portal.auth`, Task 3) is applied identically in Task 3's placeholder route group and Task 4's real route replacement — same group, same alias, no drift. `Opportunity::whereNotNull('converted_project_id')` (the "has this Opportunity actually become a Project" check) is used identically in Task 4's controller and mirrors the exact same field this whole roadmap's Phase 4 already established as the sole Account→Project link.
