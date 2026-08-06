# Non-Technical Owner Control Layer — Governance Design

*Status: SPECIFICATION ONLY — not implemented. No constitution amendment, no CI script, no PR template change, no code change is authorized by this document.*
*Author: Claude (agent), on explicit pre-approved business direction from the owner — 2026-08-04. Amended 2026-08-04 (same day) to correct the Gate 3 lifecycle, add a canonical status machine, separate technical readiness from owner decision, and resolve the open identity/notification/language-guide questions.*
*Scope: governance / product-operating-model design, not a feature design.*

---

## 0. Pre-approved business decisions (authoritative inputs, not re-derived here)

This spec does not re-litigate these — they are given:

1. The owner is non-technical.
2. The owner decides: product goals, business scope, business rules, user-visible workflow, acceptable business risk, and whether a verified change may be released.
3. Engineering agents and technical reviewers decide implementation details.
4. Exactly three owner gates: Gate 1 (Business Request), Gate 2 (Business Design), Gate 3 (Release).
5. Every gate has a one-page plain-language Owner Decision Packet plus a separate technical evidence appendix.
6. Release approval uses a short operational demo, an acceptance checklist, and a plain-language residual-risk conclusion.
7. The long-term owner interface is an in-app "Decision Center" inside ZENA WebApp.
8. GitHub must not be the primary owner interface.
9. Repository-native governance artifacts are the immediate foundation; the in-app Decision Center later becomes authoritative.

---

## 1. Gap analysis — what exists today vs. what a non-technical owner needs

### 1.1 What exists today (evidence)

- `PROJECT_CONSTITUTION.md` — governs agent behavior. Written for engineers/agents: "Evidence Before Claims" (§8) demands `route:list` output, migration citations, test run output. No plain-language surface for a non-technical reader.
- `docs/agent-ssot-rules.md` — pure technical evidence law (`route:list`, migration diffs, controller/request citations). Zero business-decision content.
- `docs/product-purpose-ssot.md` — describes product scope in relatively plain terms, but it is a static reference, not a per-change decision artifact, and has no gate/approval mechanism.
- `OPERATIONAL_GAP_REGISTER.md` — a technical backlog. Entries like GAP-031 are written entirely in engineering language ("`lockForUpdate()`-guarded", "`PROTECTED_METADATA_KEYS` guard", "`draft→submitted→approved|rejected`"). No business reader could evaluate risk from this document without translation.
- `.github/PULL_REQUEST_TEMPLATE.md` — SSOT story reference, invariants checklist, acceptance criteria, evidence/verification commands, CI check links. This *is* the closest thing to an approval mechanism today, and it is 100% engineering-facing (`composer ssot:lint`, `php artisan test`, CI run links).
- GAP-031 / PR #238 worked example (inspected directly): the PR body is a precise, well-written **engineering** root-cause narrative — code paths, class names, guard mechanisms, out-of-scope technical boundaries (GAP-032/GAP-033). It assumes the reader can evaluate `lockForUpdate()` and `DB::transaction()` as evidence of correctness. PR #238 is currently a **draft**, all 30 CI checks green, 32 files / +5216 −130. There is no artifact in the repository today that tells a non-technical owner, in one page, what problem this solves, what a user can now do, what authorization risk closed, and what decision is being asked of them.
- The Constitution's "Working Loop" (§7) and "Completion Definition" (§10) presuppose the *reader of the stop report* can interpret test output, migration state, and RBAC checks. There is no concept of an "owner" as a distinct persona anywhere in the governance stack — only "agents" and implicitly, other engineers reading PRs.

### 1.2 The gap, stated precisely

The current lifecycle is **rigorous but single-audience**: every artifact (constitution, SSOT rules, gap register, PR template, PR body) is written for someone who can read Laravel code, PHPStan output, and route tables. There is no translation layer, no gate where a non-technical person makes a binding decision using only business-legible information, and no separation between "this is a business decision" and "this is an implementation detail" — a reviewer and an owner would currently read the *same* document and be expected to approve the *same* thing.

This is not a documentation-quality gap. It is a **missing decision layer**: the constitution defines *what agents must prove*, not *what a business owner must decide, when, and using what*.

### 1.3 Design goal restated

Build a second, parallel decision surface — the **Owner Control Layer** — that sits on top of (never replaces) the existing engineering rigor. The owner reads one page per gate, in controlled plain Vietnamese, and never touches source code, test logs, CI, architecture, or terminology that requires engineering background. Material risk must still reach the owner — translated, never hidden.

---

## 2. Architecture: two explicitly separated layers

### 2.1 Owner Control Layer (OCL)

**Audience:** the owner, exclusively. **Language:** controlled plain Vietnamese (see §6.10, Language Guide). **Length:** one page per gate packet — if it does not fit on one page, it is over-detailed and must be cut, not the format changed.

Content permitted in an OCL packet (nothing else):
- operational problem (vấn đề vận hành) — what is broken today, in terms of a real workflow moment, not a bug ID
- affected users and workflow (ai bị ảnh hưởng, ở bước nào)
- evidence that the problem exists (a described real scenario or observed pattern — never a stack trace or query)
- expected business outcome (kết quả mong đợi)
- before/after workflow (trước/sau — described as a short numbered sequence of user actions, not a state diagram)
- business rules (quy tắc nghiệp vụ — "who is allowed to approve," not "which permission string gates which route")
- scope and exclusions (phạm vi và loại trừ)
- user-visible changes (thay đổi người dùng nhìn thấy)
- owner-level risks (rủi ro ở mức chủ doanh nghiệp — "nếu sai, hậu quả là gì với khách hàng/dữ liệu/tiền," never "nếu race condition xảy ra")
- acceptance scenarios (kịch bản chấp nhận — expressed as "khi X xảy ra, hệ thống phải Y")
- recommendation (đề xuất của đội kỹ thuật)
- available owner decisions (the fixed enum of choices — see §5)

**Hard rule:** if a sentence cannot be understood by someone who has never opened a code editor, it does not belong in the OCL packet. It belongs in the Engineering Evidence Layer, linked, not embedded.

### 2.2 Engineering Evidence Layer (EEL)

**Audience:** agents, technical reviewers, and (optionally, if curious) the owner — but never *required* reading for the owner. **Language:** technical, exactly as today.

Content:
- design specification (existing `docs/superpowers/specs/*`)
- implementation plan (existing `docs/superpowers/plans/*`)
- commits and changed files
- test and CI evidence (existing `gh pr checks` output, links)
- authorization and tenant-isolation evidence
- concurrency and performance evidence
- browser/E2E acceptance evidence
- review findings (existing code-review skill output)
- unresolved technical debt (existing `OPERATIONAL_GAP_REGISTER.md` entries)
- rollback details

**This layer already exists** in this repository, almost entirely — `docs/superpowers/specs/`, `docs/superpowers/plans/`, PR bodies, `OPERATIONAL_GAP_REGISTER.md`, CI runs. The design task is not to build it; it is to (a) stop asking the owner to read it directly, and (b) add the missing links from OCL packets into it.

### 2.3 The relationship

An OCL packet **links to** its EEL by work-ID (`docs/owner-decisions/<WORK-ID>/`, cross-referenced to spec/plan/PR paths). The owner packet never *requires* opening that link. A technical reviewer, auditor, or future owner-delegate can always trace an OCL claim ("authorization risk closed") back to the EEL evidence that substantiates it ("`document.approve` now gates both surfaces, `RoleBasedAccessControlMiddleware`, test X"). This traceability is what prevents the OCL from becoming an unverifiable marketing summary — every OCL claim of fact must be traceable to something concrete in the EEL, even though the owner is never required to make that trace themselves.

---

## 3. The three owner gates

Common packet mechanics (all three gates):
- One Owner Decision Packet file, plain Vietnamese, ≤ 1 page (~500–700 words, no code blocks, no tables of routes).
- One Technical Evidence Appendix, linked, not embedded.
- A fixed `owner_decision.value` enum field (§5, §3.7) — never free text for the decision itself. Free-text rationale is allowed *in addition to*, never *instead of*, the enum.
- A `decision_provenance` block recorded at decision time (who recorded it, when, at what trust level — §6.8a) — see §6.8/§6.8a on the authentication caveat.
- Immutable once decided: a reversed decision creates a new packet revision that supersedes the prior one; it does not edit history (§6.5).

### 3.1 Gate 1 — Business Request Approval

**Purpose:** the owner decides whether the operational problem is real, important, and correctly scoped — *before any solution is designed.*

**Entry criteria:**
- A work ID exists (`GAP-xxx`, or a new request ID if not sourced from the gap register).
- An operational problem has been identified and can be described in plain language with at least one concrete scenario (a real or realistic user moment, not an abstract "cải thiện hệ thống").
- No implementation plan and no production code exists yet for this work ID. Agents may inspect the codebase and research (read-only) to describe the problem accurately, but must not produce `docs/superpowers/plans/*` or touch application code before this gate is entered.

**Required packet fields (Gate 1 — `01-request.md`):**
- `problem_statement` — plain-language description of what is broken.
- `affected_users` — which roles/people hit this, and when.
- `evidence_summary` — plain description of how we know this is real (no stack traces).
- `business_impact_if_unaddressed` — what keeps going wrong if nothing changes.
- `proposed_scope` — what this request covers, in one paragraph.
- `explicit_exclusions` — what this request does NOT cover (prevents scope creep from being silently assumed).
- `recommendation` — the team's recommended action (fix now / defer / decline), with one sentence why.
- `owner_decision` — enum field (see §5.1, §3.7).
- `decision_rationale` — optional free text from the owner.

**Permitted decisions:** Approve to proceed to design (Gate 2) / Request more information / Decline (park in gap register as owner-declined) / Defer (park with a revisit condition).

**Exit criteria:** `decision: approved` recorded. This is the *only* condition under which an agent may begin producing a Gate 2 design/business-design packet or an implementation plan. `decision: more_info_requested` returns the work ID to research-only state — no plan, no code. `decision: declined` or `decision: deferred` closes or parks the work ID; the gap register entry (if any) is updated to reflect the owner decision, not silently reopened later without a new Gate 1.

### 3.2 Gate 2 — Business Design Approval

**Purpose:** the owner approves the shape of the solution as experienced by users — never its implementation.

