# Phase 7 (Use Case 1) — AI Lead Conversion Suggestion Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a manual "Gợi ý AI" (AI Suggestion) button to the lead-conversion form in the CRM Lead Inbox. Clicking it sends only `Lead.project_description` to Anthropic's Messages API (Claude Haiku 4.5) via a forced tool-use call, and pre-fills the `service_category` and `service_scope_summary` fields in the conversion form with the model's suggestion — which the sales user can freely edit before submitting. The AI never writes to the database directly; it only pre-fills form fields the human still submits.

**Architecture:** A new `App\Services\AiAssistService` wraps `https://api.anthropic.com/v1/messages` directly via Laravel's `Http` facade (same HTTP-client-wrapping pattern as `ZenaBoqIntegrationService`), using Claude's forced `tool_choice` mechanism so the model's response is always a validated, schema-constrained tool call rather than freeform text to parse. A new `POST /operator/crm/leads/{id}/suggest-conversion` JSON endpoint on `Web\CrmPageController` (gated by both `crm.manage` and a new `ai.suggest` permission) calls the service and returns the suggestion as JSON. A new vanilla-JS module (`resources/js/ai-lead-suggest.js`, following the existing `money-format.js` IIFE + `data-*` attribute convention — no reactive framework in this codebase) wires the button to `fetch()` the endpoint and populate the two form fields. On any failure (missing API key, network error, invalid/malformed model output), the service returns `null` and the UI shows a plain "Không thể tạo gợi ý lúc này" message — the form remains fully usable without AI.

**Tech Stack:** Laravel 12 (`Http` facade, `config/ai.php`), Anthropic Messages API (model `claude-haiku-4-5-20251001`, forced tool-use for structured output), vanilla JS (IIFE module, `fetch`, CSRF meta tag), Blade.

## Global Constraints

- **Data minimization (spec-mandated):** the ONLY data ever sent to the Anthropic API is `Lead.project_description`. No contact info, no account data, no tenant name, no other Lead fields. Tests must assert this explicitly (inspect the exact JSON body sent via `Http::fake` request recording).
- **Never trust the AI blindly (spec-mandated):** the returned `service_category` MUST be checked against `Opportunity::VALID_SERVICE_CATEGORIES` (`['architecture', 'interior', 'landscape', 'structure', 'mep', 'construction', 'inspection', 'consulting', 'combined_package']`, `app/Models/Opportunity.php:67-70`) before being trusted. If invalid or missing, discard the suggestion (return `null` from the service — do not partially trust the response).
- **Graceful degradation (spec-mandated):** any API failure (missing config, network error, non-2xx, malformed/invalid tool-use output) must never throw an exception to the controller or break the page. The service returns `?array` (nullable), never throws.
- **Trigger is a manual button, not automatic** — the endpoint is only ever called on explicit user click, never on page load or on every keystroke.
- **Model:** `claude-haiku-4-5-20251001`, configurable via `config('ai.model')` / `AI_MODEL` env var (do not hardcode the literal string outside the config default).
- **Scope:** Use Case 1 only (lead → conversion-form suggestion). Use Cases 2 and 3 from the original spec are explicitly out of scope for this plan.
- **New permission `ai.suggest`** must be added to `ZenaPermissionsSeeder::CANONICAL_PERMISSIONS` (auto-granted to admin roles via the existing dynamic `ZenaAdminRolePermissionSeeder` — no separate edit needed there).
- **`declare(strict_types=1)`** at the top of every new PHP file, matching the rest of the codebase.

---

### Task 1: `AiAssistService` + `config/ai.php`

**Files:**
- Create: `config/ai.php`
- Create: `app/Services/AiAssistService.php`
- Test: `tests/Unit/AiAssistServiceTest.php`

**Interfaces:**
- Produces: `App\Services\AiAssistService::suggestLeadConversion(string $projectDescription): ?array` — returns `['service_category' => string, 'scope_summary' => string]` or `null` on any failure/invalid output. This is the only public method later tasks depend on.

- [ ] **Step 1: Create the config file**

