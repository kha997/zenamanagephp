# Interim Payment Certificates (IPC) — Design Spec

Date: 2026-07-14
Status: approved by user (option A + defer retention/advance, 2026-07-14)
Depends on: R-CTR (`2026-07-13-contract-centric-management-design.md`) — contract_type, finance block, ContractPayment rollups.

## Purpose

Nghiệm thu khối lượng thanh toán theo đợt cho hợp đồng thi công: mỗi kỳ ghi *khối lượng hoàn thành kỳ này* per hạng mục × đơn giá chốt theo hợp đồng, cộng lũy kế qua các kỳ, duyệt xong tự sinh khoản phải thu. Closes the goal-#5 finance gap and takes the first concrete step on goal #2 (BOQ gets a price column).

## User-approved decisions

1. **Option A**: prices live in a contract-scoped BOQ inside the app (NOT manual per-certificate lines, NOT external zena-boq-core lines — its snapshot has no line items, verified `ZenaBoqIntegrationService.php:80-93`).
2. **Retention % and advance-recovery deductions are OUT of this slice** (schema stays additive-friendly for them).
3. Approving a certificate **auto-creates a `ContractPayment`** (`planned`, name "Nghiệm thu KL kỳ {N}", amount = certificate total, due_date = approval + 14 days) so the existing finance block/rollups pick it up with zero further wiring.

## Component 1 — Contract BOQ (extend native models)

- `boqs` + nullable `contract_id` (indexed). A BOQ with `contract_id` is that contract's "bảng khối lượng phụ lục HĐ"; project-scoped BOQs are untouched.
- `boq_line_items` + nullable `unit_price` decimal(15,2).
- `Contract::boq(): HasOne` (the contract-scoped BOQ); `Boq::contract(): BelongsTo`.
- UI on the construction-contract page: card "Bảng khối lượng HĐ" — add/edit/delete lines (code, name, unit, contract quantity, unit_price) via web endpoints (`rbac:contract.update`), **locked once any certificate is approved** (server-side check, not just UI).

## Component 2 — PaymentCertificate + lines

`payment_certificates`: ULID, `tenant_id` (+TenantScope), `contract_id`, `period_no` unsignedInteger (unique per contract), `period_from`/`period_to` dates, `status` (draft/submitted/approved — mirror `SiteDiary` constants), `total_this_period` decimal(15,2) denormalized, `submitted_by/at`, `approved_by/at`, timestamps.

`payment_certificate_lines`: ULID, `tenant_id`, `payment_certificate_id`, `boq_line_item_id`, `qty_this_period` decimal(14,3), `unit_price_snapshot` decimal(15,2) — copied from the BOQ line at entry time so later price edits never corrupt approved history, `amount_this_period` decimal(15,2) = qty × snapshot, timestamps. Unique (`payment_certificate_id`,`boq_line_item_id`).

**Cumulative math is always derived, never stored**: for each BOQ line, "lũy kế kỳ trước" = sum of `qty_this_period` across that contract's APPROVED certificates with `period_no` < current. Over-quantity (cumulative > contract qty) is a **visible warning, not a block** — field reality includes overruns; hard blocks breed fake data.

## Component 3 — Workflow

`draft → submitted → approved`, statuses on the model with a `TRANSITIONS` map like `DesignItem`. Only draft certificates accept line create/update/delete. New permissions `payment_certificate.view/create/approve` seeded in `ZenaPermissionsSeeder` + `TestDatabaseSeeder`, mirroring `site_diary.*` grants.

On approve (inside one DB transaction): set approved_by/at → recompute + freeze `total_this_period` → create the `ContractPayment` → write an `EventRecord` (`aggregate_type` `payment_certificate`, `event_key` `payment_certificate.approved`).

## Component 4 — UI

On `contracts.show` (construction type only, below the progress block): card "Nghiệm thu khối lượng" — list of certificates (kỳ, giai đoạn, tổng, status badge) + create form (period dates; period_no auto = max+1). Certificate detail page (`operator.contracts.certificates.show`): line table with columns KL hợp đồng / lũy kế trước / kỳ này (editable in draft) / còn lại / % hoàn thành / đơn giá / thành tiền, over-quantity warning row highlight, submit/approve buttons per permission + status.

## Error handling

- Certificate/BOQ endpoints: tenant-scoped `findOrFail` (404 cross-tenant), rbac middleware per route.
- Approve on non-submitted, submit on non-draft: 422 with transition message (mirror DesignItem pattern).
- BOQ line delete when referenced by any certificate line: 422 ("đã dùng trong chứng chỉ").
- Editing BOQ prices/lines after any approved certificate exists: 422 (lock rule).
- period_no collision: DB unique is the source of truth → 422 (SiteDiary's duplicate-date pattern).

## Testing

Unique period per contract; cumulative across 2 approved periods correct; snapshot price immune to later BOQ price change; approve creates exactly one ContractPayment with right name/amount and shows up in the finance block; BOQ locked after approval; draft-only editing; cross-tenant 404; permission denials; TenantScope guard entries for both new models; PHPStan exit 0 (use `Model::query()->...` everywhere — no magic statics).

## Out of scope (YAGNI / deferred)

Retention & advance deductions (next slice — add columns to `payment_certificates` later), PDF export, Excel import of BOQ, per-line QcInspection links, variation orders / price changes after lock, negative quantities (corrections go through a future variation slice).
