# Goal #8 slice — AI opportunity summary (tóm tắt cơ hội trước cuộc gặp)

Date: 2026-07-19
Status: approved for implementation planning

## Problem

ZENA vision goal #8 is "AI có kiểm soát (hỗ trợ, không bịa số liệu/đơn giá/pháp lý)". Three controlled-AI use cases have shipped (lead-conversion suggestion PR #159, design-item description PR #160, document checklist PR #161 — the last one needs no AI call). All follow one pattern: a manual button, a server-side `AiAssistService` call gated by `ai.suggest` + `throttle:ai-suggest`, and a human who reviews before anything is saved.

The brainstorm (2026-07-19) chose the next expansion direction: **summarization** — AI reads existing CRM data and restates it; it creates no new data, so it cannot fabricate figures, which is the strongest fit for "không bịa." First surface: the **Opportunity page**, answering the salesperson's question "tôi cần biết gì trước cuộc gặp khách này?" from the lead origin, appointment history, and quote history already linked to the opportunity.

## Scope

**In scope:**
- One new `AiAssistService` method: `summarizeOpportunity(array $context): ?array` — third method in the existing service, same Anthropic tool-use mechanism, same fail-safe `null` on error/disabled.
- One new endpoint: `POST /crm/opportunities/{id}/ai-summary` (web, operator group).
- Server-side context building with identity anonymization (see Data policy).
- UI: a "Tóm tắt AI" button + result panel on `resources/views/crm/opportunity-show.blade.php`, ephemeral (not persisted).

**Out of scope (deferred, not to be built speculatively):**
- Persisting summaries (no schema change of any kind; if cost becomes real, upgrade to "save latest" later).
- Summaries for Project, Quote, or any other entity (one surface per slice; a generic `Summarizer` abstraction is explicitly rejected as YAGNI with only one approved use case).
- Any change to the two existing `AiAssistService` methods.
- Any new permission (reuse `ai.suggest`; domain gate is the existing `crm.view`).
- Sending quote line items to the AI (not needed for a status summary; token waste).

## Architecture

- **Route:** `POST /crm/opportunities/{id}/ai-summary`, inside the existing `Route::prefix('operator')->name('operator.')` group, middleware `['rbac:crm.view', 'rbac:ai.suggest', 'throttle:ai-suggest']`, name `crm.opportunities.ai-summary`.
  - Deliberate difference from the two existing AI routes (which pair `ai.suggest` with a `*.manage` gate): summarization is a pure read — a `crm.view`-only salesperson may prepare for a meeting. `ai.suggest` + throttle remain as the two AI-specific control layers.
- **Controller:** `Web\CrmPageController::summarizeOpportunity(Request $request, string $id): JsonResponse` — loads the tenant-scoped Opportunity with its lead origin, `OpportunityAppointment` rows, and `Quote` rows; builds the anonymized context via a separate testable method `buildOpportunitySummaryContext(Opportunity $opportunity): array`; calls the service; returns `{"success": true, "data": {"summary": string, "generated_at": ISO8601}}` on success, or `503 {"success": false, "message": "Không thể tạo tóm tắt lúc này."}` when AI is disabled/failed — the exact response contract of the existing `suggestLeadConversion` endpoint (graceful degradation, never an unhandled 500).
- **Client sends nothing but the id** (empty POST + CSRF). All data selection happens server-side; the client cannot widen what is sent to the AI provider.

## Data policy (anonymization)

Context sent to the Anthropic API — whitelist, assembled server-side:

| Source | Sent | Deliberately NOT sent |
|---|---|---|
| Opportunity | `service_category`, `service_scope_summary`, `pipeline_stage`, `forecast_category`, `estimated_fee`, `estimated_project_value`, `probability`, `expected_close_date`, `priority`, `lost_reason` (if any), created date | `opportunity_name` (routinely contains client identity, e.g. "Biệt thự anh Minh"), `account_id`/account name/email/phone, owner ids, `external_*` fields |
| Lead (origin, if any) | `project_description`, created date | contact fields, source |
| OpportunityAppointment (each) | `type`, `scheduled_at`, `status`, `outcome_notes` | `location` (usually a client address), `assigned_to` |
| Quote (each) | `quote_number`, `revision_no`, `status`, `total`, `sent_at`, `decided_at`, `valid_until` | line items, `notes`, `payment_terms` |

Residual risk, accepted knowingly: `outcome_notes` and `project_description` are free text and may contain client names typed by users. This is the same residual risk accepted for `project_description` in Phase 7 (PR #159); it is not mechanically scrubbable and is documented rather than pretended away.

The UI still shows full client identity on the page (it is already there); only the API payload is anonymized.

## Prompt contract

- System prompt (Vietnamese): produce 5-7 bullet lines following the frame *tình trạng hiện tại → lịch sử tương tác → tình trạng báo giá → điểm cần lưu ý trước cuộc gặp*.
- Control constraints stated in the prompt: use only the supplied facts; never invent or extrapolate figures; where data is missing, write "chưa có thông tin" instead of guessing.
- Output forced through a tool-use schema `{summary: string}` (same technique as the two existing methods) — no free-text parsing.

## Workflow / UX

- On `crm/opportunity-show.blade.php`: a "Tóm tắt AI" card (matching the page's existing card components) with a **"Tạo tóm tắt"** button. Click → loading state → summary rendered in a panel below, with a muted caption "Tạo lúc HH:mm — AI chỉ tóm tắt từ dữ liệu CRM, hãy kiểm chứng trước khi trao đổi với khách" and a **"Tạo lại"** button.
- Ephemeral: nothing persisted; page reload clears the panel; regenerating costs one throttled AI call.
- 503 / failed response → inline notice "AI chưa bật hoặc đang lỗi — thử lại sau"; the page never breaks.
- Users without `ai.suggest` never see the button (Blade permission check, same as the two existing use cases).
- JS: new `resources/js/ai-opportunity-summary.js`, copying the `ai-lead-suggest.js` pattern (fetch POST with CSRF token, null-data handling).

## Testing approach

- **Feature:** (1) authorized user + AI disabled (empty API key) → 503 `{"success": false}`, not an unhandled 500 (matches `suggestLeadConversion` precedent); (2) user without `ai.suggest` → 403; (3) user without `crm.view` → 403; (4) opportunity belonging to another tenant → 404; (5) POST tests establish a real session via `/login` in `setUp()` (the Phase 7 CSRF gotcha, same pattern as `QuotePriceReferenceTest`).
- **Unit (the key control test):** `buildOpportunitySummaryContext()` output is asserted against an explicit **whitelist of keys** — not a blacklist — so any field added later cannot silently leak into the AI payload. Assert absence of `opportunity_name`, `location`, and any account identity field.
- No real Anthropic calls in CI (service degrades without a key, as today).

## Migration safety

No migration. No schema change. Purely additive code (one service method, one controller method + context builder, one route, one JS file, one Blade section).
