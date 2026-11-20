---
work_id: GAP-128
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-128/02-design.md
---

# GAP-128 — Retire the websocket server: implementation plan

Executes Gate 2 A, S-1, H-1 (`docs/owner-decisions/GAP-128/02-design.md`). Only files in the allowlist change.

1. **Red test** `tests/Feature/Deployment/WebSocketServerRetiredTest.php`:
   - `websocket:serve` is not in `Artisan::all()`;
   - `.github/workflows/production.yml` does not contain `zena-websocket`;
   - `docs/runbooks/gap-049-host-provisioning.md` grants no sudo `restart zena-websocket` and asks for no websocket unit.
   Run it: all three fail.
2. **Remove** `app/Console/Commands/WebSocketServerCommand.php`, `app/WebSocket/DashboardWebSocketHandler.php`,
   `scripts/start-websocket-server.sh`, and their `phpstan-baseline.neon` entries.
3. **Workflow** `production.yml`: delete the two `sudo systemctl restart zena-websocket` lines (cutover and auto-rollback).
4. **Runbooks**: drop the websocket unit and sudo line from `gap-049-host-provisioning.md`; add "Retiring the old
   zena-websocket unit" with the H-1 order (deploy first, then root disables/removes the unit and the sudo line); one
   pointer line in `go-live.md`.
5. **Verify**: the new test is green; `Tests\Feature\Deployment` and the Unit + Feature suites pass; PHPStan at exact head
   in CI; `git grep zena-websocket` only in history docs and the retirement section.
6. **Gate 3 packet** `docs/owner-decisions/GAP-128/03-release.md`, then stop for the Owner.
