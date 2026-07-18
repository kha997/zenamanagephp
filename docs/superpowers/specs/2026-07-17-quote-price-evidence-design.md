# Quote price evidence & reusable reference (goal #2 slice)

Date: 2026-07-17
Status: approved for implementation planning

## Problem

[[project_zena_vision]]'s goal #2 ("Báo giá + BOQ") calls for quoting that is "so sánh, hiệu chỉnh đơn giá, có chứng cứ" — comparable, price-adjustable, evidence-backed. Native quoting (`Quote`/`QuoteLineItem`, lifecycle, commercial fields, PDF export, client portal display) already shipped in earlier slices. The one gap identified in the 2026-07-17 system audit and confirmed in this brainstorm: **there is no way to record or reuse *why* a line-item price is what it is.** `QuoteLineItem.price_note` is free text with no structure, no history, and nothing carries over between quotes — every quote starts from a blank price for every line, even for work items quoted dozens of times before.

Investigated the external `zena-boq-core` repo (github.com/kha997/zena-boq-core) as a possible source to reuse. Finding: it currently contains **no product code** (no UI, database, API, or Prisma setup) — it is a documentation-only governance scaffold at "Gate 0: manual calibration," and its own rulebook is explicitly locked (`NO_ACTIVE_MAPPING_RULES_AUTHORIZED`) to prevent any price/rate/formula from being used before evidence-backed approval. Nothing to extract as code. Two things *are* worth carrying over as design inspiration (not copied data): a standardized work-item taxonomy split by cost package (materials / labor / equipment), and the core evidence principle — *never treat a historical price as ground truth by default; every price must carry an explicit, typed evidence source.* This spec applies that principle inside ZenaManage's own native quoting, built independently in Laravel.

## Scope

**In scope:**
- A tenant-scoped, append-only price reference log (`price_reference_entries`), keyed by work-item `code` + `unit`.
- Auto-lookup + auto-fill of the latest reference price when adding/editing a quote line item with a matching code, editable by the user.
- Optional capture of new evidence (source type + note) when saving quote lines — appends a new reference entry, never overwrites an old one.
- A read-only "price history" view for a given code+unit, reachable from the quote-editing screen.

**Out of scope (explicitly deferred, not to be built speculatively):**
- Multi-vendor price comparison (side-by-side quotes from several suppliers) — the brainstorm concluded the real need is audit-trail evidence for an already-chosen price, not sourcing/procurement comparison.
- File/photo attachments as evidence — structured fields (source type, note, date) only for this slice.
- Client-visible evidence — this is purely internal; nothing here ever renders in the quote PDF, client portal, or any customer-facing surface.
- A standalone "price catalog" management page (create/edit/delete references directly) — references are captured only as a byproduct of the normal quote-editing workflow.
- Any integration with `zena-boq-core`'s external API (that integration, `ZenaBoqIntegrationService`, is a separate, already-shipped, currently-dormant Phase 2 feature — untouched here) or its draft taxonomy codes (not approved, not frozen — this slice's `work_item_code` values are whatever ZenaManage's own quote line items already use, no relation to the external repo's A.01–E.02 scaffold).
- Any change to the existing `Material` model (raw-materials procurement catalog) — deliberately a separate concept from work-item price references (a work item like "Bê tông móng" is a composite of materials + labor + equipment, not a single material row).

## Data model

New table `price_reference_entries` (migration, tenant-scoped via the existing `TenantScope` trait):

| Column | Type | Notes |
|---|---|---|
| `id` | ulid, primary | |
| `tenant_id` | string, FK → tenants | |
| `work_item_code` | string(50) | matches `QuoteLineItem.code` |
| `work_item_name` | string(255) | display name, may differ slightly from any one quote's line name |
| `unit` | string(30) | must match `QuoteLineItem.unit` for a lookup to count as "the same work item" |
| `unit_price` | decimal(15,2) | |
| `benchmark_type` | string, enum-checked | `vendor_quote` \| `previous_project` \| `approved_rate` \| `expert_estimate` |
| `evidence_note` | text, nullable | free text, e.g. "Báo giá Công ty ABC, ngày 10/7" |
| `evidenced_at` | date | date the evidence itself is dated, not necessarily today |
| `created_by` | string, FK → users | |
| `created_at` | timestamp | no `updated_at` — rows are never updated, only inserted |

Indexes: `(tenant_id, work_item_code, unit, evidenced_at)` to make "latest entry for this code+unit" a fast query.

