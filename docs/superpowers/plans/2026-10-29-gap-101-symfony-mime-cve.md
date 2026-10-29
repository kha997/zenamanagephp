---
work_id: GAP-101
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-101/02-design.md
---

# GAP-101 — symfony/mime CVE-2026-45070: implementation plan

Executes approved Gate 2 Option A (`docs/owner-decisions/GAP-101/02-design.md`).

1. **Red:** `composer audit --locked` reports CVE-2026-45070 for `symfony/mime` v7.4.19.
2. **Change:** `composer update symfony/mime` (no other package); confirm the `composer.lock` diff touches only the
   `symfony/mime` entry and `content-hash`.
3. **Green:** `composer audit --locked` reports no advisory.
4. Verify: Unit + Feature + Integration and `tests/Feature/Api` (no new failures vs the known baseline); exact-head CI
   including Security Vulnerability Scan; then Gate 3 packet.
