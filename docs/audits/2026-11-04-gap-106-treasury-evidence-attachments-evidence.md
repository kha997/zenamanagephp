# GAP-106 — Treasury evidence attachments: discovery evidence

Date: 2026-11-04. Base: `main` at `71bc0c946c3b1338723658a24adae6e55572905c`.

Sources read:
- the canonical Treasury business spec, `docs/superpowers/specs/2026-08-07-project-treasury-cashflow-design.md` on branch
  `docs/project-treasury-spec`;
- the GAP-097 S6 exclusions;
- the code on `main`.

Method: static reading; no production data was available.

## 1. What the canonical spec asks

- **Q5:** which expenses are "missing evidence".
- **§7.12 Attachments:**
  - Prefer reuse of the repository's canonical document/attachment system. Do not create a duplicate file storage
    subsystem.
  - Evidence types: bank transfer image, bank statement, invoice, receipt, payment voucher, cash acknowledgement, labour
    confirmation, contract or purchase order, other.
- **§10.1:** dashboard card "Missing evidence count" and alert "missing evidence".
- **§11:** report 11 "Missing evidence report".
- **§12.7:** "Expense cannot post without evidence when tenant policy marks evidence mandatory."
- **Out of scope:** OCR / automatic extraction (spec non-goal 3).

GAP-097 (S6) excluded evidence, missing-evidence and the related report "until a later slice".

## 2. What exists

### Canonical document system

Models and services:
- `App\Models\Document`: `tenant_id`, `project_id`, `linked_entity_type` / `linked_entity_id`, `visibility`, `file_path`,
  `mime_type`, `file_size`, `file_hash`, …
- `DocumentVersion` stores each file version.
- `DocumentCreationService` creates documents; `DocumentVersionService` adds versions.

Usage today:
- Design items attach files through it, with linked type `design_item`
  (`Api\DesignItemController::uploadDocument`, local / default disk, one document per item plus versions).
- Allowed linked types: `task`, `component`, `diary`, `cr`, `submittal`, `design_item`.

**Risk: visibility.** The web document list `/app/documents` (`Web\DocumentController::index`) lists every document of
the user's tenant. It filters by project and status, but it has **no permission or linked-type filter**. Treasury evidence
stored as a plain `Document` would show there to anyone with app access. Bank statements and cash receipts are sensitive,
and Treasury access is restricted (`treasury.view` plus project membership or `treasury.all_projects`).

### Work-instance attachments (GAP-015 pattern)

- `WorkInstanceStepAttachment` is its own table: tenant, step, file name / path / mime / size, uploader.
- Upload and delete happen through the web, guarded by `work.update`.

This is a narrower, permission-scoped attachment that does not appear in the general document list.

### Treasury

- No attachment or evidence field on any Treasury table.
- There is no tenant setting for "evidence mandatory".
- Reconciliation records an `evidence_reference` text (S4a), not a file.

## 3. Open points for Gate 2

1. **Storage:**
   - reuse `Document` (spec preference) and make sure Treasury-linked documents are hidden from the general document list
     and readable only under Treasury permission; or
   - a Treasury-specific attachment table like the work-instance one (spec says avoid a duplicate subsystem).

   Either option needs a decision on where files live (private disk, download through an authorised route only).
2. **What can carry evidence:** expenses only, or every Treasury document (funding, transfer, advance, settlement,
   reconciliation).
3. **Mandatory evidence (§12.7):**
   - a per-tenant switch (new setting; which table);
   - always mandatory for expenses;
   - or optional with a "missing evidence" flag only.

   It also needs a decision on when the check runs (submit or approve).
4. **Who can add or remove evidence:**
   - after posting, a document is immutable, but evidence is metadata, so adding may be allowed;
   - removing should be audited or forbidden.
5. **File rules:** allowed types (images, PDF), size limit, filename handling, duplicate detection by hash.
6. **Missing-evidence outputs:** the dashboard card and alert, report 11, and a register filter.

## 4. Shared constraints

- Tenant and project isolation; Treasury policy for every read and download.
- Posted Treasury documents keep ledger immutability. Evidence must not change amounts or statuses.
- MySQL / SQLite parity for any migration; the GAP-038 CHECK constraints on `treasury_financial_documents` must not be
  lost to an SQLite table rebuild (as handled in GAP-105).
- CSV report through the S6 pattern.

## 5. Out of scope

- OCR / extraction.
- Bank statement import (separate Work ID).
- Period lock.
- Thresholds / advance deadlines.
- Changing the general document module beyond what is needed to keep Treasury evidence private.
- Deployment.
