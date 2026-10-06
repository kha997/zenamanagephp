---
work_id: GAP-063
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-063/02-design.md
---

# GAP-063 — Treasury S1 foundation: implementation plan

Executes the approved Gate 2 Option A (`docs/owner-decisions/GAP-063/02-design.md`).
TDD order; each step's tests written before its code.

1. **Permissions.** Add 16 `treasury.*` codes to `ZenaPermissionsSeeder` and
   `ZenaRbacSeeder`; new `TreasuryRolePermissionSeeder` (alias-based,
   case-insensitive, additive) called from `DatabaseSeeder` and
   `ZenaRbacSeeder`. Test: `tests/Feature/Treasury/TreasuryRolePermissionSeederTest.php`.
2. **Access rule.** `App\Policies\TreasuryPolicy` + four gate abilities in
   `AuthServiceProvider`; membership = active `project_user_roles` row.
   Test: `TreasuryPolicyTest`.
3. **Setup rules.** `App\Services\Treasury\TreasurySetupService` — party/wallet
   types (PR #245 §7.1/§7.2), same-tenant validation, immutable wallet
   `project_id`, delete only when unreferenced (every FK column from the v17
   schema). Shared by API and web so both enforce identical rules.
4. **API.** `Api\Treasury\TreasuryPartyController`,
   `Api\Treasury\TreasuryWalletController`; routes in `routes/api_zena.php`.
   Factories for the two models (SSOT lint). Test: `TreasurySetupApiTest`.
5. **Web.** `Web\Treasury\TreasuryPageController`, views in
   `resources/views/treasury/`, two nav items + icons, "Ngân quỹ" link on the
   project page. Test: `TreasuryWebPagesTest`.
6. **Verification.** Treasury, navigation, architecture, seeder, RBAC,
   governance and Zena suites; `composer ssot:lint`; exact-head CI.