```php
<?php declare(strict_types=1);

return [
    // Anthropic API key. Leave unset in any environment where AI suggestions
    // should be disabled — AiAssistService fails closed (returns null) when empty.
    'anthropic_api_key' => env('ANTHROPIC_API_KEY'),

    // Model used for lead-conversion suggestions (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 7).
    'model' => env('AI_MODEL', 'claude-haiku-4-5-20251001'),
];
```

Save as `config/ai.php`.

- [ ] **Step 2: Write the failing unit tests**

Create `tests/Unit/AiAssistServiceTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AiAssistService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AiAssistServiceTest extends TestCase
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    protected function setUp(): void
    {
        parent::setUp();
        config(['ai.anthropic_api_key' => 'test-key', 'ai.model' => 'claude-haiku-4-5-20251001']);
    }

    public function test_returns_suggestion_on_valid_tool_use_response(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [
                    [
                        'type' => 'tool_use',
                        'name' => 'suggest_lead_conversion',
                        'input' => [
                            'service_category' => 'interior',
                            'scope_summary' => 'Thiết kế nội thất căn hộ 2 phòng ngủ.',
                        ],
                    ],
                ],
            ], 200),
        ]);

        $result = (new AiAssistService())->suggestLeadConversion('Can can ho 2 phong ngu can thiet ke noi that');

        $this->assertSame([
            'service_category' => 'interior',
            'scope_summary' => 'Thiết kế nội thất căn hộ 2 phòng ngủ.',
        ], $result);
    }

    public function test_sends_only_project_description_as_message_content(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'architecture', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        (new AiAssistService())->suggestLeadConversion('Nha pho 5x20, 3 tang, khu Binh Chanh');

        Http::assertSent(function ($request) {
            $body = $request->data();
            $messages = $body['messages'];

            $this->assertCount(1, $messages);
            $this->assertSame('user', $messages[0]['role']);
            $this->assertSame('Nha pho 5x20, 3 tang, khu Binh Chanh', $messages[0]['content']);

            // No other Lead/Account/tenant field anywhere in the request body.
            $encoded = json_encode($body);
            $this->assertStringNotContainsString('tenant', strtolower((string) $encoded));
            $this->assertStringNotContainsString('contact_hint', (string) $encoded);
            $this->assertStringNotContainsString('account', strtolower((string) $encoded));

            return true;
        });
    }

    public function test_returns_null_when_api_key_missing(): void
    {
        config(['ai.anthropic_api_key' => null]);

        $result = (new AiAssistService())->suggestLeadConversion('Nha pho 5x20');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_when_project_description_blank(): void
    {
        $result = (new AiAssistService())->suggestLeadConversion('   ');

        $this->assertNull($result);
        Http::assertNothingSent();
    }

    public function test_returns_null_on_non_successful_response(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'rate limited'], 429)]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_connection_fails(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
        });

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_no_tool_use_block_present(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [['type' => 'text', 'text' => 'I cannot help with that.']],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_service_category_not_in_enum(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'not_a_real_category', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_returns_null_when_scope_summary_empty(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'architecture', 'scope_summary' => ''],
                ]],
            ], 200),
        ]);

        $this->assertNull((new AiAssistService())->suggestLeadConversion('Nha pho 5x20'));
    }

    public function test_uses_configured_model(): void
    {
        config(['ai.model' => 'claude-haiku-4-5-20251001']);

        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'architecture', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        (new AiAssistService())->suggestLeadConversion('Nha pho 5x20');

        Http::assertSent(function ($request) {
            $this->assertSame('claude-haiku-4-5-20251001', $request->data()['model']);

            return true;
        });
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=AiAssistServiceTest`
Expected: FAIL — `Class "App\Services\AiAssistService" not found`

- [ ] **Step 4: Implement `AiAssistService`**