**Entry criteria:** Gate 1 `decision: approved` exists for this work ID. A business-facing design has been drafted (workflow before/after, roles, rules) — but **no implementation plan (`docs/superpowers/plans/*`) and no production code may exist yet.** The existing engineering `docs/superpowers/specs/*` design document may exist in parallel/underneath (it is the EEL source), but Gate 2 approval is what authorizes moving from "design" to "plan."

**Required packet fields (Gate 2 — `02-design.md`):**
- `workflow_before` — numbered steps, current state.
- `workflow_after` — numbered steps, proposed state.
- `affected_roles` — which user roles see a change, and what changes for each.
- `allowed_actions` / `forbidden_actions` — plain statement of who can now do what, and what remains blocked.
- `statuses_and_next_steps` — if the workflow has states (e.g. "chờ duyệt," "đã duyệt," "bị từ chối"), name them in plain Vietnamese and say what happens next in each.
- `exceptions` — known edge cases described in business terms ("nếu người duyệt nghỉ việc giữa chừng thì...").
- `user_visible_behavior` — what changes on screen/in notifications, described functionally, not visually (no wireframe required at this gate).
- `business_acceptance_scenarios` — "given X, when Y, the system must Z," in plain language, forming the basis of the Gate 3 acceptance checklist.
- `scope_exclusions` — carried forward/refined from Gate 1, updated if design revealed new boundaries.
- `owner_decision` — enum field (see §5.1, §3.7).
- `decision_rationale` — optional free text.

**Explicitly excluded from this packet:** class names, database tables, framework choices, algorithm/locking strategy, test structure. If the engineering spec's technical sections (e.g. GAP-031 spec §4–§14: enum helpers, `DocumentWorkflowService`, `lockForUpdate()`) were pasted into a Gate 2 packet, that is a packet defect — remove and re-summarize in business terms.

**Permitted decisions:** Approve to proceed to implementation planning / Request changes to the design / Decline.

**Exit criteria:** `decision: approved` recorded. This is the *only* condition under which an agent may create `docs/superpowers/plans/*` or begin writing production code for this work ID.

### 3.3 Gate 3 — Release Approval

**Purpose:** the owner decides whether a *verified, already-built* change may go live. Gate 3 does **not** gate the start of building, testing, or reviewing that change — it gates only the final release act. This distinction was under-specified in the original version of this spec (which implied release *preparation* could not begin until owner approval) and is corrected here; see §3.4 for the canonical sequence and §3.5 for how blocked-but-in-progress work stays visible to the owner without requiring a decision from them.

**Gate 3 packet lifecycle begins the moment Gate 2 is approved** — not the moment engineering finishes. The packet is drafted early (`status: not_started` → `preparing`) and evolves alongside implementation; it only becomes decision-ready (`status: awaiting_owner`, §3.6) once every mandatory engineering gate is green.

**Preparation activities — occur before the owner decision, authorized by Gate 2 approval alone, and require no separate owner action per activity:**
- implementation (writing the code that fulfills the approved Gate 2 design)
- testing (unit/integration/E2E/concurrency, as already required by `PROJECT_CONSTITUTION.md` Appendix A.5)
- independent/technical review (the existing code-review skill, PHPStan, security scan)
- technical gate verification (CI: the same required-check set already enforced today — API/Feature/Integration/Security Tests, Code Quality Analysis, Dependency/Docker/License scans, Repo Hygiene Guards, concurrency tests where applicable)
- operational demo preparation (scripting and rehearsing the plain-language walkthrough that will appear in the packet)
- business acceptance evidence collection (confirming each Gate 2 `business_acceptance_scenarios` item, one by one)
- rollback preparation (confirming and documenting the rollback path)
- Gate 3 packet generation (drafting `03-release.md` itself, including a draft `technical_readiness` value — see §3.7)

None of the above requires a `decision` from the owner. All of it may — and normally does — happen while the packet's `status` is `preparing` or, if a mandatory gate goes red mid-preparation, `blocked_technical` (§3.6). A PR may be opened, pushed to, iterated on, and marked Ready for technical/code review during this phase; "Ready for review" is a statement about engineering readiness for review, not a claim of owner approval, and remains permitted before Gate 3 (§3.5 defines exactly what is prohibited before approval, and marking a PR ready for *review* is not on that list — only merge, deploy, and representing the change as owner-approved are).

**What Gate 3 owner approval specifically authorizes**, once granted:
- marking the release as business-approved (the one thing that changes the moment `owner_decision.value` becomes `approved`, §3.7);
- merging, once all repository-native requirements are *also* satisfied (branch protection, required reviewers, green required CI — this spec adds an additional required condition, it does not relax any existing one);
- deployment or production release, proceeding through the existing engineering pipeline (`PROJECT_CONSTITUTION.md` Appendix A.11) exactly as before.

**Before Gate 3 approval, explicitly prohibited (this is the actual gate, not "no preparation may occur"):**
- merging the branch, for any work ID where owner approval is a required condition for release;
- production deployment;
- production data changes;
- releasing the change to real users;
- representing the change, anywhere (PR description, stop report, chat), as "owner-approved" or "released" — an unapproved change may be described as "technically ready" or "awaiting owner decision," never as approved.

**Entry criteria (packet creation, `not_started` → `preparing`):** Gate 2 `owner_decision.value: approved` exists for this work ID. Nothing else is required to *start* the Gate 3 packet and begin engineering preparation — see the preparation list above.

**Transition criteria (`preparing`/`blocked_technical` → `awaiting_owner`, i.e. becoming decision-ready — this is the point previously, and incorrectly, described as "Gate 3 entry"):** `technical_readiness: ready` (§3.7) — meaning, concretely: required CI checks pass (`gh pr checks`), tenant-isolation and RBAC evidence exists for any touched authorization surface, and no unresolved Critical/Important finding from technical review remains open — the same technical bar already codified in `PROJECT_CONSTITUTION.md` §10 (Completion Definition). Until this is true, the packet stays in `preparing` (actively being worked) or `blocked_technical` (visibly stalled on a specific red check, §3.5) — in both cases visible to the owner, in neither case awaiting a decision from them.

**Required packet fields (Gate 3 — `03-release.md`):**
- `what_was_broken` — one paragraph, plain language, carried from Gate 1.
- `what_users_can_now_do` — carried/refined from Gate 2's `workflow_after`.
- `demo_script_and_result` — a short (3–7 step) scripted walkthrough of the new behavior and its observed outcome — "bấm nút Duyệt → trạng thái đổi thành Đã duyệt → người nộp nhận thông báo" — not a Dusk test transcript.
- `business_acceptance_checklist` — the Gate 2 `business_acceptance_scenarios`, each marked done/not-done.
- `changes_delivered` — plain summary of what shipped.
- `exclusions_and_deferred_gaps` — explicitly what did NOT ship, and any new gap IDs opened as a result (e.g. GAP-032/GAP-033 in the GAP-031 example, §9).
- `residual_risks_plain_language` — translated from the EEL's technical debt/known-limitation notes, phrased as business consequences, never as code paths.
- `rollback_impact` — plain statement: can this be undone, what happens to in-flight data if it is.
- `technical_safety_summary` — one short paragraph, plain language, confirming data-integrity/tenant-isolation/security checks passed — a *statement*, not the evidence itself (evidence lives in EEL).
- `release_recommendation` — the team's recommendation.
- `technical_readiness` — machine field, not prose (§3.7).
- `owner_decision` — machine field, not prose (§3.7 — enum per §5.1).
- `decision_rationale` — optional free text.

**Permitted owner decisions (only exposed once `status: awaiting_owner`, §3.6):** Approve release / Request business correction (send back — implies re-entering Gate 2 or a scoped fix, not a new Gate 1 unless scope changed) / Defer release (approved in substance, timing not yet right).

**What the owner may NOT override, ever, regardless of decision:** red status on any of: data integrity, tenant isolation, security, authorization, required CI, failed migrations, destructive-data risk, or missing mandatory evidence. If any of these is red, `technical_readiness` cannot be `ready`, `status` cannot reach `awaiting_owner`, and **no approval action is offered to the owner at all** — not because the packet is hidden (it is not — §3.5), but because the decision surface itself does not exist while readiness is not `ready`. This is the one place the OCL is deliberately *not* owner-configurable: business risk tolerance governs feature scope and timing, never data/security invariants (this mirrors `PROJECT_CONSTITUTION.md` §5 Priority Rule, tiers 1–2, which already rank data integrity and security above all else).

**Exit criteria:** `owner_decision.value: approved` → merge/deploy may proceed through the existing engineering pipeline (Constitution Appendix A.11), subject to any remaining repository-native requirements. `owner_decision.value: correction_requested` → work ID returns to `preparing`, no release. `owner_decision.value: deferred` → build is complete and stays complete, but release is held pending a future Gate 3 revisit (no re-approval of Gate 1/2 required unless scope changes).

### 3.4 Canonical Gate 3 sequence

```text
Gate 2 approved
→ implementation plan
→ implementation
→ technical verification
→ demo and Gate 3 packet preparation
→ mandatory engineering gates green
→ Gate 3 awaiting owner
→ owner decision
→ merge/deploy through the release process
```

Everything from "implementation plan" through "mandatory engineering gates green" is engineering work, authorized by Gate 2 approval alone, requiring no further owner action. "Gate 3 awaiting owner" is the only point at which an approval action is offered. "Owner decision" is the only point at which a human, not an agent, sets `owner_decision.value`. "Merge/deploy through the release process" happens only after `owner_decision.value: approved`, and only if repository-native requirements (branch protection, required reviewers, green required CI) are *also* independently satisfied — Gate 3 approval is a necessary condition for release, never a sufficient one on its own, and vice versa (§3.7).

### 3.5 Visibility while blocked — the owner sees blocked work, but cannot act on it

