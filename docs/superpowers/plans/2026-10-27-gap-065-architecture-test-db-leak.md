---
work_id: GAP-065
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-065/02-design.md
---

# GAP-065 — Architecture test DB leak: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-065/02-design.md`).

1. **Red:** run the evidence's reproduction command on the branch (`VendorApiTest` + `tests/Architecture` +
   `ProductionBootstrapCommandTest`); expect 3 failing `ProductionBootstrapCommandTest` tests.
2. **Fix:** add `use Illuminate\Foundation\Testing\RefreshDatabase;` and `use RefreshDatabase;` to
   `tests/Architecture/DebugRouteBoundaryInvariantTest.php`; nothing else.
3. **Green:** the reproduction command passes; the class passes alone and inside `tests/Architecture`.
4. **Verification:** Architecture, Feature/Api, Web, Zena, Deployment, Unit suites; PHPStan; php-cs-fixer; SSOT test
   lint; Gate 3 packet.
