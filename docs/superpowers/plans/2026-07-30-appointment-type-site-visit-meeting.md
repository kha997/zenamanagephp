# Appointment Types: Site Visit & Meeting Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add two new CRM opportunity appointment types — `site_visit` (Tham quan) and `meeting` (Họp) — to the existing "Đặt lịch mới" form, alongside `consultation` and `survey`.

**Architecture:** Add two new constants to `OpportunityAppointment::VALID_TYPES` (model), and two new Vietnamese label entries to the `$appointmentTypeLabels` array in `opportunity-show.blade.php`. The dropdown and the appointments-table badge already iterate over `$appointmentTypeLabels`, and validation in `CrmPageController::storeAppointment` already derives its `in:` rule from `VALID_TYPES` — so no controller or route change is needed. Column `type` is `string(20)`, no migration needed.

**Tech Stack:** Laravel (PHP 8.2+ strict_types), Blade views, PHPUnit feature tests.

## Global Constraints

- New type values are English snake_case, matching existing `consultation`/`survey` convention: `site_visit`, `meeting`.
- No DB schema change (column is a plain `string(20)`, no DB-level enum).
- No new form fields — the existing `location` free-text input covers site-visit detail (e.g. "Công trình ABC" or "Trụ sở công ty").
- Vietnamese labels: `site_visit` → "Tham quan", `meeting` → "Họp".
- Do not change appointment status-transition logic, `outcome_notes` flow, or any route.

---

### Task 1: Add new type constants to the model

**Files:**
- Modify: `app/Models/OpportunityAppointment.php:32-38`
- Test: `tests/Feature/Models/OpportunityAppointmentModelTest.php`

**Interfaces:**
- Consumes: nothing new.
- Produces: `OpportunityAppointment::TYPE_SITE_VISIT = 'site_visit'`, `OpportunityAppointment::TYPE_MEETING = 'meeting'`, both included in `OpportunityAppointment::VALID_TYPES`. Later tasks (view, controller-driven validation) rely on `VALID_TYPES` containing these two new values.

- [ ] **Step 1: Write the failing test**

Add two new entries to the existing data provider in `tests/Feature/Models/OpportunityAppointmentModelTest.php` (method `appointmentTypeProvider`, currently lines 68-74):

```php
    public static function appointmentTypeProvider(): array
    {
        return [
            'consultation' => [OpportunityAppointment::TYPE_CONSULTATION],
            'survey' => [OpportunityAppointment::TYPE_SURVEY],
            'site_visit' => [OpportunityAppointment::TYPE_SITE_VISIT],
            'meeting' => [OpportunityAppointment::TYPE_MEETING],
        ];
    }
```

This feeds the existing `test_appointment_can_be_created_for_each_valid_type` test (lines 41-66) with the two new type values — no other change needed in that test method.

- [ ] **Step 2: Run test to verify it fails**

Run: `./vendor/bin/phpunit tests/Feature/Models/OpportunityAppointmentModelTest.php --filter test_appointment_can_be_created_for_each_valid_type`

Expected: FAIL — errors referencing undefined constants `OpportunityAppointment::TYPE_SITE_VISIT` and `OpportunityAppointment::TYPE_MEETING` (PHP fatal error: Undefined constant).

- [ ] **Step 3: Add the constants and update VALID_TYPES**

In `app/Models/OpportunityAppointment.php`, replace lines 32-38:

```php
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_SURVEY = 'survey';

    public const VALID_TYPES = [
        self::TYPE_CONSULTATION,
        self::TYPE_SURVEY,
    ];
```

with:

```php
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_SURVEY = 'survey';
    public const TYPE_SITE_VISIT = 'site_visit';
    public const TYPE_MEETING = 'meeting';

    public const VALID_TYPES = [
        self::TYPE_CONSULTATION,
        self::TYPE_SURVEY,
        self::TYPE_SITE_VISIT,
        self::TYPE_MEETING,
    ];
```

- [ ] **Step 4: Run test to verify it passes**

Run: `./vendor/bin/phpunit tests/Feature/Models/OpportunityAppointmentModelTest.php`

Expected: PASS (all tests in the file, including the 4 data-provider cases).

- [ ] **Step 5: Commit**

```bash
git add app/Models/OpportunityAppointment.php tests/Feature/Models/OpportunityAppointmentModelTest.php
git commit -m "feat(crm): add site_visit and meeting appointment types to model"
```

---

### Task 2: Add Vietnamese labels to the opportunity-show view

**Files:**
- Modify: `resources/views/crm/opportunity-show.blade.php:14-17`
- Test: `tests/Feature/OpportunityAppointmentLifecycleTest.php`

**Interfaces:**
- Consumes: `OpportunityAppointment::TYPE_SITE_VISIT`, `OpportunityAppointment::TYPE_MEETING`, `OpportunityAppointment::VALID_TYPES` (from Task 1).
- Produces: `$appointmentTypeLabels` array with 4 entries. The "Đặt lịch mới" `<select name="type">` (lines 211-217) and the appointments-table type column (line 143) both iterate/read this array, so both update automatically — no changes needed to those blocks.

- [ ] **Step 1: Write the failing test**

