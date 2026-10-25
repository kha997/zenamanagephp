---
work_id: GAP-017
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-017/02-design.md
---

# GAP-017 — Invitation decline link: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-017/02-design.md`).

1. **Test first** — add `tests/Feature/Web/InvitationDeclineRouteRemovedTest.php` (both decline URLs return 404, no
   `invitations.decline` route, `invitations.accept` still exists) and show it fails on the base.
2. **Routes** — remove the decline route and the `/invite/decline/{token}` redirect from `routes/web.php`, and the
   matching entry from `legacy-map.json`.
3. **Dead code** — delete `app/Http/Controllers/Web/InvitationController.php`, the `invitations/index`, `create` and
   `manage` views, and that controller's `phpstan-baseline.neon` entries.
4. **Verify** — the new test passes; `route:list` differs only by the two routes; `--filter=Invitation`, PHPStan,
   php-cs-fixer, exact-head CI, governance and docs lints.