```php
<?php declare(strict_types=1);

namespace App\Services;

use App\Models\Opportunity;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * AI-assisted lead-conversion suggestion (spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md, Phase 7, Use Case 1).
 * Data minimization: the only data ever sent to Anthropic is the raw Lead.project_description string —
 * no contact info, account data, or tenant identifiers. Never trust the response blindly: service_category
 * is re-validated against Opportunity::VALID_SERVICE_CATEGORIES before being returned.
 */
class AiAssistService
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';
    private const TOOL_NAME = 'suggest_lead_conversion';

    /**
     * @return array{service_category: string, scope_summary: string}|null
     */
    public function suggestLeadConversion(string $projectDescription): ?array
    {
        $apiKey = (string) config('ai.anthropic_api_key');

        if ($apiKey === '' || trim($projectDescription) === '') {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'x-api-key' => $apiKey,
                    'anthropic-version' => '2023-06-01',
                    'content-type' => 'application/json',
                ])
                ->post(self::ENDPOINT, [
                    'model' => (string) config('ai.model', 'claude-haiku-4-5-20251001'),
                    'max_tokens' => 512,
                    'tools' => [[
                        'name' => self::TOOL_NAME,
                        'description' => 'Suggest a service category and a short scope summary for a CRM opportunity converted from a lead.',
                        'input_schema' => [
                            'type' => 'object',
                            'properties' => [
                                'service_category' => [
                                    'type' => 'string',
                                    'enum' => Opportunity::VALID_SERVICE_CATEGORIES,
                                ],
                                'scope_summary' => [
                                    'type' => 'string',
                                    'description' => 'A short (1-2 sentence) Vietnamese scope summary suitable for a CRM opportunity record.',
                                ],
                            ],
                            'required' => ['service_category', 'scope_summary'],
                        ],
                    ]],
                    'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                    'messages' => [[
                        'role' => 'user',
                        'content' => $projectDescription,
                    ]],
                ]);

            if (!$response->successful()) {
                Log::warning('ai_assist.lead_suggestion_failed', ['status' => $response->status()]);

                return null;
            }

            return $this->extractSuggestion($response->json('content', []));
        } catch (Throwable $e) {
            Log::error('ai_assist.lead_suggestion_exception', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<int, mixed> $contentBlocks
     * @return array{service_category: string, scope_summary: string}|null
     */
    private function extractSuggestion(array $contentBlocks): ?array
    {
        $toolUse = null;
        foreach ($contentBlocks as $block) {
            if (is_array($block) && ($block['type'] ?? null) === 'tool_use') {
                $toolUse = $block;
                break;
            }
        }

        if ($toolUse === null || !isset($toolUse['input']) || !is_array($toolUse['input'])) {
            return null;
        }

        $category = (string) ($toolUse['input']['service_category'] ?? '');
        $summary = trim((string) ($toolUse['input']['scope_summary'] ?? ''));

        if (!in_array($category, Opportunity::VALID_SERVICE_CATEGORIES, true) || $summary === '') {
            Log::warning('ai_assist.lead_suggestion_invalid_output', ['service_category' => $category]);

            return null;
        }

        return ['service_category' => $category, 'scope_summary' => $summary];
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `php artisan test --filter=AiAssistServiceTest`
Expected: PASS (10/10)

- [ ] **Step 6: Commit**

```bash
git add config/ai.php app/Services/AiAssistService.php tests/Unit/AiAssistServiceTest.php
git commit -m "feat(ai): add AiAssistService wrapping Anthropic Messages API for lead-conversion suggestions"
```

---

### Task 2: `ai.suggest` permission + JSON suggestion endpoint

**Files:**
- Modify: `database/seeders/ZenaPermissionsSeeder.php` (add one entry to `CANONICAL_PERMISSIONS`)
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (new `suggestLeadConversion` method)
- Modify: `routes/web.php` (new route)
- Test: `tests/Feature/Zena/AiLeadSuggestionTest.php`

**Interfaces:**
- Consumes: `App\Services\AiAssistService::suggestLeadConversion(string $projectDescription): ?array` (Task 1).
- Produces: `POST /operator/crm/leads/{id}/suggest-conversion` → JSON `{"success": true, "data": {"service_category": string, "scope_summary": string}}` on success, or `{"success": false, "message": string}` (422/503) when no suggestion is available. Route name: `operator.crm.leads.suggest-conversion`.

- [ ] **Step 1: Add the permission entry**

In `database/seeders/ZenaPermissionsSeeder.php`, immediately after the `crm.convert` line (around line 170):

```php
        ['code' => 'crm.convert', 'module' => 'crm', 'action' => 'convert', 'description' => 'Convert won opportunities into projects'],
        ['code' => 'ai.suggest', 'module' => 'ai', 'action' => 'suggest', 'description' => 'Request AI-generated suggestions (e.g. lead-conversion service category and scope summary)'],
