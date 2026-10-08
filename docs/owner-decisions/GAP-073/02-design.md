---
work_id: GAP-073
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-08-gap-073-phpstan-2-3-evidence.md
  plan: null
  branch: docs/GAP-073-phpstan-2-3
  pr: https://github.com/kha997/zenamanagephp/pull/346
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T22:16:46+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T22:16:46+07:00"
  updated_at: "2026-10-08T22:16:46+07:00"
generated_by: agent
---

# GAP-073 — PHPStan 2.3 and its 10 findings: Gate 2 design

## Owner Summary

Hai phương án; cả hai nâng PHPStan lên 2.3 và không che lỗi:

- **A — sửa code tại chỗ (khuyến nghị):** thêm dòng truy vấn bị thiếu cho
  `$dbConfig` (theo đúng mẫu của controller anh em
  `SimpleSidebarBuilderController`), thêm `use ($interactionLog)` cho 2 closure,
  bỏ `$validator` thừa trong 3 closure, đổi `set(..., 'EX', 60)` thành
  `setex(..., 60, ...)`. Xoá 2 dòng baseline tương ứng. Nhỏ, hành vi của code
  đang chạy không đổi.
- **B — xoá các class không được dùng:** xoá `Admin/BasicSidebarController`,
  `App\Http\Controllers\InteractionLogController` và các Request đi kèm, rồi sửa
  2 chỗ còn lại như A. Gọn hơn về lâu dài nhưng đụng nhiều file hơn và cần
  kiểm tra kỹ hơn rằng không còn chỗ nào tham chiếu.

Đề xuất **Phương án A**; dọn code chết có thể làm ở việc riêng.

## Design: Option A (exact allowlist)

1. `composer update phpstan/phpstan` (2.2.5 → 2.3.x, in-constraint; lockfile
   moves only `phpstan/phpstan`).
2. `app/Http/Controllers/Admin/BasicSidebarController.php` — in `show()` and
   `preview()`, define `$dbConfig = \App\Models\SidebarConfig::where('role_name',
   $role)->where('is_enabled', true)->first();` before it is used (same lookup
   as `SimpleSidebarBuilderController::show()`).
3. `app/Http/Requests/UpdateInteractionLogRequest.php` — add
   `use ($interactionLog)` to the `visibility` and `client_approved` closures
   (`client_approved` also guards a null `$interactionLog`).
4. `app/Http/Controllers/Api/App/SettingsController.php` — remove the unused
   `$validator` from the three `DB::transaction(function () use (...))`
   captures; nothing else changes.
5. `app/Services/HealthCheckService.php` — `$redis->setex($testKey, 60,
   $testValue)` instead of `set($testKey, $testValue, 'EX', 60)` (same effect:
   value with a 60-second TTL; valid for phpredis and predis).
6. `phpstan-baseline.neon` — remove the two now-fixed entries
   (`$dbConfig`, `$interactionLog`); no new entries.

## Verification

- Red first: PHPStan 2.3 on the unchanged code reports the 10 errors.
- After: PHPStan 2.3 clean; full Unit, Feature, Integration suites;
  `/api/.../settings` tests and health-check tests green; SSOT, governance,
  docs lints; exact-head CI green.
- Files: `composer.lock`, the 4 app files, `phpstan-baseline.neon`, governed
  plan, this packet and 03-release.

## Rollback

Revert the squash commit.

## Out of scope

Deleting unreachable classes, other baseline entries, majors, CI changes,
deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-073 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
