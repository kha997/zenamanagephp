# OWN-2026-024 (O-17h) — expected-RED security tests (quarantine manifest)

**State at H4 (2026-11-25): the quarantine is empty.** Every H1 test that failed on purpose now passes with its assertions **unchanged**, and runs in ordinary CI. `phpunit.xml` no longer excludes the group `uat-recovery-07-o17h-red`.

`UatRecovery07O17hQuarantineGuardTest` (ordinary CI) fails if:
- any test carries the group again;
- `phpunit.xml` excludes it again;
- a released test is deleted;
- a release below is no longer recorded.

A new expected-RED security test needs a new Owner gate. It must not be added silently.

- **H3:** the legacy `super_admin` middleware bypass is removed.
- **H4:** the `DocumentPolicy` and `SidebarConfigPolicy` role-name shortcuts are removed.

**Released:**

| Test | Was RED for | Released by | Note |
|---|---|---|---|
| `test_every_rbac_argument_is_a_real_permission_code` | 80 routes gated on the non-permission `rbac:admin` | H2a | `platform.*` re-gating; passes unchanged |
| `test_tenant_role_admin_cannot_self_grant_host_or_owner_authority` | RBAC-ESC-1 | H2b | protected-code rule + D5; passes unchanged |
| `test_reseeding_does_not_restore_a_deliberately_revoked_grant` | `production:rbac` restored a revoked Owner grant | H2b | revokes via the governed `rbac:permission revoke`; assertion unchanged; **Owner review requested** |
| `test_zero_grant_owner_is_denied_by_every_permission_gate` | M1 short-circuit | H3 | passes unchanged |
| `test_tenant_owner_cannot_reach_host_level_routes_by_role_name` | Owner reached 62 `platform.*` routes by role name | H3 | passes unchanged |
| `test_revoking_a_canonical_grant_from_the_owner_removes_access` | revoking `role.view` from the Owner changed nothing | H3 | passes unchanged |
| `test_zero_grant_owner_cannot_manage_roles_or_permissions` | zero-grant Owner assigned roles, created roles/permissions | H3 | passes unchanged |
| `test_zero_grant_owner_cannot_approve_a_change_request` | zero-grant Owner approved a change request | H3 | passes unchanged |
| `test_document_policy_does_not_bypass_canonical_grants` | `DocumentPolicy` granted by role name and legacy `users.role` | H4 | passes unchanged |
| `test_sidebar_config_policy_does_not_bypass_canonical_grants` | `SidebarConfigPolicy::before()` granted everything to the role name | H4 | passes unchanged |

## Diagnostic (non-blocking)

```bash
./vendor/bin/phpunit --group uat-recovery-07-o17h-red
```

Expected at H4: no tests executed.
