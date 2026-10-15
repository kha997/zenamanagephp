---
work_id: GAP-079
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-14-gap-079-roleseeder-permission-name-evidence.md
  plan: docs/superpowers/plans/2026-10-14-gap-079-roleseeder-permission-name.md
  branch: docs/GAP-079-roleseeder-permission-name
  pr: https://github.com/kha997/zenamanagephp/pull/352
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T22:28:32+07:00"
  owner_response_reference: "Owner decision in-session on 2026-10-14, verbatim: 'APPROVE GAP-079 Gate 2 Option A'. Reviewed design head: 376917ccc768e185eddd721fe17208b15ae56d1d. Approves Option A and its exact allowlist (RoleSeeder sets name = code; new data-only migration 2026_10_14_000000_backfill_null_permission_names filling name = code where name IS NULL, down no-op; classifications.json entry 'expand'; remove the AUD-28 allowlist in AudChangeRequestPermissionSeedingTest; new PermissionNameBackfillMigrationTest; docs incl. marking AUD-28 fixed); not Gate 3, merge, release, or deployment."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T22:27:53+07:00"
  updated_at: "2026-10-14T22:28:32+07:00"
generated_by: agent
---

# GAP-079 — RoleSeeder permission name: Gate 2 design

## OWNER GATE 2: APPROVED — OPTION A

Owner approved Option A in-session on 2026-10-14 against reviewed design head
`376917ccc768e185eddd721fe17208b15ae56d1d`. This authorizes only the bounded implementation defined by this packet;
it does not authorize Gate 3, merge, release, or deployment.

## Owner Summary

Hai phương án:

- **A — sửa seeder + migration điền tên cho dữ liệu cũ (khuyến nghị):**
  `RoleSeeder` đặt `name = code` khi tạo quyền. Một migration chỉ sửa dữ
  liệu đặt `name = code` cho mọi dòng `permissions` đang có `name` NULL,
  nên database đã seed trước đây cũng được sửa. Test audit bỏ ngoại lệ.
- **B — chỉ sửa seeder:** database mới seed thì đúng; database cũ vẫn giữ 2
  dòng NULL cho tới khi ai đó sửa tay.

Cả hai giữ nguyên `user.manage` (xoá một dòng quyền có thể đang gán cho vai
trò là việc riêng). Đề xuất **Phương án A**.

## Design: Option A (exact allowlist)

1. `database/seeders/RoleSeeder.php`: in the permission loop, add
   `'name' => $permData['code']` to the create attributes. Roles and the
   permission list are unchanged.
2. New migration `database/migrations/2026_10_14_000000_backfill_null_permission_names.php`:
   `up()` runs one update — `permissions.name = permissions.code` where
   `name IS NULL` and `code IS NOT NULL` (guarded by `Schema::hasColumn`).
   `down()` is a no-op (the previous NULL values carry no information).
   `permissions.name` has no unique index, so this cannot conflict.
3. `database/migrations/classifications.json`: register the migration as
   `expand` (data-only, additive).
4. `tests/Feature/Audit/AudChangeRequestPermissionSeedingTest.php`: remove the
   AUD-28 allowlist; assert no permission has a NULL name after `db:seed`.
5. New test `tests/Feature/Seeders/PermissionNameBackfillMigrationTest.php`:
   insert a NULL-name permission, run the migration's `up()`, assert
   `name = code`; assert a row that already has a different name is untouched.
6. Docs: plan, this packet, 03-release; AUD-28 row in the audit index marked
   fixed by GAP-079.

## Verification

- Red first: the audit test without the allowlist fails on main
  (`project.read`, `user.manage`); passes after the change.
- The new migration test; Unit/Feature/Integration suites; PHPStan;
  governance, SSOT and docs lints; exact-head CI.

## Rollback

Revert the squash commit. Names already filled by the migration stay (they
equal the code, the same value `PermissionSeeder` writes).

## Out of scope

Changing name/code checks, merging seeders, deleting `user.manage`,
`Src\RBAC` permission creation without a name, deployment.

## Decision Needed

Owner chọn: `APPROVE GAP-079 Gate 2 Option A` (khuyến nghị) / Option B /
Request changes / Decline.

## What the owner is NOT being asked to decide

Gate 3, merge, release hay deploy.