While a Gate 3 packet is in `preparing` or `blocked_technical` (§3.6), it is not hidden from the owner. Repository-native visibility (and, later, the Decision Center's Gate 3 queue, §10.1) surfaces a **read-only blocked decision card** containing:

- current business objective — carried from Gate 1/Gate 2, one sentence;
- delivered progress — plain-language, e.g. "phần lớn đã xong, đang chờ kiểm tra an toàn cuối cùng";
- plain-language blocking reason — never a raw CI job name or stack trace, e.g. "một phép kiểm tra an toàn dữ liệu chưa đạt" rather than "Document Workflow Concurrency (real MySQL) failing";
- operational risk if released now — why the block exists, in business terms, e.g. "nếu phát hành lúc này, hai người có thể vô tình ghi đè quyết định duyệt của nhau";
- what engineering is doing next — one short phrase, e.g. "đang sửa và chạy lại kiểm tra";
- whether any owner decision is currently needed — always "Không" (No) while blocked; this field exists specifically so the card never leaves the owner guessing.

**While blocked, structurally, not just by convention:**
- no approval action is available — the card renders with no buttons, not with disabled-looking buttons that invite a workaround;
- no owner decision is requested — the packet's `status` is not `awaiting_owner`, so it does not appear in any "needs you" queue (§6.3, §10.1);
- the owner cannot override the mandatory engineering gate — there is no field or action anywhere in the OCL that sets `technical_readiness: ready` except the engineering-evidence process itself (§3.7);
- the packet is clearly labeled **`BLOCKED — OWNER ACTION NOT REQUIRED`** at the top of the card, in the same plain language as the rest of the OCL.

This resolves the tension between two requirements that could otherwise look contradictory: the owner must never be asked to inspect CI, and the owner must never be kept in the dark about progress. **Visibility and decision-eligibility are independent.** A card can be fully visible (business objective, progress, blocking reason, risk, next step — all plain language) while simultaneously exposing zero decision actions. §9 shows this exact card for GAP-031's earlier, genuinely-blocked state.

### 3.6 Canonical gate status machine

A single machine-readable `status` vocabulary is used across all three gates (replacing the earlier, less precise `draft`/`pending_decision` pair used in the original version of this spec, now folded into this richer set — see §6.3):

```text
not_started
preparing
blocked_technical
awaiting_owner
approved
changes_requested
deferred
superseded
```

**Only `awaiting_owner` exposes owner decision actions.** Every other status is either "nothing to decide yet" (`not_started`, `preparing`, `blocked_technical`) or "already decided, terminal for this revision" (`approved`, `changes_requested`, `deferred`, `superseded`).

**Gate 1 and Gate 2 transitions** (neither gate has engineering work in flight yet — Gate 1 entry forbids planning/code entirely, Gate 2 entry forbids everything but the design draft — so neither uses `blocked_technical`):

```text
not_started → preparing
preparing → awaiting_owner
awaiting_owner → approved | changes_requested | deferred
changes_requested → preparing
deferred → preparing | superseded
approved → superseded
```

**Gate 3 transitions** (engineering work is genuinely in flight, so `blocked_technical` is real and reachable):

```text
not_started → preparing
preparing → blocked_technical | awaiting_owner
blocked_technical → preparing | awaiting_owner
awaiting_owner → approved | changes_requested | deferred
changes_requested → preparing
deferred → preparing | superseded
approved → superseded
```

**Automatic revert rule (Gate 3 only):** if a mandatory engineering gate that was green turns red *after* the packet reached `awaiting_owner` — a flaky-turned-real CI failure, a newly discovered tenant-isolation issue, a rebased branch that reintroduces a regression — the packet **automatically** reverts to `blocked_technical`, and any pending or in-flight owner decision request for that packet revision is invalidated (it is not silently left dangling as if still awaiting a valid decision; §7.1's lint contract treats a stale `awaiting_owner` sitting alongside a currently-red mandatory check as a structural inconsistency, not merely an advisory warning). If the owner had already recorded `approved` before the regression was discovered, that recorded decision is **not deleted** (§6.5, immutable history) but the packet enters a new revision (`03-release-v2.md`, `supersedes` the approved one) that must independently reach `awaiting_owner` again before release may proceed — an already-spent approval never carries forward across a re-opened technical block.

**Reconciling `status` with each gate's owner-decision enum (§5.1):** Gate 1's enum includes an outcome (`declined`) and Gate 2's/Gate 3's enums include outcomes (`declined`; `correction_requested`) that are not themselves separate lifecycle stages in the 8-value set above. Rather than inventing additional status values per gate, `status` reflects which of the 8 canonical stages the packet occupies, while the finer-grained *reason* lives in `owner_decision.value` (§3.7, §5.1): a `declined` or `deferred` decision both place `status: deferred` (parked, not currently proceeding) — distinguished by `owner_decision.value`, where `declined` signals "not revisiting without a new Gate 1" and `deferred` signals "revisit later, same work ID"; a `changes_requested`, `correction_requested`, or `more_info_requested` decision all place `status: changes_requested` (sent back for rework) — again distinguished by `owner_decision.value`. This keeps the status vocabulary small and identical across all three gates (satisfying self-review item 8, §13) while losing no information.

### 3.7 Technical readiness and owner decision are independent fields

Two fields, tracked separately, on every Gate 3 packet (Gates 1–2 carry only `owner_decision`, since they have no engineering-evidence dimension to track — see the frontmatter note in §6.2):

```yaml
technical_readiness:
  value: not_checked | blocked | ready
  generated_by: engineering_evidence

owner_decision:
  value: none | approved | changes_requested | deferred    # Gate 3's concrete enum uses correction_requested in place of the generic changes_requested shown here — see §5.1
  authority: human_owner
```

**`technical_readiness`** is set exclusively by engineering evidence (CI results, review findings, tenant-isolation/RBAC checks) — never by an agent's judgment call and never by the owner. `generated_by: engineering_evidence` is a statement that this field's value is a *computed fact*, not an opinion.

**`owner_decision`** is set exclusively by a recorded owner action (§6.6, §8) — never inferred from `technical_readiness`, never defaulted, never pre-filled to anything other than `none`.

**The two invariants this design exists to enforce:**
- An agent must never translate `technical_readiness: ready` into `owner_decision: approved`. Readiness is a precondition for *asking* the owner, never a substitute for *asking* the owner.
- Owner approval must never convert a red `technical_readiness` into `ready`. The owner's authority covers business risk and release timing (§0.2), not data integrity or security invariants (§3.3, "what the owner may NOT override").

**Release eligibility requires both, conjunctively, plus repository requirements:**

```text
technical_readiness = ready
AND
owner_decision = approved
AND
<repository-specific reviewer/branch-protection requirements, unchanged by this spec>
```

Neither field alone is sufficient. A `technical_readiness: ready` packet with `owner_decision: none` is exactly the `awaiting_owner` status (§3.6) — built, verified, and waiting on a human. An `owner_decision: approved` packet whose `technical_readiness` later regresses to `blocked` (the automatic-revert case, §3.6) is not release-eligible despite the recorded approval, because the conjunction is evaluated at release time, not merely recorded once and trusted forever.

---

## 4. Gate flow diagram

```
Work ID opened (from OPERATIONAL_GAP_REGISTER.md or a new owner-raised item)
        │
        ▼
[Research only — no plan, no code] ──▶ Gate 1 packet: preparing ──▶ awaiting_owner ──▶ OWNER DECISION
        │                                                                                  │
        │                                                        approved ◀────────────────┤──▶ declined/deferred → status: deferred, parked
        ▼
[Business design drafted — no plan, no code] ──▶ Gate 2 packet: preparing ──▶ awaiting_owner ──▶ OWNER DECISION
        │                                                                                  │
        │                                                        approved ◀────────────────┤──▶ changes requested → status: changes_requested → back to preparing
        ▼
Gate 3 packet opens: status = preparing (visible to owner, no decision requested)
        │
        ▼
[Implementation plan + code + tests + CI + technical review + demo prep + Gate 3 packet drafting]
        │
        ├──▶ a mandatory engineering gate is red ──▶ status: blocked_technical
        │         (READ-ONLY blocked card visible to owner — §3.5 — no decision offered, owner cannot override)
        │         │
        │         └──▶ fixed ──▶ back to preparing
        ▼
All mandatory engineering gates green (technical_readiness: ready) ──▶ status: awaiting_owner ──▶ OWNER DECISION
        │                                                                                              │
        │                                                                    approved ◀─────────────────┤──▶ correction requested → status: changes_requested → back to preparing
        │                                                                                              └──▶ deferred → status: deferred (build stays complete, release held)
        ▼
owner_decision = approved AND technical_readiness = ready AND repository requirements satisfied
        │
        ▼
merge/deploy through the existing engineering release process
```

If any mandatory engineering gate turns red again after `awaiting_owner` is reached (including after an `approved` decision is recorded), the packet automatically reverts to `blocked_technical` and any stale decision request is invalidated (§3.6) — this loop-back edge is omitted from the diagram above for readability but is binding (§3.6, §7.1).

---

## 5. Decision-escalation rules

### 5.1 The fixed decision enums (machine-readable, one per gate)

```
gate_1_decision:  approved | more_info_requested | declined | deferred
gate_2_decision:  approved | changes_requested | declined
gate_3_decision:  approved | correction_requested | deferred
```

No other values are valid. An agent may propose a recommended value; only a recorded owner decision (§6, §8) may set the field to `approved`. In frontmatter (§6.2) this enum is carried in `owner_decision.value` (§3.7), with `none` as the not-yet-decided sentinel (replacing the earlier, less precise `null` used in the original version of this spec).

### 5.2 Findings that REQUIRE owner involvement

A finding discovered at any point in the lifecycle (design, implementation, review, post-release) requires routing back to the owner (a new or amended gate packet) when it changes any of:

- product goal
- scope (adds or removes what the change covers)
- business rules (who is allowed to do what, under what condition)
- user roles or authority (who can act, approve, or see)
- data visibility (who can see what data)
- approval responsibility (who is the decision-maker for a workflow step)
- workflow states (new/removed/renamed states a user-facing record can be in)
- financial or legal behavior
- user-facing acceptance criteria (what "done" looks like to a user)
- risk accepted by the business (a new residual risk the business, not engineering, must own)
- release timing

**Routing rule:** if the finding surfaces *before* Gate 2 is approved, it is folded into the Gate 2 packet draft (no separate escalation needed — Gate 2 hasn't happened yet). If it surfaces *after* Gate 2 approval but before Gate 3, it requires a Gate 2 packet **revision** (§6.5, supersedes the prior one) before implementation continues on the affected part. If it surfaces *after* Gate 3 approval (i.e., post-release), it requires a new Gate 1 (it is operationally a new problem) unless it is a rollback/incident, in which case it follows incident process, not this gate flow, and is retrospectively logged into `OPERATIONAL_GAP_REGISTER.md`.

Worked example from this repository: GAP-031's design process discovered that `SimpleDocumentController::update()`/`createVersion()` allowed direct writes of reserved workflow statuses — an **authorization/data-integrity finding**, i.e. tier-1/tier-2 by the Constitution's own Priority Rule. Under this model, this finding would have required an owner-visible note even though it was discovered *during* implementation-adjacent design work, because it is a security/authorization finding (§5.2, "data visibility" / "risk accepted by the business" adjacent) — see §9 (item 4, "Rủi ro phân quyền nào đã được đóng lại?") for how this is represented in the worked Gate 3 packet. *(Corrected cross-reference in this amendment: the original version pointed to a non-existent "§9.7" — the packet's items are numbered 1–10 within its own body, not as document subsections.)*

### 5.3 Findings that do NOT require owner involvement

No owner decision is needed for implementation choices that remain inside the approved Gate 2 business design:

- class or method names
- refactoring structure
- test mechanics
- framework patterns (e.g. choosing `DB::transaction()` + `lockForUpdate()` vs. an alternative concurrency strategy, as long as the business-visible behavior — "two people cannot approve the same document at once" — is unchanged)
- query implementation
- internal technical organization (e.g. introducing `DocumentWorkflowService` as a single mutation owner)

**Anti-bureaucracy rule:** agents must not manufacture an owner-escalation for a finding that fits §5.3 merely to "be safe." Over-escalation is itself a governance failure — it trains the owner to stop reading packets carefully. When genuinely unsure whether a finding is §5.2 or §5.3, the test is: *does this change what a user can do, what data they can see, who is responsible, or what risk the business carries?* If no to all four, it is §5.3.

---

## 6. Repository-native foundation

### 6.1 Target structure (as specified by the owner; adopted verbatim)

```text
docs/owner-governance/
├── OWNER_OPERATING_MODEL.md
├── OWNER_DECISION_RULES.md
├── OWNER_LANGUAGE_GUIDE.md
├── templates/
│   ├── gate-1-business-request.md
│   ├── gate-2-business-design.md
│   └── gate-3-release-decision.md
└── examples/
    └── GAP-031-owner-release-packet.md

docs/owner-decisions/
└── <WORK-ID>/
    ├── 01-request.md
    ├── 02-design.md
    └── 03-release.md
```

None of these files are created by this spec (scope constraint, §11). This section specifies their exact intended contents for the future implementation task.

### 6.2 Frontmatter (machine-readable fields, per packet file)

Every `docs/owner-decisions/<WORK-ID>/0X-*.md` file carries YAML frontmatter. This block supersedes the flatter `status`/`decision`/`decision_actor` shape used in the original version of this spec — it now carries the canonical status machine (§3.6), the independent technical-readiness/owner-decision pair (§3.7), and an explicit decision-provenance/trust-level record (§6.8a):

```yaml
---
work_id: GAP-031                     # canonical ID — see §6.4 for the locked identity model
gate: 1 | 2 | 3
status: not_started | preparing | blocked_technical | awaiting_owner | approved | changes_requested | deferred | superseded   # §3.6; blocked_technical is Gate-3-only
generated_by: agent                  # see 6.6 — always "agent" for content; never "owner"

technical_readiness:                 # Gate 3 only — Gates 1–2 omit this block entirely (no engineering evidence exists yet)
  value: not_checked | blocked | ready
  generated_by: engineering_evidence

owner_decision:                      # present on all three gates; enum is gate-specific per §5.1, "none" is the not-yet-decided sentinel
  value: none | approved | more_info_requested | declined | deferred | changes_requested | correction_requested
  authority: human_owner

decision_provenance:                 # §6.8a — records HOW the decision was captured, never claims it is authenticated during the repo-native phase
  trust_level: claimed_repo_record | authenticated_decision_center
  recorded_by: <actor or agent identity string>
  recorded_at: <ISO-8601 or null>
  owner_response_reference: <conversation reference (e.g. session/turn) or, post-Decision-Center, a decision event ID — null pre-decision>
  reconciliation_required: true | false

supersedes: null | <path to prior packet this replaces>
superseded_by: null | <path to packet that replaced this one>
links:
  spec: docs/superpowers/specs/<...>-design.md          # EEL
  plan: docs/superpowers/plans/<...>.md                 # EEL, gate 2+ only
  branch: <git branch name>
  pr: <PR URL or null if not yet opened>
  release: <deploy/release reference or null>
---
```

### 6.3 Lifecycle statuses

`status` uses the canonical 8-value machine defined in §3.6, distinct from `owner_decision.value` (§3.7), which is the owner's verb:
- `not_started` — work ID exists (or gate not yet entered), no packet content drafted yet.
- `preparing` — agent-authored content in progress; for Gate 3, this also covers implementation/testing/review (§3.3, §3.4) — not yet presented to the owner.
- `blocked_technical` — **Gate 3 only.** A mandatory engineering gate is red; visible to the owner as a read-only card (§3.5), no decision offered.
- `awaiting_owner` — presented, awaiting owner action. This is the **only** status a queue (repo-native today, Decision Center later — §10) should surface as "needs you," and the only status that exposes a decision action (§3.6).
- `approved` / `changes_requested` / `deferred` — terminal for this packet revision, mirrors the recorded `owner_decision.value` per the mapping in §3.6 (declined folds into `deferred`; more_info_requested/correction_requested fold into `changes_requested`).
- `superseded` — a later revision of the same gate replaced this one (§6.5); the file is kept, never deleted, for history.

### 6.4 Work-ID linkage and identity model (locked)

`work_id` in the packet frontmatter is the join key across: the work's canonical project identifier ↔ `docs/owner-decisions/<WORK-ID>/` ↔ `docs/superpowers/specs/*` ↔ `docs/superpowers/plans/*` ↔ git branch name ↔ PR ↔ eventual release/deploy record.

This identity model is now locked (previously an open question, §12 of the original version):

- **Existing canonical work IDs remain unchanged.** `GAP-*` (`OPERATIONAL_GAP_REGISTER.md`), `ZMC-*`, `WP-*`, and any other already-established project identifier continue to be used exactly as they are today. The owner-governance layer adopts whatever ID a piece of work already has — it does not introduce a competing numbering scheme for work that already carries one.
- **New requests originating directly from the owner** (not sourced from an existing register or audit) use a new prefix: **`OWN-YYYY-NNN`** (e.g. `OWN-2026-001`), where `YYYY` is the calendar year the request was raised and `NNN` is a zero-padded sequence number within that year. This distinguishes "the owner asked for this" from "engineering/audit found this" at a glance, without requiring anyone to open the packet to know the provenance of the request.
- **`work_id` is the single join key** across owner packets, spec, plan, branch, PR, and release record — regardless of which prefix family it belongs to. Tooling (the future lint, §7) treats all prefixes identically once assigned; no prefix is privileged over another for validation purposes.
- **Existing work is never renamed** merely to fit the new owner-governance system. A `GAP-031`-style ID does not become an `OWN-*` ID retroactively because it now also has owner-governance packets — the packets simply reference the ID that already exists.

### 6.5 Superseded decisions

A gate is **never edited in place** once `owner_decision.value` is not `none`. If circumstances change (e.g. Gate 2 design needs revision after an owner-required finding per §5.2), a new file is created: the existing convention in this repository already does this for engineering specs (see `docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md`, which itself documents "rev 3" changes inline — but for *owner* packets, the ruling is stricter: a new **file**, not an inline revision marker, because the original's `decision_provenance` block must remain untouched as history). The superseding file sets `supersedes: <old path>`; the superseded file gets `superseded_by: <new path>` and `status: superseded`. Naming convention: `02-design.md`, `02-design-v2.md`, `02-design-v3.md`, etc. — never overwrite `02-design.md` after it has a non-`none` `owner_decision.value`.

### 6.6 Who/what may generate packet content vs. who may decide

- **Content** (all narrative fields, the recommendation, the `release_recommendation`/`recommendation` field, and — for Gate 3 — the engineering-evidence-derived `technical_readiness` value, §3.7) is always `generated_by: agent` (or `generated_by: engineering_evidence` specifically for `technical_readiness`, which is computed, not opinion). Agents draft every packet field, including the recommended action.
- **The decision** (`owner_decision.value` transition from `none` to a real enum value, plus the `decision_provenance` block, §6.8a) may **only** be set by an explicit owner action, never inferred, defaulted, or pre-filled by an agent. See §8, §6.8a for how this is (and is not) enforced in a repository-native world.
- Agents must never write `owner_decision.value: approved` on behalf of the owner, even if the recommendation was "approve" and it seems obvious. Doing so is the single most dangerous failure mode this design exists to prevent (see the owner's explicit prompt constraint: "The lint must not infer that the owner actually approved something merely because an agent wrote `approved`").

### 6.7 Placeholder and contradiction checks

Any packet containing literal `TBD`, `TODO`, `TBA`, `???`, or an empty required frontmatter field (other than the ones legitimately unset pre-decision: `owner_decision.value: none`, `decision_provenance.recorded_by/recorded_at/owner_response_reference: null`, `supersedes`, `superseded_by`, `pr`, `release`) fails validation (§7 lint contract). A packet whose `status` says `approved` but whose `owner_decision.value` is `none` (or vice versa) is a contradiction and fails validation. A packet claiming `gate: 3` `status: awaiting_owner` while `technical_readiness.value` is not `ready`, or while its `links.pr` has open required CI checks (verifiable via `gh pr checks`), is a structural contradiction — not merely advisory — because §3.6/§3.7 make `technical_readiness: ready` a precondition for `awaiting_owner` to exist at all; the lint must fail this, not warn on it (this tightens the "flag as a warning" language from the original version of this spec — see §7.5 for the one piece that remains genuinely hybrid: live CI status still requires querying GitHub, not just reading the repo tree).

### 6.8 What this repository-native layer honestly cannot do

Stated plainly, because the owner's prompt explicitly required this: **Markdown files in a git repository cannot authenticate a human.** Anyone with write access to the branch can type `decision_provenance.recorded_by: <owner's name>` into a file. This design does not claim otherwise — the `trust_level: claimed_repo_record` value (§6.8a) exists specifically to say so, on every such record. What the repo-native layer *can* do is:
- make the *absence* of a decision structurally obvious (lint fails, §7),
- make *fabricating* a decision require an explicit, visible, reviewable act (a commit, attributable to whichever git identity made it — not invisible),
- make history immutable-by-convention (§6.5 — supersede, don't edit), and
- provide the exact shape of data the future Decision Center (§10) needs to become the authoritative, actually-authenticated source.

Trusting that the person who committed `owner_decision.value: approved` was in fact the owner is a **process/access-control** guarantee (who has push rights to the relevant branch, ideally a protected owner-only decisions branch or a required review from a designated owner GitHub identity), not something Markdown or CI can prove. This is explicitly called out as a known limitation of the repository-native foundation, resolved only when the Decision Center (§10) becomes authoritative.

### 6.8a Decision provenance and trust level (resolves the repository-phase authentication gap explicitly)

Every packet's `decision_provenance` block (§6.2) makes the honesty of §6.8 a structured, machine-readable fact rather than a paragraph of prose the reader has to already trust:

```yaml
decision_provenance:
  trust_level: claimed_repo_record | authenticated_decision_center
  recorded_by: <actor or agent>
  recorded_at: <timestamp>
  owner_response_reference: <conversation or future decision event reference>
  reconciliation_required: true | false
```

**During the repository-native phase (now, and until §10 is built and activated):**
- every owner decision recorded in a packet carries `trust_level: claimed_repo_record` — never `authenticated_decision_center`. This is not a placeholder value pending later upgrade of the *same* record; it is the honest, permanent classification of any decision captured this way, for as long as this phase lasts.
- `recorded_by` names whichever actor (agent session, or a human directly editing the file) committed the change — this is an attribution field, not an authentication field: it says who typed it, not who is verified to have decided it.
- `owner_response_reference` points at the concrete artifact the "claimed" decision traces back to — typically a reference to the working conversation/session in which the owner gave the decision to an agent (§6.9), so the claim is at minimum traceable to a specific exchange, not a bare assertion with no trail at all.
- CI/lint (§7) may validate that this block's **structure** is well-formed and its **enum values** are legal — it must never validate, imply, or report that the decision is *authenticated*. A green lint run on a `claimed_repo_record` packet means "the record is structurally complete," full stop.
- `reconciliation_required` stays `false` during this phase by definition — there is no second, authoritative source yet to diverge from.

**After Decision Center activation (§10):**
- decisions captured through the authenticated Decision Center session carry `trust_level: authenticated_decision_center`, and `recorded_by` becomes a verified user identity rather than a self-reported string (§10.5).
- repository packet files become **synchronized projections** of the app's decision events (§10.10) — their `decision_provenance` block is written by the sync job, not by an agent transcribing a conversation.
- if a repo file's `claimed_repo_record` decision (from before cutover, or from a stray manual edit after cutover) disagrees with the authenticated app record for the same `work_id`/`gate`, `reconciliation_required: true` is set on the repo file and the app record wins (§10.10) — divergence is flagged for reconciliation, never silently overwritten and never silently ignored.

This block is the concrete mechanism referenced informally throughout the rest of this spec (§6.6, §6.8, §10.5) — it does not introduce a new promise beyond what those sections already state; it makes the promise inspectable per-packet.

### 6.9 Repository-native phase: how the owner actually sees a packet (resolves the notification open question)

During the repository-native phase, **notification delivery infrastructure is explicitly out of scope** — there is no email sender, no digest job, no push notification to build or design here. Instead:

- **agents present the packet to the owner directly, inline, in the active working conversation** — the same mechanism already used throughout this repository's session-based workflow (e.g. this very design being presented to the owner for review at the end of a session). A `status: awaiting_owner` packet is something an agent surfaces and reads aloud (in plain Vietnamese, per §6.10) in the conversation where the owner is present, not something the owner is expected to go discover by polling a file or a queue.
- **the repository Markdown file records the claimed decision and its evidence reference** (`decision_provenance`, §6.8a) once the owner responds in that conversation — the file is the durable record of what was presented and what was said back, not the delivery mechanism itself.
- **the file does not authenticate the owner** — this is the same limitation stated in §6.8/§6.8a, restated here because it directly bears on how "notification" works in this phase: there is no login, no verified session, only a conversation an agent participated in and then transcribed into `decision_provenance.owner_response_reference`.

This fully resolves what was previously an open implementation question (§12 of the original version, "notification channel(s) for the repo-native phase") — there is no channel to choose during this phase; presentation happens synchronously, inside the conversation, by design.

### 6.10 `OWNER_LANGUAGE_GUIDE.md` — mandatory but bounded contents

`OWNER_LANGUAGE_GUIDE.md` is required reading for any agent drafting an OCL packet (§2.1, corrected cross-reference — the original version of this spec pointed to §7, which was wrong). Its contents are deliberately narrow:

- **approved plain-Vietnamese replacements for terms that have actually appeared in an owner packet** — e.g. "tenant isolation" → "dữ liệu của một khách hàng không bị khách hàng khác nhìn thấy hoặc thao tác"; "race condition" / "concurrency" → "hai người cùng thao tác trên một hồ sơ cùng lúc"; "RBAC" / "authorization" → "ai được phép làm gì."
- **prohibited or discouraged technical terms** — a short denylist of terms that must never appear untranslated in an OCL packet: class/method names, SQL, HTTP status codes, framework names (Laravel, PHPStan, Sanctum), CI job names, git/branch/PR jargon beyond "the change."
- **examples of acceptable risk wording** — e.g. "nếu phát hành lúc này, có khả năng hai người vô tình ghi đè quyết định của nhau" is acceptable; "TOCTOU race condition in the decide() method" is not.
- **examples of what must remain in the Engineering Evidence Layer** — a short list of content categories (lock strategy, query plans, migration diffs, stack traces) that belong in the EEL and must never be pasted into an OCL packet even as "extra detail for a curious owner."
- **a growth rule: the glossary grows only when a term is actually needed in an owner packet.** No agent may pre-populate `OWNER_LANGUAGE_GUIDE.md` with translations for terms that have not yet come up in a real packet — this keeps the guide a living record of *actual* translation decisions made under real drafting pressure, not a speculative general software-engineering-to-Vietnamese dictionary.

**Explicitly not this document's job:** `OWNER_LANGUAGE_GUIDE.md` is not a general software-engineering glossary, not a Vietnamese localization file for the ZENA WebApp UI, and not a style guide for engineering documentation. It exists solely to keep OCL packet language consistent across work IDs and across agents, one real term at a time. This fully resolves the previously open question (§12 of the original version, "should the language guide include a maintained glossary") — yes, bounded exactly as above.

---

## 7. Owner-governance-lint (design only — not implemented, no script created)

A lightweight structural check, conceptually parallel to the existing `composer ssot:lint` (Constitution Appendix A already requires an evidence-lint discipline; this is its owner-governance analogue), intended to run in CI once implemented. This section specifies its **contract**, not its code.

### 7.1 What it checks

- Required files exist for the current work ID at the current lifecycle stage (e.g. a branch/PR touching implementation for `GAP-xxx` must have `docs/owner-decisions/GAP-xxx/02-design.md` present with `status: approved`).
- Required frontmatter fields are present and non-empty (per §6.2/§6.7), including the `technical_readiness` block on Gate 3 packets and the `decision_provenance` block on every packet.
- No `TODO`/`TBD`/placeholder tokens anywhere in a packet file.
- `work_id` values are internally consistent across `01-request.md`/`02-design.md`/`03-release.md` and match the branch/PR's declared work ID, and conform to the locked identity model (§6.4) — no ad hoc prefixes.
- **Gate 2 must exist and be `status: approved` before an implementation plan (`docs/superpowers/plans/<work-id>-*.md`) is committed.** (Unchanged from the original version — implementation still cannot start without Gate 2 approval; only the *Gate 3* rule below changed.)
- **Merging is blocked for any work ID where Gate 3 owner approval is required, unless the Gate 3 packet's `owner_decision.value: approved` AND `technical_readiness.value: ready` both hold.** This replaces the earlier, overly broad rule that blocked marking a PR "Ready for review" before Gate 3 — engineering preparation, including opening a PR and marking it ready for *technical* review, is permitted throughout `preparing`/`blocked_technical` (§3.3, §3.4); only the merge/release act itself is gated.
- `links.spec` / `links.plan` (Gate 2+) resolve to files that actually exist in the repo (no dangling EEL links).
- `owner_decision.value`, where not `none`, is a member of the fixed enum for that gate (§5.1) — nothing else is accepted; `technical_readiness.value`, where present, is one of `not_checked`/`blocked`/`ready` — nothing else is accepted.

### 7.2 What it explicitly does NOT check (and must not pretend to)

- It cannot verify that `decision_provenance.recorded_by` is really the owner (§6.8, §6.8a) — it can only confirm the block is structurally present and its `trust_level` value is legal.
- It cannot verify that the plain-language content is *actually* free of technical jargon — that is a human/review-time quality bar (§6.10, `OWNER_LANGUAGE_GUIDE.md` — corrected cross-reference from the original version, which mistakenly pointed here), though a narrow keyword denylist (e.g. flagging raw class names, SQL, HTTP status codes appearing in an OCL packet) is a reasonable **advisory** (non-blocking) check to include later.
- It must **not** treat the mere presence of the string `approved` in `owner_decision.value` as proof of a real decision, and must never upgrade a packet's `decision_provenance.trust_level` on its own — this is the owner's explicit red line (§6.6, §6.8a). The lint's job is structural completeness, never decision authenticity.

### 7.3 Failure mode

Lint failures block the same way `composer ssot:lint` and other required CI checks already block today (Constitution Appendix A.11, A.12) — they do not silently warn on required-gate items (missing Gate 2 before a plan exists), but may warn (non-blocking) on advisory items (jargon denylist, §7.2).

### 7.4 Relationship to the existing PR template

The existing `.github/PULL_REQUEST_TEMPLATE.md` (Invariants Checklist, Acceptance Criteria, Evidence/Verification, CI Checks) is the EEL-facing artifact and is **not replaced**. §8.4 above (Pull request template) specifies how an owner-readable summary is added *above* it, and this section's §7.1 specifies how the lint interacts with it — but no template file is modified by this spec (scope constraint, §11). *(Corrected cross-reference in this amendment: the original version of this spec pointed to a non-existent "§9 below (PR/CI behavior)"; §9 is the GAP-031 worked example, not a PR/CI section.)*

### 7.5 Known limitation

Because CI checks (`gh pr checks`) and this repository's actual required-check list can only be queried, not statically known ahead of time (they are configured in GitHub branch protection, not in the repo tree in an easily lint-parseable form for every case), the "all mandatory engineering gates are green" check for Gate 3 entry (§3.3) is necessarily a **hybrid** check: the structural pieces (packet exists, fields populated) are lintable in the repo; the "CI is actually green right now" piece requires querying the PR's live check status (as this design task itself did via `gh pr checks 238`) and cannot be fully captured by a pure static lint. The implementation task should treat this as a GitHub Actions job that calls the GitHub API for live status, not a pure file-tree lint.

---

## 8. Agent behavior changes (specified, not applied)

*None of the following edits are made by this spec. They are the exact amendments a future task should apply.*

### 8.1 `PROJECT_CONSTITUTION.md`

Add a new section — proposed as **§3a, "Owner Gates,"** immediately after §3 (Mandatory Alignment Check) and before §4 (Operational Gap Detection) — stating:
- the three-gate model exists and is mandatory for any change that reaches implementation planning or code (i.e., Gate 1/2 are not optional "if you feel like it" steps);
- the exact gate-entry/exit rule already stated in §3.1–§3.3 above: **no implementation plan before Gate 2 approval; no merge, deployment, production data change, or user release before Gate 3 approval — but implementation, testing, review, technical gate verification, demo preparation, and Gate 3 packet drafting all proceed freely once Gate 2 is approved, without waiting for Gate 3** (§3.3, §3.4 — this replaces a stricter "no release prep before Gate 3" phrasing used in the original version of this spec, which incorrectly implied engineering work itself was gated on owner approval rather than only the release act);
- a pointer to `docs/owner-governance/OWNER_OPERATING_MODEL.md` as the SSOT for gate mechanics, added as a new row in Appendix B's Governance Map table ("Owner decision gates and packets" → `docs/owner-governance/OWNER_OPERATING_MODEL.md`);
- an explicit statement that the existing §8 (Evidence Before Claims) and Appendix A remain **entirely in force and unchanged** — the owner gates are an additional decision layer, not a replacement for engineering evidence discipline.

### 8.2 `docs/agent-ssot-rules.md`

Add a short new rule (proposed **Rule 9**, after the existing Rule 8):
> **9) Owner-facing content is a distinct artifact, not a technical report.** When producing an Owner Decision Packet (`docs/owner-decisions/<WORK-ID>/0X-*.md`), do not cite `route:list`, migration line numbers, or controller code as the packet's *content* — cite them in the linked Engineering Evidence Layer instead. The packet itself must be readable by someone who cannot interpret any of Rules 1–8.

### 8.3 Planning and implementation lifecycle instructions (superpowers skills: `brainstorming`, `writing-plans`, `executing-plans`, `subagent-driven-development`)

Each of these skills' entry checklist gains one new precondition, stated at the skill level (not duplicated per-project): *before entering the skill for a change large enough to warrant its own spec, confirm whether this work has a work ID with an approved Gate 1 (for brainstorming/spec-writing) or approved Gate 2 (for writing-plans/executing-plans). If unclear whether owner gates apply to this repository, check for `docs/owner-governance/` — its presence means gates are mandatory here.* This keeps the change project-scoped (only repos that adopt `docs/owner-governance/` are affected) rather than a global skill behavior change.

### 8.4 Pull request template

A future (not-yet-made) edit to `.github/PULL_REQUEST_TEMPLATE.md` adds one new top section, **above** the existing "SSOT Story Reference" section:

```markdown
## Owner Summary (read this first — no code required)
- Work ID: <GAP-xxx / REQ-xxx>
- Gate status: Gate 1 [approved/pending] · Gate 2 [approved/pending] · Gate 3 [approved/pending]
- Owner packets: `docs/owner-decisions/<WORK-ID>/`
- What this changes for users (one paragraph, plain language):
- Decision needed from the owner right now (or "none — informational"):
```

Everything below this (the existing full template) is unchanged and remains the Engineering Evidence Layer.

### 8.5 Integration review packet / completion / stop-report format

The existing Constitution §10 (Completion Definition) stop-report gains five new required closing sections, appended after the existing evidence list (§8):
1. **Owner Summary** — 3–5 sentences, plain language, what changed for users.
2. **Technical Evidence Appendix** — pointer to the existing full evidence (unchanged from today's stop-report content).
3. **Decision Needed** — explicit statement of what gate (if any) is now `awaiting_owner` (§3.6), or "none — no owner decision required at this time" (including the case where Gate 3 is `blocked_technical` or `preparing`, §3.5 — visible progress, no decision needed).
4. **Residual Risk** — plain-language, business-consequence framing (not "TOCTOU race condition" but "two people could act on the same record at the exact same moment before this fix").
5. **What the owner is NOT being asked to decide** — an explicit negative-space statement, e.g. "the owner is not being asked to approve the choice of database locking strategy, only whether the new 'Approve/Reject' button behavior is correct." This section is what most directly prevents accidental technical escalation (§5.3) — it forces the agent to state the boundary out loud every time.

---

## 9. GAP-031 worked example — sample Gate 3 Owner Release Packet

*This section is the required worked example. It is written as the packet would read, in plain Vietnamese, understandable without reading PR #238 or any source file. It is illustrative content for this design spec — the actual file `docs/owner-governance/examples/GAP-031-owner-release-packet.md` is not created by this spec (scope constraint, §11); this is what it would contain.*

### 9.1 Two Gate 3 machine states, same work ID

The status machine (§3.6) and the technical-readiness/owner-decision contract (§3.7) mean GAP-031's Gate 3 packet would have looked different at two different points in its real history — illustrating that the *same* packet can be legitimately blocked and later legitimately decision-ready, without ever hiding progress from the owner.

**Earlier state — while a mandatory check had not yet produced evidence:**

```text
Status: BLOCKED_TECHNICAL
Reason: Real-MySQL concurrency evidence not yet available
Owner action: None
Approval control: Disabled
```

At this point the owner-visible card (§3.5) would have read, in full: business objective ("đóng lỗ hổng cho phép lách quyền duyệt tài liệu"), delivered progress ("phần lớn code đã xong, đang chờ chạy kiểm tra hai người cùng thao tác một lúc trên MySQL thật"), blocking reason ("một phép kiểm tra an toàn dữ liệu quan trọng chưa chạy xong"), operational risk if released now ("nếu phát hành lúc này, chưa chắc chắn hệ thống xử lý đúng khi hai người duyệt cùng lúc"), next step ("đội kỹ thuật đang hoàn tất và chạy lại kiểm tra"), decision needed ("Không — không cần quyết định vào lúc này"). No approve/reject buttons rendered. Labeled `BLOCKED — OWNER ACTION NOT REQUIRED`.

**Current, verified state — matching this repository's actual state as inspected during this session (`gh pr checks 238`: 30/30 required checks pass, including "Document Workflow Concurrency (real MySQL)"; `gh pr view 238`: still a draft, not merged):**

```text
Status: AWAITING_OWNER
Technical readiness: READY
Owner decision: NONE
Decision requested: Approve release / Request business correction / Defer
```

This is the state in which the plain-Vietnamese packet below would actually be shown to the owner, with a real decision requested.

> **Gói quyết định phát hành — GAP-031: Duyệt hồ sơ tài liệu**
>
> **1. Vấn đề đã xảy ra là gì?**
> Trên trang web (không phải qua API kỹ thuật), nút "Duyệt" và "Từ chối" một tài liệu thực ra không hoạt động đúng — chức năng này chưa từng được nối vào bất kỳ đường dẫn nào mà người dùng thật có thể bấm tới, và nếu vô tình chạm được, nó sẽ ghi vào một trạng thái ("pending") không tồn tại ở bất kỳ nơi nào khác trong hệ thống, dẫn tới hồ sơ tài liệu bị mắc kẹt, không rõ ai là người duyệt, không có dấu vết ai đã duyệt. Cùng lúc đó, đội kỹ thuật phát hiện một lỗ hổng nghiêm trọng hơn: một người chỉ có quyền "sửa tài liệu" (không có quyền "duyệt tài liệu") vẫn có thể tự ghi trạng thái "đã duyệt" hoặc "bị từ chối" thẳng vào hồ sơ mà không cần ai thực sự bấm nút duyệt — nghĩa là quyền duyệt có thể bị lách hoàn toàn.
>
> **2. Người dùng nào bị ảnh hưởng?**
> Bất kỳ ai có quyền duyệt tài liệu trong dự án (thường là quản lý dự án/PM), và bất kỳ ai nộp tài liệu chờ duyệt. Trước đây, họ phải dùng đường kỹ thuật (API) để duyệt/từ chối vì màn hình web không hoạt động đúng.
>
> **3. Bây giờ người dùng có thể làm gì?**
> Trên giao diện web, người có quyền duyệt tài liệu có thể mở danh sách "Chờ duyệt," bấm Duyệt hoặc Từ chối trực tiếp, và hệ thống ghi lại rõ ràng: ai đã quyết định, quyết định gì, khi nào, kèm ghi chú nếu có. Vòng đời tài liệu rõ ràng và duy nhất là: **Nháp → Đã nộp → (Đã duyệt hoặc Bị từ chối)**. Không còn tồn tại đường vòng nào để bỏ qua bước duyệt.
>
> **4. Rủi ro phân quyền nào đã được đóng lại?**
> Trước đây, người chỉ có quyền "sửa" — không có quyền "duyệt" — có thể tự đặt trạng thái tài liệu thành "đã duyệt" hoặc "bị từ chối" bằng một đường ghi dữ liệu khác (không qua nút Duyệt), coi như tự cấp quyền duyệt cho chính mình. Việc này đã được chặn hoàn toàn: chỉ có đúng một cách hợp lệ để một tài liệu chuyển sang "đã duyệt"/"bị từ chối," và cách đó luôn đi qua kiểm tra quyền "duyệt tài liệu." Đồng thời, thông tin "ai đã duyệt, khi nào, ghi chú gì" cũng được khoá lại — trước đây một người có quyền sửa vẫn có thể ghi đè hoặc xoá thông tin này mà không cần thực sự thực hiện hành động duyệt.
>
> **5. Đã kiểm thử những gì?**
> Toàn bộ 30 kiểm tra tự động bắt buộc của hệ thống đều đạt (bao gồm kiểm tra riêng cho tình huống hai người cùng bấm Duyệt một tài liệu cùng lúc — hệ thống xử lý đúng, không bị lỗi hay ghi đè sai). Đã kiểm tra: dữ liệu tài liệu của một khách hàng không thể bị khách hàng khác nhìn thấy hoặc thao tác (cách ly dữ liệu theo tổ chức). Đã kiểm tra: người không có quyền duyệt bị chặn đúng ở mọi đường, kể cả đường vòng đã phát hiện ở mục 4.
>
> **6. Điều gì KHÔNG nằm trong phạm vi lần này?**
> Không có thay đổi cấu trúc dữ liệu (không thêm bảng/cột mới). Không khôi phục lại trạng thái "pending" cũ — nó bị xoá hẳn, không dùng nữa.
>
> **7. Vì sao GAP-032 và GAP-033 vẫn để riêng, chưa xử lý ở đây?**
> - **GAP-032:** hiện tại trường "trạng thái tài liệu" vẫn có thể được đặt thành các giá trị cũ không thuộc quy trình duyệt (ví dụ "active," "review" — kế thừa từ trước). Việc này không tạo ra lỗ hổng phân quyền (đã được kiểm soát ở mục 4), nhưng cần một quyết định nghiệp vụ riêng: các trạng thái cũ đó có ý nghĩa gì, có cần đưa vào quy trình duyệt hay không — nên tách thành một quyết định khác, không trộn vào lần sửa lỗi bảo mật này.
> - **GAP-033:** hiện tại quyền "duyệt tài liệu" là quyền theo vai trò trong toàn bộ tổ chức (ví dụ: bất kỳ PM nào cũng duyệt được), chứ chưa phải "người này được chỉ định duyệt hồ sơ này." Muốn tài liệu xuất hiện trong mục "việc cần tôi làm hôm nay" của từng cá nhân, cần có cơ chế chỉ định người duyệt cụ thể — đây là một tính năng mới, cần thiết kế và quyết định riêng, không phải một phần của việc vá lỗ hổng bảo mật lần này.
>
> **8. Rủi ro còn lại là gì?**
> Không có rủi ro về mất dữ liệu hoặc lộ dữ liệu giữa các khách hàng. Rủi ro còn lại thuần tuý là về phạm vi sản phẩm: các trạng thái tài liệu kiểu cũ (GAP-032) và việc chưa có "người duyệt được chỉ định riêng" (GAP-033) vẫn tồn tại như trước — không tốt hơn, nhưng cũng không xấu đi so với trước khi có bản sửa này.
>
> **9. Có thể hoàn tác không?**
> Có. Thay đổi này không đổi cấu trúc dữ liệu, chỉ đổi luồng xử lý và kiểm tra quyền — có thể quay lại phiên bản trước nếu cần, không ảnh hưởng dữ liệu đã có.
>
> **10. Đề xuất của đội kỹ thuật:** Phát hành (Approve). Toàn bộ kiểm tra bắt buộc đã đạt, rủi ro bảo mật đã đóng, không có thay đổi dữ liệu, có thể hoàn tác an toàn.
>
> **Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

**What the packet does not ask the owner to do:** at no point does the packet (either the machine-state snapshot in §9.1 or the plain-Vietnamese narrative above) ask the owner to open PR #238, read a CI check name or log, look at a MySQL query, read a line of source code, or read a review comment. Every claim of fact in the packet (30/30 checks pass, the concurrency test exists and passes, tenant isolation holds, the authorization bypass is closed) is stated as a plain-language conclusion, with the underlying evidence available only through the EEL link (§2.3) — never as something the owner is asked to go verify personally.

Note on current real-world state (traced during this design session, not fabricated): PR #238 is presently a **draft**, all 30 required CI checks pass, and it is explicitly not marked Ready for review or merged — consistent with this spec's scope constraint (§11) and with Gate 3 not yet having been formally introduced into this repository's process. Per §3.3/§3.4 (corrected in this amendment), PR #238 being a draft does not, by itself, mean Gate 3 is blocked — draft status is an engineering-preparation signal, not a `technical_readiness` input; the packet's `technical_readiness: ready` in §9.1 reflects that every mandatory check already passes. The sample above is what the packet *would* say if Gate 3 existed today; it does not assert that a real Gate 3 decision has been made for PR #238, and marking PR #238 Ready remains outside this session's scope (§11).

---

## 10. In-app Decision Center (future — design only, not implemented)

### 10.1 Queues

Four queues, each scoped to the viewer's tenant unless the viewer holds a system-level owner role (see §10.9): **Gate 1 (Business Requests)**, **Gate 2 (Business Designs)**, **Gate 3 (Releases)**, and **Owner Questions** (a lightweight catch-all for "more info requested" round-trips that don't fit neatly into a gate resubmission — e.g. a clarifying question raised by engineering mid-Gate-2 that needs a quick owner answer without a full packet revision).

### 10.2 Decision cards

Each queue item renders as a card summarizing the OCL packet's core fields (problem/outcome for Gate 1, before/after workflow for Gate 2, demo result/residual risk for Gate 3) with a single "open" action revealing the full one-page packet — mirroring the repository-native Markdown 1:1 in content, not a redesigned narrative (the repo file and the in-app card must never diverge in what they claim — see §10.10 on reconciliation). The Gate 3 queue additionally surfaces `blocked_technical` and `preparing` packets as read-only cards per §3.5 — visible for transparency, rendered with no decision buttons, distinctly labeled `BLOCKED — OWNER ACTION NOT REQUIRED` (§3.5), so the owner can see release progress without a queue item ever implying a decision is wanted.

### 10.3 Actions

Exactly the fixed enum per gate (§5.1), rendered as buttons: Approve / [Request info | Request changes | Request correction] / [Decline | Defer]. No free-form status field is ever exposed as an action — only the enum. A free-text rationale box is available alongside every action, optional except when declining or requesting changes/correction (a reason is required to send work back).

### 10.4 Immutable audit events

Every action emits an append-only event: `{work_id, gate, owner_decision, actor_id, actor_display_name, timestamp, rationale, packet_revision_id}`. Events are never edited or deleted; a changed mind produces a new event (and, per §6.5, a new packet revision), never a mutation of the old one.

### 10.5 Decision actor and timestamp

The actor is the **authenticated ZENA WebApp user** who performed the action — this is the mechanism that finally closes the gap named in §6.8/§6.8a (repo-native Markdown cannot authenticate a human; an authenticated in-app session can). The Decision Center is the first point in this entire design where `decision_provenance.recorded_by` stops being a self-reported string (`trust_level: claimed_repo_record`) and becomes a verified identity (`trust_level: authenticated_decision_center`).

### 10.6 Links to demos and evidence

Each Gate 3 card links out to (a) the demo script/result inline in the card (no click needed to read it — it's short by construction, §3.3), and (b) an optional "Technical details" expander that surfaces the EEL links (spec/plan/PR/CI) for anyone who wants to go deeper — collapsed by default, never required reading.

### 10.7 Notification behavior (resolved — no longer an open question)

**Authenticated in-app notification is primary.** The owner is notified inside the Decision Center itself, tied to their authenticated session, when a packet enters `awaiting_owner` (§3.6) for any queue they have visibility into. Engineering/agents are notified when a decision is recorded (approved/changes-requested/deferred/etc.) so implementation can resume or stop.

**Email or Zalo may only link or remind — never carry the decision itself.** An optional email/Zalo message may tell the owner "a release is awaiting your decision, open the Decision Center," with a deep link — but the message itself is not, and must never become, a place to reply "approved." Nothing about this design should ever grow into "reply APPROVE to this email/Zalo message" — that would silently downgrade the same authentication gap this design closes with the Decision Center (§10.5, §6.8a) back into an unauthenticated channel.

**Decisions made solely through email or Zalo are not authoritative.** If an owner replies to a reminder message with something that reads like a decision ("ok duyệt luôn"), that reply is, at most, a signal for an agent/engineer to go present the packet properly and record it — through the authenticated Decision Center once built (`trust_level: authenticated_decision_center`), or, during any transitional period, through the same conversation-based `claimed_repo_record` path described in §6.9. An email/Zalo reply is never itself written into `decision_provenance` as the recorded decision.

**Repository artifacts remain synchronized projections** (§10.10) — the notification's deep link points at the app, and any repo-native packet file that mirrors the resulting decision is written by the sync job afterward, not by parsing the reminder or its reply.

No notification fires on `not_started`/`preparing`/`blocked_technical` status — only on `awaiting_owner` and on a recorded decision, keeping volume low and signal high (avoiding the "over-escalation trains the reader to stop reading" failure named in §5.3).

### 10.8 Permission model

A new permission class, distinct from existing `PROJECT_CONSTITUTION.md` Appendix A.9 RBAC roles (super_admin/PM/Member/Client, etc.): **`owner.decide`** — held only by the actual business owner(s), never granted to engineering/agent-facing roles. `owner.decide` is what gates the Approve/Decline/Defer actions in the Decision Center; anyone can *view* an `awaiting_owner` card, and — per §3.5 — a `blocked_technical` card too (transparency in both cases), but only `owner.decide` holders can act, and only on cards that are actually `awaiting_owner`. This mirrors the existing pattern in this codebase of a tightly-scoped permission gating a decision action (e.g. `document.approve` in GAP-031 itself) rather than inventing a new authorization mechanism.

### 10.9 Tenant and system-level scope

ZENA WebApp is multi-tenant (`PROJECT_CONSTITUTION.md` Appendix A.7). Product/governance decisions of the kind this design covers are about the **ZENA product itself**, not a single tenant's data — so `owner.decide` is a **system-level** permission (analogous to today's `/admin/*` system-wide surface, Appendix A.1), not a per-tenant one. The Decision Center is not a tenant-facing feature; it is an internal product-governance tool that happens to be built inside the same WebApp codebase. This must not be confused with any existing tenant-scoped "approval" feature (e.g. `document.approve` inside a tenant's project) — different concern, different permission, different queue.

### 10.10 Integration with repository work IDs/PRs and reconciliation on divergence

The Decision Center reads/writes the same `work_id` join key as the repository-native layer (§6.4). While the repo-native layer is the *only* mechanism (pre-Decision-Center), the two must eventually run in parallel during migration, and after that the app is authoritative while the repo stays a synchronized **projection** (per the owner's explicit framing) for agents/CI that only know how to read files. Failure/reconciliation rule: if the app's recorded decision and the repo file's `owner_decision.value` ever disagree, **the app record wins** once the app is authoritative, `reconciliation_required: true` is set on the affected repo packet's `decision_provenance` block (§6.8a), and a reconciliation job overwrites the repo file's frontmatter (never its narrative content) to match, appending a `superseded_by`/reconciliation note rather than silently rewriting history. Until the app is authoritative (i.e., during the repo-native-only phase this spec covers), there is no app record to reconcile against and `reconciliation_required` stays `false` by definition (§6.8a) — this rule only takes effect once §10 is actually built, which this spec does not authorize (§11).

---

## 11. Scope constraints (restated, binding)

This spec authorizes **no implementation**. Specifically not done as part of this task: the Decision Center, any production controller or schema change, any CI script (including the lint described in §7), any PR template edit, any `PROJECT_CONSTITUTION.md`/`docs/agent-ssot-rules.md` amendment, marking PR #238 Ready, or any merge/deploy action. Only this specification document is written and committed.

---

## 12. Open implementation questions (flagged, not resolved here)

Three items previously listed here are now resolved by this amendment and removed from this list: the work-ID prefix question (§6.4, locked: existing IDs unchanged, `OWN-YYYY-NNN` for owner-raised requests), the repository-native-phase notification channel question (§6.9/§10.7, resolved: conversation-presentation now, no channel to choose), and the language-guide scope question (§6.10, resolved: bounded, grows only on demonstrated need). One item remains genuinely open:

- Exact enforcement mechanism for "only the real owner can set `owner_decision.value`" during the repo-native-only phase — branch protection + CODEOWNERS restricted to the owner's GitHub identity is the most credible near-term mitigation, but is still a process control, not a technical proof (§6.8, §6.8a). This should be decided and documented in `OWNER_OPERATING_MODEL.md` at implementation time. This is intentionally left open rather than resolved by this amendment: it is an access-control/infrastructure decision (which GitHub identity is "the owner," how branch protection is configured) that depends on facts outside this repository's Markdown (real GitHub org membership, real owner account), not a governance-design question this spec can settle by specification alone.

---

## 13. Self-review (re-run in full for this amendment)

**Placeholders:** none found — a full pass confirms no `TBD`/`TODO`/`TBA` markers remain in the amended text; §12 now lists exactly one genuinely open question (down from four), correctly framed as an implementation-time access-control decision, not an unfinished spec section.

**Contradictions checked, general pass:**
- The corrected Gate 3 language now agrees everywhere it is mentioned: §3.3 (main definition), §3.4 (canonical sequence), §3.6 (status machine), §3.7 (readiness/decision contract), §4 (flow diagram), §7.1 (lint contract), §8.1 (constitution amendment), §9 (worked example) all state the same rule — engineering preparation is unblocked by Gate 2 approval alone; only merge/deploy/production-data-change/user-release/claiming-approval are blocked pre-Gate-3. No section still says or implies "no release prep before Gate 3."
- Two stale cross-references from the original version are fixed: §2.1's language-guide pointer (was "§7," now "§6.10") and §7.4's PR/CI pointer (was a non-existent "§9 below (PR/CI behavior)," now "§8.4 above"); §5.2's "§9.7" pointer is corrected to reference the packet's own item 4 by name, since the packet's numbered list is not a set of document subsections.
- Terminology is now consistent throughout: every remaining reference to the old flat `decision`/`decision_actor`/`draft`/`pending_decision` vocabulary has been updated to `owner_decision.value` / `decision_provenance.recorded_by` / `not_started`/`preparing` / `awaiting_owner` (§3.6, §6.2, §6.3, §7.1, §7.2, §8.5, §9.1, §10.4, §10.5, §10.8).

**Confirmation of the 8 points required by this amendment:**

1. **No circular dependency between Gate 3 preparation and Gate 3 approval.** Preparation (implementation, testing, review, CI, demo prep, packet drafting — §3.3's list) is authorized solely by Gate 2's `owner_decision.value: approved`, which happened earlier and independently. Gate 3 approval is authorized solely by reaching `status: awaiting_owner`, which itself depends only on `technical_readiness: ready` (§3.7) — an engineering-evidence fact, not a prior owner decision on this same gate. Nothing in §3.3–§3.7 requires a Gate 3 decision to exist before Gate 3 preparation can occur, and nothing requires Gate 3 preparation to reference a not-yet-existing decision. The dependency graph is strictly one-directional: Gate 2 approval → preparation → readiness → decision-eligibility → decision → release.
2. **Technical blockers remain visible to the owner but cannot be overridden.** §3.5 specifies the exact read-only card shown during `blocked_technical`/`preparing`, with a mandatory `BLOCKED — OWNER ACTION NOT REQUIRED` label. §3.3 ("what the owner may NOT override") and §3.7 (owner approval never converts a red `technical_readiness` to `ready`) both independently state the override prohibition. §10.2/§10.8 extend this to the future Decision Center: blocked cards render in the queue with zero decision buttons, and `owner.decide` permission only acts on cards that are actually `awaiting_owner`.
3. **Technical readiness and owner approval are represented independently.** §3.7 defines the two fields with distinct `generated_by`/`authority` provenance, states both required invariants explicitly (readiness never implies approval; approval never implies readiness), and §6.2's frontmatter carries them as two separate YAML blocks, never merged into one.
4. **Stale owner decisions cannot survive a newly red mandatory technical gate.** §3.6's "Automatic revert rule" specifies that a post-`awaiting_owner` regression forces `status: blocked_technical` and invalidates any pending decision request; a previously recorded `approved` is not deleted (immutable history, §6.5) but does not carry forward — a fresh packet revision must independently reach `awaiting_owner` again. §3.7's release-eligibility conjunction is stated as evaluated at release time, not cached from an earlier moment, closing the same gap from the release-gating side.
5. **Existing work IDs remain compatible.** §6.4 locks `GAP-*`/`ZMC-*`/`WP-*` and any other already-established identifier as unchanged and un-renamed; only new owner-raised requests get the new `OWN-YYYY-NNN` prefix. The GAP-031 worked example (§9) continues to use `GAP-031` throughout, unmodified by this amendment's identity-model changes — direct evidence the locked model doesn't disturb existing IDs.
6. **Repository records do not pretend to authenticate the owner.** §6.8 (unchanged claim) plus the new §6.8a make this a structured, inspectable fact via `decision_provenance.trust_level: claimed_repo_record` during the repo-native phase, explicitly never `authenticated_decision_center` until the Decision Center exists (§10.5). §7.2 explicitly forbids the lint from validating or implying authentication. §6.9 states plainly that conversation-based presentation has no login and no verified session.
7. **Notification and language-guide questions are no longer left open.** §12 now carries exactly one open question (the access-control enforcement mechanism, deliberately left for implementation since it depends on real GitHub identity/org facts outside this repo). The repo-native notification question is resolved in §6.9 (conversation-based presentation, no channel to choose) and §10.7 (future Decision Center: authenticated in-app primary, email/Zalo link-and-remind-only, never decision-bearing). The language-guide scope question is resolved in §6.10 (bounded contents, grows only on demonstrated need, explicitly not a general engineering glossary).
8. **No new bureaucracy beyond the three approved gates.** The amendment adds zero new gates — Gate 3 still has exactly one owner decision point (`awaiting_owner`), not two (there is no separate "readiness review" gate a human sits through; readiness is computed from evidence, §3.7). The 8-value status machine (§3.6) is reused identically across all three gates rather than each gate inventing its own vocabulary, and outcomes that don't map to a distinct lifecycle stage (`declined`, `correction_requested`, `more_info_requested`) are folded into the existing `deferred`/`changes_requested` statuses via `owner_decision.value` rather than growing the status enum — a deliberate check against status-enum sprawl. The blocked-card visibility mechanism (§3.5) adds owner-facing transparency without adding an owner-facing decision — it is strictly additive to what the owner can *see*, not to what the owner is *asked to do*.

**Accidental owner involvement in technical choices:** re-checked §3.2 (Gate 2 fields, unchanged by this amendment) and the new §3.7 frontmatter fields — `technical_readiness` is explicitly `generated_by: engineering_evidence`, never owner-authored or owner-facing as a field to fill in; the OCL-facing `technical_safety_summary` (§3.3) remains prose, not the raw field.

**Unauthenticated approvals represented as trustworthy:** confirmed no change weakens this from the original version — if anything, §6.8a strengthens it by making `trust_level` a required, validated (structurally, not semantically) field on every packet rather than a claim made only in prose.

**Duplicate sources of truth:** the new `OWN-YYYY-NNN` identity model (§6.4) does not create a second work-tracking system — it is a naming convention within the single `work_id` join key already established, applied only to the subset of requests that don't already have an ID from an existing system.

**Excessive bureaucracy:** re-confirmed via point 8 above; additionally, `decision_provenance` (§6.8a) was checked against the risk of becoming its own mini-bureaucracy — it has exactly 5 fields, all either computed/attributed automatically (`trust_level`, `recorded_by`, `recorded_at`) or null until a decision exists, none requiring separate manual owner input beyond the decision itself.

**Unclear Gate entry/exit conditions:** the new §3.6 transition tables make Gate 1/2/3 entry/exit fully explicit as state-machine edges, strictly more precise than the prose-only criteria in the original version; cross-checked that every edge in §4's flow diagram has a corresponding transition in §3.6's tables (no diagram edge without a matching machine transition, no machine transition absent from the diagram).

**Conflict with the current Project Constitution:** re-checked against `PROJECT_CONSTITUTION.md` §5 (Priority Rule) and §6 (Hard Constraints) — unchanged conclusion from the original self-review: this amendment does not relax any technical invariant; it more precisely separates *when engineering may work* (now correctly: as soon as Gate 2 is approved, per Constitution §7's existing Working Loop, which was never gated on a third approval in the first place) from *when the business may release* (still gated on Gate 3, still unable to override tier-1/tier-2 invariants). This amendment is, if anything, a closer fit to the existing Constitution than the original version — the original's implied "no engineering work before Gate 3" was never actually consistent with Constitution §7's Working Loop, which expects Implement → Test → Adversarial review → Operational simulation to happen as a matter of course, not to wait on a release-authorization step.

No unresolved issues from this re-run. §12's single remaining open item is correctly deferred as an infrastructure/access-control decision outside a specification-only session's authority to settle.
