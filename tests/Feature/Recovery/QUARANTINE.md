# UAT-RECOVERY-01 — quarantined tests

Two tests in `UatRecovery01IdentityRbacTest` **fail today on purpose**: they encode the secure/expected behaviour for decisions the Owner has not taken yet. Their assertions are **unchanged**. They carry the PHPUnit group `uat-recovery-01-quarantine`, which `phpunit.xml` excludes from the ordinary run (exactly like the existing `performance` / `mysql-parity` groups). Nothing else in the Recovery suite is excluded.

**A quarantined test is not a passing test.** CI reports them as *not run*. `UatRecovery01QuarantineGuardTest` (normal CI) fails if the quarantine set is anything other than exactly these two, or if the exclusion/reference is removed.

| Test | Open decision (`docs/audits/UAT-RECOVERY-01/OWNER-DECISION-REGISTER.md`) | Remove the quarantine (and keep the test) when |
|---|---|---|
| `test_D2_user_with_only_seeded_project_manager_role_passes_api_gate` | O-2 / O-8 — meaning of the parameterless `rbac` gate | the Owner decides and implements the gate semantics for seeded DB roles |
| `test_D2_no_seeded_user_can_create_users_through_canonical_api` | O-2 / O-8 — legacy `users.role` vs DB roles | the Owner decides how seeded tenant admins obtain `user.create` |

## Diagnostic (non-blocking, always exit 0)
```bash
scripts/ci/run-recovery-quarantine-diagnostic.sh            # report -> storage/logs/recovery-quarantine-diagnostic.txt
./vendor/bin/phpunit --group uat-recovery-01-quarantine     # same, raw
```
Expected today: 2 tests, 2 failures. To release a test from quarantine: delete its `#[Group('uat-recovery-01-quarantine')]` line and its entry in the guard test, and (when the set is empty) the `<group>` line in `phpunit.xml`. Never edit the assertion to make it pass.

## History
The Owner approved quarantining four tests. While integrating onto `main` (`cb958ad48`), upstream GAP-098 started seeding the missing route permissions, so `test_D3_every_rbac_route_permission_exists_in_canonical_seed` now **passes unchanged**. It was released from quarantine and runs in ordinary CI as a regression guard; the other three still failed and stayed quarantined.

2026-11-24: the Owner decided O-18 (Option D, including Project Manager). With the seeder fix a single seed run grants Project Member `task.view`, so `test_D3_project_member_can_open_my_work` **passes unchanged** and was released (its removal condition was met). Its O-18 regression coverage is `UatRecovery05O18PermissionBaselineTest`.
