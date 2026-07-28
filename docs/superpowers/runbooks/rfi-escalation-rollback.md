# RFI Escalation — Production Rollback Runbook

**Do not run any migration `down()` in production once real escalation data exists.** All migrations in this feature (`rfi_escalations`, `rfi_legacy_migration_confirmations`, `rfi_escalation_migration_state`, `current_escalation_id` on `rfis`) are additive; their `down()` methods exist only for local/test iteration before any real data is written. Running a `down()` in production is a schema rollback and is explicitly out of scope for this runbook — it is never the right response to an incident once real rows exist.

## What "rollback" means here

Rolling back this feature means reverting *application behavior* — new escalate/resolve/cancel actions, and the cutover's tightened status validation — while the `rfi_escalations`, `rfi_legacy_migration_confirmations`, and `rfi_escalation_migration_state` tables and all data in them stay exactly as they are. There is no scenario in this plan where rolling back means deleting or altering that data.

Two independent levers exist, and an incident may need one or both:

1. **The cutover flag** (`rfi_escalation_migration_state.cutover_completed_at`) — controls whether `RfiController::allowedStatusValues()` accepts the legacy `escalated`/`pending` status values on direct writes. This is a single command, safe to run at any time, fully reversible in both directions.
2. **Reachability of the new escalation routes/actions** (`escalate`, `resolve-escalation`, `cancel`) — controls whether operators can invoke the new lifecycle actions at all. This is operational (RBAC/deploy), not a single command, because it depends on how much of the feature needs to stop.

## Step-by-step

### 1. Diagnose which lever the incident needs

- If the problem is specifically that the cutover's tightened validation is rejecting writes it shouldn't (e.g. a legacy integration still posts `status=escalated` directly and was missed during the legacy-confirmation sweep), go to **Step 2 only**.
- If the problem is with the new escalation feature itself (bad data being written by `escalate()`/`resolveEscalation()`, a bug in `RfiLifecycleService`, notification spam, etc.) and operators need to stop using the new actions entirely while a fix ships, go to **Step 3** (optionally after Step 2).
- If the incident predates this feature's deploy entirely and a full code rollback to the previous release is the fastest safe path, go to **Step 5**.

### 2. Clear the cutover flag (single command, fully reversible)

```bash
php artisan rfi:escalation-rollback --reason="<why — link the incident/ticket>"
```

This:
- Sets `rfi_escalation_migration_state.cutover_completed_at` back to `NULL`.
- Makes `RfiController::allowedStatusValues()` accept `escalated`/`pending` again at the application layer (pre-cutover behavior).
- Logs a `rfi_escalation_cutover_rolled_back` warning entry with the supplied reason, for audit trail.
- Does **not** touch `rfi_escalations` or `rfi_legacy_migration_confirmations` in any way — it only ever writes to `rfi_escalation_migration_state`.

`--reason` is required; the command exits with code 1 and does nothing if it is omitted, so an accidental blank invocation cannot silently flip the flag without an audit trail.

Re-running `rfi:escalation-cutover` later (once the underlying issue is fixed and the legacy-confirmation sweep is re-verified via `rfi:escalation-preflight-report`) re-enables the tightened validation — this flag is designed to be flipped back and forth safely.

### 3. Stop the new escalation routes from being reachable (RBAC-level, no deploy needed)

If the cutover flag alone isn't enough — e.g. the new `escalate()`/`resolveEscalation()` actions themselves need to stop running — revoke the permissions that gate them rather than editing routes or code:

- Revoke `rfi.escalate` and `rfi.cancel` from every role that currently has them (check `ZenaAdminRolePermissionSeeder` and `ZenaProjectManagerRolePermissionSeeder` for the current grant list).
- This causes the `rbac:rfi.*` route middleware to reject all callers with a `403` on those actions, without touching routes, controllers, or the database schema.
- Re-granting the permissions afterward restores exactly the pre-incident behavior — this is also fully reversible.

Note: `respond()` and `close()` also route through `RfiLifecycleService` as of this feature (Tasks 8–9). If the incident is in the shared lifecycle service rather than something escalation-specific, revoking `rfi.escalate`/`rfi.cancel` alone will not isolate it — see Step 5 for a full code rollback instead.

### 4. Confirm no data loss

Before and after any rollback action, verify these counts are identical:

```sql
SELECT COUNT(*) FROM rfi_escalations;
SELECT COUNT(*) FROM rfi_legacy_migration_confirmations;
```

Neither `rfi:escalation-rollback` nor an RBAC permission change can change these counts. If they differ, the discrepancy has a cause unrelated to this rollback procedure and needs separate investigation — do not attribute it to the rollback.

### 5. Full code rollback (deploy the previous release)

If the incident requires reverting the application code itself — not just the cutover flag or route reachability — deploy the release that predates this feature's code.

That older code simply does not read `rfis.current_escalation_id` or the `rfi_escalations`/`rfi_legacy_migration_confirmations`/`rfi_escalation_migration_state` tables. They remain in the schema, unused but fully intact, until a future decision is made about them (e.g. a follow-up migration once the feature is confirmed dead — not part of any emergency rollback).

**Do not** drop these tables or run their migrations' `down()` methods as part of an emergency rollback, even at this step. A code-level rollback and a schema-level rollback are different operations with different risk profiles; this runbook only ever recommends the former.

## Summary of guarantees

| Action | Reverses cutover validation | Reverses new route reachability | Touches `rfi_escalations` / confirmation data |
|---|---|---|---|
| `rfi:escalation-rollback` | Yes | No | Never |
| Revoke `rfi.escalate`/`rfi.cancel` | No | Yes | Never |
| Deploy previous release | Yes (code doesn't check it) | Yes (routes don't exist) | Never |
| Migration `down()` | N/A | N/A | **Yes — do not run in production** |
