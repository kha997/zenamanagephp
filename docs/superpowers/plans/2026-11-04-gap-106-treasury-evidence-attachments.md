---
work_id: GAP-106
owner_governance_version: 1
owner_gate_2_record: docs/owner-decisions/GAP-106/02-design.md
---

# GAP-106 — Treasury evidence attachments: implementation plan

Executes Gate 2 S-2, E-1, M-1, D-1 (`docs/owner-decisions/GAP-106/02-design.md`).

1. **Red:** write `tests/Feature/Treasury/TreasuryEvidenceTest.php` first. It fails because there is no table, service or
   route yet.
2. **Green — data:**
   - migration `treasury_document_attachments`: composite FK to `treasury_financial_documents`, unique
     (document, sha256);
   - model `TreasuryDocumentAttachment`.
3. **Green — service:** `TreasuryEvidenceService` handles:
   - attach: type and size rules, adder rules, hash duplicate check, audit;
   - remove: uploader only, before posting, audit;
   - list and count;
   - missing evidence: posted expenses that are not reversed;
   - tenant switch in `tenants.settings`: owner-level only, audited.

   `TreasuryExpenseService::submit` refuses an expense without evidence when the switch is on.
4. **Green — pages:**
   - routes: attach, download (as an attachment, nosniff), remove, switch;
   - evidence panel on the project Treasury page and count per register row;
   - switch on the Treasury index;
   - dashboard card and the `missing_evidence` alert;
   - report `missing_evidence` (CSV) and a register filter.
5. **Verify:**
   - Treasury files never appear in `/app/documents`.
   - The register query count does not grow with the number of attachments.
   - The migration runs up / down / up on MySQL.
   - PHPStan, php-cs-fixer, SSOT lint, route guard and deptrac pass.
   - Unit + Feature + Integration and `tests/Feature/Api` pass.
6. **Gate 3:** release packet with the subject commit and implementation-tree digest.
