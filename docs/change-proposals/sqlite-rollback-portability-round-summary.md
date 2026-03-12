# SQLite Rollback Portability / Idempotency Round Summary

## Outcome
- `ProjectCreateCanaryTest` currently passes end-to-end (`1 test, 12 assertions`) in the validated Dusk flow.
- The teardown-only SQLite rollback blockers in this round were resolved through controlled migration `down()` hardening.

## Migrations Patched In This Chain
- `database/migrations/2026_02_20_100000_add_token_hash_columns_to_invitations_table.php`
- `database/migrations/2026_02_20_090000_add_team_tenant_columns_to_invitations_table.php`
- `database/migrations/2026_02_12_120000_add_tenant_id_to_notification_rules_table.php`
- `database/migrations/2026_02_11_000000_add_impact_metrics_to_change_requests_table.php`
- `database/migrations/2026_02_09_020000_add_visibility_and_linked_entities_to_documents.php`
- `database/migrations/2026_02_06_120000_fix_documents_project_foreign_key.php`
- `database/migrations/2026_02_05_000000_add_tenant_id_to_zena_submittals_table.php`
- `database/migrations/2026_02_02_090000_add_documents_creator_columns.php`
- `database/migrations/2026_01_30_000001_add_zena_fields_to_audit_logs.php`
- `database/migrations/2025_09_22_012453_optimize_project_activities_table_schema.php`
- `database/migrations/2025_09_22_012440_optimize_document_versions_table_schema.php`
- `database/migrations/2025_09_20_164912_add_missing_columns_to_task_assignments_table.php`
- `database/migrations/2025_09_20_132400_add_missing_fields_to_components_table.php`
- `database/migrations/2025_09_17_162520_add_created_by_to_projects_table.php`
- `database/migrations/2025_09_17_043659_add_tenant_id_to_task_dependencies_table.php`
- `database/migrations/2025_09_14_160418_add_tenant_id_to_zena_projects_table.php`
- `database/migrations/2025_09_17_043146_add_tenant_id_to_task_assignments_table.php`
- `database/migrations/2025_09_14_160450_add_parent_foreign_key_to_zena_components_table.php`
- `database/migrations/2025_09_16_084654_add_team_support_to_task_assignments_table.php`
- `database/migrations/2025_09_17_043044_add_missing_fields_to_tasks_table.php`

## Repeating Error Patterns Seen
- `dropForeign()` in `down()` on SQLite (unsupported by schema builder).
- Multiple `dropColumn()` / `renameColumn()` operations inside one `Schema::table()` modification.
- Missing idempotent handling around `dropIndex()` / `dropForeign()`.
- SQLite index edge cases (`already exists` / `no such index`) not handled consistently.

## Standard Rules For Future Migration `down()` Changes
- If driver is SQLite: do not call `dropForeign()`.
- Split risky schema operations into separate `Schema::table()` calls (especially column drops/renames).
- Wrap `dropIndex()` / `dropForeign()` in minimal idempotent handling (`try/catch` and/or guarded checks).
- Keep behavior unchanged for non-SQLite drivers except rollback resilience.

## Boundary Of This Work
- This round is rollback portability/idempotency debt reduction only.
- No app/domain runtime behavior changes were intended.
- No Dusk infrastructure changes were required for the passing canary path.
