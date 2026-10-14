---
work_id: GAP-079
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-14-gap-079-roleseeder-permission-name-evidence.md
  plan: null
  branch: docs/GAP-079-roleseeder-permission-name
  pr: https://github.com/kha997/zenamanagephp/pull/352
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-14T22:27:53+07:00"
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-14T22:27:53+07:00"
  updated_at: "2026-10-14T22:27:53+07:00"
generated_by: agent
---

# GAP-079 — RoleSeeder permission name: Gate 2 design

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