`App\Models\PriceReferenceEntry` (Eloquent model):
- `use HasUlids, HasFactory, TenantScope;`
- `public const VALID_BENCHMARK_TYPES = ['vendor_quote', 'previous_project', 'approved_rate', 'expert_estimate'];`
- `public static function latestFor(string $tenantId, string $code, string $unit): ?self` — scoped query, `orderByDesc('evidenced_at')->first()`.

No foreign key from `price_reference_entries` to `quote_line_items` or `quotes` — the link is by `(work_item_code, unit)` string match, matching how `QuoteLineItem.code` already works today (free text, not a formal catalog FK). This keeps the two domains (a specific quote's line items vs. the tenant-wide price knowledge base) loosely coupled, which matters because a reference entry can outlive the quote that created it and inform many future quotes.

`QuoteLineItem` schema is unchanged.

## Workflow / UX

**Looking up a reference (auto-fill):**
- New endpoint `GET /crm/price-references/lookup?code=...&unit=...` (web route, `crm.view` permission — read-only, same gate as viewing quotes) returns JSON: `{unit_price, benchmark_type, evidence_note, evidenced_at}` or `null` if no match.
- On the quote-editing page (`resources/views/crm/quote-show.blade.php`), when a user fills in a line item's `code` field, JS calls this endpoint (debounced) and pre-fills the `unit_price` input if empty or if the user opts to overwrite, and shows a small inline hint: `Tham chiếu: {unit_price} — {benchmark_type label}, {evidenced_at}`. The user can freely change the price afterward; nothing is locked.

**Recording new evidence (on save):**
- `CrmPageController::saveQuoteLines()` already fully replaces a quote's line items on every save (delete + recreate). This slice adds three **optional** per-line form inputs: `benchmark_type` (select), `evidence_note` (text), and `evidence_date` (date, defaults to today client-side if left blank). Validation: `lines.*.benchmark_type` nullable, must be one of `PriceReferenceEntry::VALID_BENCHMARK_TYPES` when present; `lines.*.evidence_note` nullable string; `lines.*.evidence_date` nullable date, not in the future.
- Inside the same `DB::transaction` that recreates `QuoteLineItem` rows: for each line where `benchmark_type` was submitted, also insert a new `PriceReferenceEntry` (`work_item_code` = the line's `code`, `unit` = the line's `unit`, `unit_price` = the line's `unit_price`, `evidenced_at` = the line's optional `evidence_date` input, defaulting to today if left blank — a báo giá NCC is often dated earlier than the day someone gets around to entering it, so this needs to be a real, separately-editable field, not always "today"). Lines with `code` empty are skipped (no code, nothing to key a reference on). Lines with `benchmark_type` empty are skipped (no new evidence to record) — this is the common case, most saves won't create a reference entry, which is intentional (evidence capture is opt-in, not mandatory).

**Viewing history:**
- A "Xem lịch sử giá" link next to any line item that has a non-empty `code`, opening a small modal (AJAX-loaded partial) listing `PriceReferenceEntry::query()->where('work_item_code', $code)->where('unit', $unit)->orderByDesc('evidenced_at')->get()` — newest first, read-only, no edit/delete actions (append-only, matches the "audit trail" framing — a mistaken entry is corrected by adding a new, better one, not by editing history).

**Permissions:** the lookup and history endpoints reuse `crm.view`; the evidence-recording side-effect reuses the existing `crm.manage` gate already enforced on `saveQuoteLines` (no new permission).

## Testing approach

- Unit: `PriceReferenceEntry::latestFor()` returns the newest entry by `evidenced_at` when multiple exist for the same code+unit; returns `null` when none match; tenant isolation (entry from tenant A never returned for tenant B's lookup).
- Feature: `saveQuoteLines` with a line carrying `benchmark_type` creates exactly one new `PriceReferenceEntry`; a line without `benchmark_type` creates none; saving the same quote twice with evidence both times creates two entries (append-only, not upsert); the lookup endpoint returns the correct latest entry and 404/null for an unknown code.
- RBAC: a user without `crm.view` gets 403 from the lookup/history endpoints; a user without `crm.manage` cannot trigger evidence creation (already covered transitively by the existing `saveQuoteLines` permission test, extended to assert no `PriceReferenceEntry` row is created on a rejected request).

## Migration safety

Pure additive migration (new table only, no changes to existing tables). Safe to run online, no backfill needed — the reference log starts empty and grows only as new evidence is recorded going forward.