Add a new test method to `tests/Feature/OpportunityAppointmentLifecycleTest.php`, placed after `test_store_creates_scheduled_appointments_for_both_types` (after line 92):

```php
    public function test_store_creates_scheduled_appointments_for_site_visit_and_meeting(): void
    {
        $tenant = Tenant::factory()->create();
        ['user' => $user, 'opportunity' => $opportunity] = $this->makeOpportunity($tenant);
        $assignee = $this->createTenantUser($tenant, ['name' => 'Host'], ['sales'], ['crm.view', 'crm.manage']);

        $this->actingAs($user);

        foreach ([OpportunityAppointment::TYPE_SITE_VISIT, OpportunityAppointment::TYPE_MEETING] as $index => $type) {
            $response = $this->post(route('operator.crm.opportunities.appointments.store', $opportunity->id), [
                'type' => $type,
                'scheduled_at' => Carbon::now()->addDays($index + 1)->format('Y-m-d H:i:s'),
                'location' => $type === OpportunityAppointment::TYPE_SITE_VISIT ? 'Công trình ABC' : 'Phòng họp A',
                'assigned_to' => (string) $assignee->id,
            ]);

            $response->assertRedirect();
            $response->assertSessionHas('success');
        }

        $this->assertSame(2, OpportunityAppointment::query()
            ->where('opportunity_id', (string) $opportunity->id)
            ->where('status', OpportunityAppointment::STATUS_SCHEDULED)
            ->count());
    }
```

Also extend the existing label-rendering test `test_opportunity_show_renders_appointment_card_with_expected_labels_and_actions` (lines 266-296) to assert the two new labels render. Insert after line 282 (right after the `$completed` appointment block) and before the `$response = $this->actingAs($user)...` line:

```php
        $siteVisit = $this->makeAppointment($tenant, $opportunity, $user, OpportunityAppointment::STATUS_SCHEDULED, [
            'type' => OpportunityAppointment::TYPE_SITE_VISIT,
            'scheduled_at' => Carbon::now()->addDays(3),
            'assigned_to' => (string) $assignee->id,
        ]);
```

Then add assertions after the existing `$response->assertDontSee('>survey<', false);` line (line 292):

```php
        $response->assertSeeText('Tham quan');
        $response->assertDontSee('>site_visit<', false);
```

- [ ] **Step 2: Run tests to verify they fail**

Run: `./vendor/bin/phpunit tests/Feature/OpportunityAppointmentLifecycleTest.php --filter "test_store_creates_scheduled_appointments_for_site_visit_and_meeting|test_opportunity_show_renders_appointment_card_with_expected_labels_and_actions"`

Expected: FAIL — `test_store_creates_scheduled_appointments_for_site_visit_and_meeting` fails validation (422, `type` not in allowed list) since Task 1 already landed the model constants but the view/controller path is unaffected by that — actually validation already accepts these values after Task 1 (controller derives `in:` from `VALID_TYPES`), so this test should redirect successfully already. The label assertion test fails: `assertSeeText('Tham quan')` not found, because `$appointmentTypeLabels` doesn't have the `site_visit` key yet, so the badge renders the raw string `site_visit` instead.

- [ ] **Step 3: Add the labels**

In `resources/views/crm/opportunity-show.blade.php`, replace lines 14-17:

```php
    $appointmentTypeLabels = [
        'consultation' => 'Tư vấn',
        'survey' => 'Khảo sát',
    ];
```

with:

```php
    $appointmentTypeLabels = [
        'consultation' => 'Tư vấn',
        'survey' => 'Khảo sát',
        'site_visit' => 'Tham quan',
        'meeting' => 'Họp',
    ];
```

- [ ] **Step 4: Run tests to verify they pass**

Run: `./vendor/bin/phpunit tests/Feature/OpportunityAppointmentLifecycleTest.php`

Expected: PASS (all tests in the file).

- [ ] **Step 5: Commit**

```bash
git add resources/views/crm/opportunity-show.blade.php tests/Feature/OpportunityAppointmentLifecycleTest.php
git commit -m "feat(crm): show Tham quan and Họp labels for new appointment types"
```

---

### Task 3: Full regression pass

**Files:** none (verification only)

**Interfaces:**
- Consumes: everything from Tasks 1-2.
- Produces: nothing (final verification gate before considering the feature done).

- [ ] **Step 1: Run the full CRM-related test suite**

Run: `./vendor/bin/phpunit tests/Feature/OpportunityAppointmentLifecycleTest.php tests/Feature/Models/OpportunityAppointmentModelTest.php`

Expected: PASS, 0 failures.

- [ ] **Step 2: Run PHPStan on touched files**

Run: `./vendor/bin/phpstan analyse app/Models/OpportunityAppointment.php`

Expected: No new errors (baseline-clean).

- [ ] **Step 3: Manual smoke check (optional but recommended)**

Start the app locally, open an Opportunity's detail page, open "Đặt lịch mới", confirm the `Loại` dropdown now shows: Chọn loại / Tư vấn / Khảo sát / Tham quan / Họp, and that submitting `Tham quan` with a `Địa điểm` value creates a row whose badge reads "Tham quan" in the Lịch hẹn table.

No commit for this task (verification only, no file changes expected).