```

- [ ] **Step 2: Write the failing feature test**

Create `tests/Feature/Zena/AiLeadSuggestionTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use App\Http\Middleware\RoleBasedAccessControlMiddleware;
use App\Models\Lead;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Tests\Traits\TenantUserFactoryTrait;

class AiLeadSuggestionTest extends TestCase
{
    use RefreshDatabase;
    use TenantUserFactoryTrait;

    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private Tenant $tenant;
    private User $user;
    private Lead $lead;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app['router']->aliasMiddleware('rbac', RoleBasedAccessControlMiddleware::class);
        config(['ai.anthropic_api_key' => 'test-key']);

        $this->tenant = Tenant::factory()->create();
        $this->user = $this->createTenantUser(
            $this->tenant,
            [],
            ['admin'],
            ['crm.view', 'crm.manage', 'ai.suggest']
        );

        $this->lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Chi Lan - 090xxxxxxx',
            'project_description' => 'Can ho 2 phong ngu can thiet ke noi that',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);
    }

    public function test_returns_suggestion_for_authorized_user(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'interior', 'scope_summary' => 'Thiết kế nội thất căn hộ.'],
                ]],
            ], 200),
        ]);

        $response = $this->actingAs($this->user)
            ->postJson(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                ['X-Tenant-ID' => (string) $this->tenant->id]
            );

        $response->assertOk()->assertJson([
            'success' => true,
            'data' => ['service_category' => 'interior', 'scope_summary' => 'Thiết kế nội thất căn hộ.'],
        ]);
    }

    public function test_sends_only_project_description_to_anthropic(): void
    {
        Http::fake([
            self::ENDPOINT => Http::response([
                'content' => [[
                    'type' => 'tool_use',
                    'name' => 'suggest_lead_conversion',
                    'input' => ['service_category' => 'interior', 'scope_summary' => 'Tóm tắt.'],
                ]],
            ], 200),
        ]);

        $this->actingAs($this->user)
            ->postJson(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                ['X-Tenant-ID' => (string) $this->tenant->id]
            );

        Http::assertSent(function ($request) {
            $this->assertSame('Can ho 2 phong ngu can thiet ke noi that', $request->data()['messages'][0]['content']);

            return true;
        });
    }

    public function test_returns_503_when_ai_service_unavailable(): void
    {
        Http::fake([self::ENDPOINT => Http::response(['error' => 'down'], 500)]);

        $response = $this->actingAs($this->user)
            ->postJson(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                ['X-Tenant-ID' => (string) $this->tenant->id]
            );

        $response->assertStatus(503)->assertJson(['success' => false]);
    }

    public function test_denied_without_ai_suggest_permission(): void
    {
        $salesUser = $this->createTenantUser($this->tenant, [], ['sales'], ['crm.view', 'crm.manage']);

        $response = $this->actingAs($salesUser)
            ->postJson(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                ['X-Tenant-ID' => (string) $this->tenant->id]
            );

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_denied_without_crm_manage_permission(): void
    {
        $viewer = $this->createTenantUser($this->tenant, [], ['viewer'], ['crm.view', 'ai.suggest']);

        $response = $this->actingAs($viewer)
            ->postJson(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                ['X-Tenant-ID' => (string) $this->tenant->id]
            );

        $response->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_returns_404_for_lead_in_another_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = $this->createTenantUser($otherTenant, [], ['admin'], ['crm.view', 'crm.manage', 'ai.suggest']);

        $response = $this->actingAs($otherUser)
            ->postJson(
                route('operator.crm.leads.suggest-conversion', $this->lead->id),
                [],
                ['X-Tenant-ID' => (string) $otherTenant->id]
            );

        $response->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_requires_authentication(): void
    {
        $this->postJson(route('operator.crm.leads.suggest-conversion', $this->lead->id))
            ->assertRedirect();
    }
}
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `php artisan test --filter=AiLeadSuggestionTest`
Expected: FAIL — route `operator.crm.leads.suggest-conversion` not defined

- [ ] **Step 4: Add the route**

In `routes/web.php`, immediately after the existing `crm.leads.discard` route (line 972):

```php
    Route::post('/crm/leads/{id}/suggest-conversion', [App\Http\Controllers\Web\CrmPageController::class, 'suggestLeadConversion'])->middleware(['rbac:crm.manage', 'rbac:ai.suggest'])->name('crm.leads.suggest-conversion');
```

- [ ] **Step 5: Implement `suggestLeadConversion` on `CrmPageController`**

Add `use App\Models\Lead;` is already imported. Add `use App\Services\AiAssistService;` and `use Illuminate\Http\JsonResponse;` to the existing import list at the top of `app/Http/Controllers/Web/CrmPageController.php`, then add this method after `discardLead` (after line 134):

```php
    public function suggestLeadConversion(Request $request, string $id, AiAssistService $aiAssistService): JsonResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $lead = Lead::query()->forTenant($tenantId)->whereKey($id)->first();

        if (!$lead instanceof Lead) {
            return response()->json(['success' => false, 'message' => 'Lead not found'], 404);
        }

        $suggestion = $aiAssistService->suggestLeadConversion((string) $lead->project_description);

        if ($suggestion === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo gợi ý lúc này.',
            ], 503);
        }

        return response()->json(['success' => true, 'data' => $suggestion]);
    }
```

- [ ] **Step 6: Run the tests to verify they pass**

Run: `php artisan test --filter=AiLeadSuggestionTest`
Expected: PASS (7/7)

- [ ] **Step 7: Run the permission seeder tests to check nothing broke**

Run: `php artisan test --filter=Permission`
Expected: PASS (existing permission-seeding tests still green; `ai.suggest` now included since `ZenaAdminRolePermissionSeeder` dynamically grants every `CANONICAL_PERMISSIONS` code)

- [ ] **Step 8: Commit**

```bash
git add database/seeders/ZenaPermissionsSeeder.php app/Http/Controllers/Web/CrmPageController.php routes/web.php tests/Feature/Zena/AiLeadSuggestionTest.php
git commit -m "feat(crm): add ai.suggest permission and AI lead-conversion suggestion endpoint"
```

---

### Task 3: Conversion form fields + "Gợi ý AI" button + JS module

**Files:**
- Modify: `app/Http/Controllers/Api/LeadController.php` (`convert()` — accept `service_scope_summary`)
- Modify: `app/Http/Controllers/Web/CrmPageController.php` (`convertLead()` — pass through `service_scope_summary`)
- Modify: `resources/views/crm/leads.blade.php` (new fields + button)
- Create: `resources/js/ai-lead-suggest.js`
- Modify: `resources/views/layouts/operator.blade.php` (load the new module)
- Modify: `vite.config.js` (register the new entry)
- Test: extend `tests/Feature/Zena/OperatorCrmUiTest.php`

**Interfaces:**
- Consumes: `POST operator.crm.leads.suggest-conversion` (Task 2) — JSON `{success, data: {service_category, scope_summary}}`.
- Produces: form field `service_scope_summary` now flows through `convertLead()` → `Api\LeadController::convert()` → `Opportunity.service_scope_summary` (previously hardcoded to `$lead->project_description`, now defaults to it but is overridable).

- [ ] **Step 1: Write the failing feature test for the new form field**

In `tests/Feature/Zena/OperatorCrmUiTest.php`, add this test method (after `test_crm_ui_full_flow_lead_to_project`):

```php
    public function test_lead_conversion_accepts_custom_scope_summary(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Chi Mai',
            'project_description' => 'Mo ta goc tu Lead',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $convert = $this->actingAs($this->user)
            ->post(route('operator.crm.leads.convert', $lead->id), [
                'account_name' => 'Chi Mai',
                'opportunity_name' => 'Nha Chi Mai',
                'service_category' => 'interior',
                'service_scope_summary' => 'Tom tat da chinh sua boi sale, khac voi mo ta goc.',
            ], $headers);

        $convert->assertRedirect(route('operator.crm.index'));

        $opportunity = Opportunity::query()->where('opportunity_name', 'Nha Chi Mai')->firstOrFail();
        $this->assertSame('Tom tat da chinh sua boi sale, khac voi mo ta goc.', $opportunity->service_scope_summary);
    }

    public function test_lead_conversion_falls_back_to_project_description_without_scope_summary(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        $lead = Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Anh Nam',
            'project_description' => 'Mo ta goc khong bi ghi de',
            'source' => 'zalo',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->post(route('operator.crm.leads.convert', $lead->id), [
                'account_name' => 'Anh Nam',
                'opportunity_name' => 'Nha Anh Nam',
                'service_category' => 'architecture',
            ], $headers);

        $opportunity = Opportunity::query()->where('opportunity_name', 'Nha Anh Nam')->firstOrFail();
        $this->assertSame('Mo ta goc khong bi ghi de', $opportunity->service_scope_summary);
    }

    public function test_leads_page_shows_ai_suggest_button(): void
    {
        $headers = ['X-Tenant-ID' => (string) $this->tenant->id];

        Lead::query()->create([
            'tenant_id' => (string) $this->tenant->id,
            'contact_hint' => 'Anh Duc',
            'project_description' => 'Nha xuong 200m2',
            'source' => 'hotline',
            'status' => Lead::STATUS_NEW,
            'captured_by' => (string) $this->user->id,
        ]);

        $this->actingAs($this->user)
            ->get(route('operator.crm.leads'), $headers)
            ->assertOk()
            ->assertSee('Gợi ý AI')
            ->assertSee('service_scope_summary', false);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `php artisan test --filter=OperatorCrmUiTest`
Expected: FAIL — `test_lead_conversion_accepts_custom_scope_summary` fails because `service_scope_summary` is not yet accepted; `test_leads_page_shows_ai_suggest_button` fails because the button/field don't exist yet.

- [ ] **Step 3: Update `Api\LeadController::convert()` to accept `service_scope_summary`**

In `app/Http/Controllers/Api/LeadController.php`, in the `$validator = Validator::make(...)` call inside `convert()` (around line 212-223), add one rule after `service_category`:

```php
        $validator = Validator::make($request->all(), [
            'account_id' => [
                'nullable',
                'string',
                Rule::exists('accounts', 'id')->where('tenant_id', $tenantId),
            ],
            'account_name' => ['required_without:account_id', 'nullable', 'string', 'max:255'],
            'account_type' => ['nullable', Rule::in(Account::VALID_TYPES)],
            'opportunity_name' => ['required', 'string', 'max:255'],
            'service_category' => ['nullable', Rule::in(Opportunity::VALID_SERVICE_CATEGORIES)],
            'service_scope_summary' => ['nullable', 'string', 'max:2000'],
            'estimated_fee' => ['nullable', 'numeric', 'min:0'],
        ]);
```

Then, in the same method's `Opportunity::query()->create([...])` call (around line 241-251), change the `service_scope_summary` line:

```php
            $opportunity = Opportunity::query()->create([
                'tenant_id' => $tenantId,
                'account_id' => (string) $account->id,
                'opportunity_name' => (string) $request->input('opportunity_name'),
                'service_category' => (string) $request->input('service_category', 'architecture'),
                'service_scope_summary' => $request->input('service_scope_summary', $lead->project_description),
                'pipeline_stage' => Opportunity::STAGE_NEW_LEAD,
                'estimated_fee' => $request->input('estimated_fee'),
                'sales_owner_id' => (string) $user->id,
                'created_by' => (string) $user->id,
            ]);
```

- [ ] **Step 4: Update `Web\CrmPageController::convertLead()` to pass the new field through**

In `app/Http/Controllers/Web/CrmPageController.php`, in `convertLead()` (around line 102-121), add `service_scope_summary` to the `$request->validate([...])` call:

```php
    public function convertLead(Request $request, string $id, ApiLeadController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'account_id' => ['nullable', 'string'],
            'account_name' => ['nullable', 'string', 'max:255'],
            'opportunity_name' => ['required', 'string', 'max:255'],
            'service_category' => ['nullable', 'string'],
            'service_scope_summary' => ['nullable', 'string', 'max:2000'],
            'estimated_fee' => ['nullable', 'numeric', 'min:0'],
        ]);
```

The rest of the method (the `$apiController->convert(...)` call and error handling) is unchanged — `array_filter($validated, ...)` already forwards every present key.

- [ ] **Step 5: Run the tests to verify the scope-summary tests pass**

Run: `php artisan test --filter=OperatorCrmUiTest`
Expected: `test_lead_conversion_accepts_custom_scope_summary` and `test_lead_conversion_falls_back_to_project_description_without_scope_summary` now PASS. `test_leads_page_shows_ai_suggest_button` still FAILS (UI not added yet).

- [ ] **Step 6: Add the form fields and button to `resources/views/crm/leads.blade.php`**

Replace the conversion `<form>` block (lines 65-76) with:

```blade
                                    <form method="POST" action="{{ route('operator.crm.leads.convert', $lead->id) }}" class="mt-2 space-y-2" data-ai-lead-suggest-form data-lead-id="{{ $lead->id }}">
                                        @csrf
                                        <select name="account_id" class="operator-select">
                                            <option value="">Tạo khách hàng mới từ lead</option>
                                            @foreach ($accounts as $account)
                                                <option value="{{ $account->id }}">Gắn: {{ $account->display_name }}</option>
                                            @endforeach
                                        </select>
                                        <input name="account_name" type="text" class="operator-input" placeholder="Tên khách hàng (nếu tạo mới)" value="{{ $lead->contact_hint }}">
                                        <input name="opportunity_name" type="text" class="operator-input" placeholder="Tên cơ hội *" required>
                                        <select name="service_category" class="operator-select" data-ai-field="service_category">
                                            <option value="">Loại dịch vụ</option>
                                            @foreach (['architecture' => 'Kiến trúc', 'interior' => 'Nội thất', 'landscape' => 'Cảnh quan', 'structure' => 'Kết cấu', 'mep' => 'Cơ điện (MEP)', 'construction' => 'Thi công', 'inspection' => 'Giám sát', 'consulting' => 'Tư vấn', 'combined_package' => 'Trọn gói'] as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        <textarea name="service_scope_summary" class="operator-textarea" placeholder="Tóm tắt phạm vi (mặc định lấy từ mô tả lead)" data-ai-field="scope_summary"></textarea>
                                        <button type="button" class="operator-button operator-button-secondary" data-ai-suggest-trigger>Gợi ý AI</button>
                                        <span class="text-xs text-slate-500" data-ai-suggest-status></span>
                                        <button type="submit" class="operator-button operator-button-primary">Chuyển</button>
                                    </form>
```

- [ ] **Step 7: Create the JS module**

Create `resources/js/ai-lead-suggest.js`:

```javascript
/**
 * Nút "Gợi ý AI" trên form chuyển lead → cơ hội: gọi endpoint gợi ý AI
 * và điền sẵn service_category/service_scope_summary — người dùng vẫn
 * có thể sửa trước khi submit.
 */
(function () {
    'use strict';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function attach(form) {
        if (form.dataset.aiSuggestBound) return;
        form.dataset.aiSuggestBound = '1';

        var trigger = form.querySelector('[data-ai-suggest-trigger]');
        var statusEl = form.querySelector('[data-ai-suggest-status]');
        var categoryField = form.querySelector('[data-ai-field="service_category"]');
        var summaryField = form.querySelector('[data-ai-field="scope_summary"]');
        var leadId = form.dataset.leadId;

        if (!trigger || !leadId) return;

        trigger.addEventListener('click', function () {
            trigger.disabled = true;
            if (statusEl) statusEl.textContent = 'Đang tạo gợi ý...';

            fetch('/operator/crm/leads/' + encodeURIComponent(leadId) + '/suggest-conversion', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': csrfToken(),
                    'X-Requested-With': 'XMLHttpRequest',
                    Accept: 'application/json',
                },
            })
                .then(function (response) {
                    return response.json().then(function (body) {
                        return { ok: response.ok, body: body };
                    });
                })
                .then(function (result) {
                    if (!result.ok || !result.body.success) {
                        if (statusEl) statusEl.textContent = 'Không thể tạo gợi ý lúc này.';
                        return;
                    }

                    if (categoryField) categoryField.value = result.body.data.service_category;
                    if (summaryField) summaryField.value = result.body.data.scope_summary;
                    if (statusEl) statusEl.textContent = 'Đã điền gợi ý — bạn có thể chỉnh sửa.';
                })
                .catch(function () {
                    if (statusEl) statusEl.textContent = 'Không thể tạo gợi ý lúc này.';
                })
                .finally(function () {
                    trigger.disabled = false;
                });
        });
    }

    function init() {
        document.querySelectorAll('[data-ai-lead-suggest-form]').forEach(attach);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
```

- [ ] **Step 8: Register the new JS module in Vite and the operator layout**

In `vite.config.js`, add `'resources/js/ai-lead-suggest.js'` to the existing `input` array:

```javascript
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js'],
```

In `resources/views/layouts/operator.blade.php`, line 10, add it to the `@vite([...])` call:

```blade
    @vite(['resources/css/operator.css', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js'])
```

- [ ] **Step 9: Run the full CRM UI test file to verify all pass**

Run: `php artisan test --filter=OperatorCrmUiTest`
Expected: PASS (all methods, including the 3 new ones)

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/Api/LeadController.php app/Http/Controllers/Web/CrmPageController.php resources/views/crm/leads.blade.php resources/js/ai-lead-suggest.js resources/views/layouts/operator.blade.php vite.config.js tests/Feature/Zena/OperatorCrmUiTest.php
git commit -m "feat(crm): add service_scope_summary field and Gợi ý AI button to lead-conversion form"
```

---

### Task 4: Full suite + Deptrac verification

**Files:** None (verification only).

- [ ] **Step 1: Run the full test suite**

Run: `php artisan test`
Expected: All tests pass (baseline was 1403 passed, 11 skipped, 0 failed before this phase; expect that plus the ~20 new tests added across Tasks 1-3, still 0 failed).

- [ ] **Step 2: Run Deptrac**

Run: `vendor/bin/deptrac analyse --no-cache`
Expected: No new violations. `AiAssistService` lives in `app/Services/`, consumed only by `app/Http/Controllers/Web/CrmPageController.php` — same layer relationship as the existing `ZenaBoqIntegrationService` usage, so no boundary changes are expected.

- [ ] **Step 3: If either step fails, fix and re-run**

Do not proceed to the final review until both Step 1 and Step 2 are clean.

---

## Post-plan notes for the controller (not a task — read before dispatching)

- Task 2's endpoint intentionally does **not** call `AiAssistService` with any Lead field other than `project_description` — this is the data-minimization boundary from the spec and must not be widened without a fresh brainstorm.
- The `ai.suggest` + `crm.manage` dual-permission gate on the route mirrors the Phase 4 precedent (`crm.convert` + `contract.create` layering) — a reviewer may ask "why two permissions for one button"; the answer is intentional (separates "can touch this lead" from "can invoke paid AI calls").
- Use Cases 2 and 3 remain out of scope; do not extend `AiAssistService` with new methods as part of this plan.
