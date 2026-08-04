# Owner Control Layer — Repository Governance Foundation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Stand up the repository-native half of the Owner Control Layer — machine-checkable gate packets, owner-facing operating documents, a structural `owner-governance-lint`, and the agent/PR/CI amendments that make every future AI-assisted change automatically produce an owner-readable decision layer, a linked engineering-evidence layer, and honest (non-authenticated) decision provenance — without building the in-app Decision Center or touching any production business logic.

**Architecture:** Pure Markdown + YAML frontmatter packets under `docs/owner-decisions/<WORK-ID>/`, validated by a standalone PHP CLI (`scripts/ssot/owner_governance_lint.php`, using the already-vendored `symfony/yaml`, no Laravel bootstrap) that is wired into a new, docs-path-triggered GitHub Actions workflow. A JSON/YAML schema file is the single source of truth for allowed fields/enums, consumed by both the lint and its PHPUnit-wrapped fixture tests. `PROJECT_CONSTITUTION.md`, `docs/agent-ssot-rules.md`, and `.github/PULL_REQUEST_TEMPLATE.md` gain new sections; no existing technical section is removed, only reorganized beneath an owner-readable summary.

**Tech Stack:** PHP 8.2 CLI script (`vendor/symfony/yaml`, already present via transitive dependency — verified below), PHPUnit (`Tests\Unit`, plain `TestCase`, no DB — following the existing `RouteSsotGuardTest` pattern), GitHub Actions (new workflow file, modeled on `.github/workflows/routes-guardrails.yml`), `gh api` for branch-protection/CODEOWNERS configuration, plain Markdown for all owner/EEL documents.

## Global Constraints

*(Copied verbatim from `docs/superpowers/specs/2026-08-04-non-technical-owner-control-layer-design.md`, commit `119f3b25c24dfb1cd1e28c72aafdfe07a5880f77`. Every task below implicitly includes all of these.)*

- Exactly three owner gates: Gate 1 (Business Request), Gate 2 (Business Design), Gate 3 (Release).
- One-page owner packet cap (~500–700 words, no code blocks, no route tables) per gate packet.
- Controlled plain Vietnamese for every Owner Control Layer (OCL) field; technical terminology is translated or excluded, never pasted raw.
- `technical_readiness` and `owner_decision` are independent fields — an agent must never translate `technical_readiness: ready` into `owner_decision: approved`, and owner approval must never convert a red `technical_readiness` into `ready`.
- Only `status: awaiting_owner` (this plan names the field `gate_status`, reconciled below) exposes owner decision actions.
- Mandatory technical gates (data integrity, tenant isolation, security, authorization, required CI, failed migrations, destructive-data risk, missing mandatory evidence) can never be overridden by an owner decision.
- Technically blocked work (`blocked_technical`) remains visible to the owner as a read-only card labeled `BLOCKED — OWNER ACTION NOT REQUIRED`, with no decision action exposed.
- A mandatory technical gate turning red after `awaiting_owner` is reached automatically reverts the packet to `blocked_technical` and invalidates any pending/stale decision request.
- Existing work IDs (`GAP-*`, and any other already-established identifier) are never renamed to fit this system.
- New owner-raised requests (not sourced from an existing register) use `OWN-YYYY-NNN`.
- Markdown files and CI cannot authenticate the owner — every decision record must carry a `decision_provenance.trust_level` that is honest about this (`claimed_repo_record` during this phase, never `authenticated_decision_center`).
- No notification infrastructure is built in the repository-native phase — packets are presented to the owner in the active working conversation.
- No in-app Decision Center implementation is in scope for this plan.
- No changes to production business logic, controllers, or database schema.
- No automatic merge or deploy is introduced by this plan; `owner-governance-lint` only blocks/permits, it never triggers a release action.

**Naming reconciliation (decided during planning, stated once here so every task is consistent):** the approved design (§6.2) calls the packet-lifecycle field `status`; this plan's own task list (Task 1) calls it `gate_status`. This plan adopts **`gate_status`** as the actual frontmatter key everywhere, to avoid a collision with the generic word "status" appearing informally elsewhere in packet prose, and to make grep-based tooling unambiguous. `owner_decision` keeps the name and shape from the design's §3.7/§6.2 unchanged. Two frontmatter fields not present in the design's §6.2 are added because this plan's Task 1 explicitly requires them: `decision_requested` (the human-readable enum of choices currently on offer — `null` unless `gate_status: awaiting_owner`) and `residual_risk_rating` / `mandatory_technical_gate_summary` (Gate 3 only, machine-checkable companions to the prose `residual_risks_plain_language` / `technical_safety_summary` fields the design already specifies as packet body content).

---

## Verified Repository Facts (pre-plan inspection, 2026-08-04)

Recorded per the plan's instruction to state verified facts, not assumptions. Every claim below was checked directly against this repository during this planning session; none is carried over unverified from the design spec.

1. **`PROJECT_CONSTITUTION.md`** — 1 file at repo root, `Version: 1.0`, `Effective: 2026-07-23`. §3 "Mandatory Alignment Check" ends before §4 "Operational Gap Detection" — confirmed insertion point for a new §3a exists cleanly between them. Appendix B "Governance Map" is a Markdown table with one SSOT-topic-to-file row per line — confirmed a new row can be appended without restructuring.
2. **`docs/agent-ssot-rules.md`** — 8 numbered rules, ending at "8) What to include in any agent report." Confirmed a "Rule 9" can be appended.
3. **`OPERATIONAL_GAP_REGISTER.md`** — confirmed GAP-031 entry exists, status `RESOLVED (verified)`, citing this repo's own design/plan docs and `app/Services/DocumentWorkflowService.php` et al. GAP-032/GAP-033 confirmed `OPEN (verified)`, both explicitly scoped out of GAP-031.
4. **`.github/PULL_REQUEST_TEMPLATE.md`** — exact current content captured (single file, no directory of multiple templates). Sections in order: `SSOT Story Reference`, `Summary`, `Invariants Checklist (MUST)`, `Acceptance Criteria`, `Evidence / Verification`, `CI Checks (Links)`, `SSOT Backlog Update (REQUIRED)`, `Change Proposal (ONLY if needed)`, `Notes / Follow-ups`. No existing "Owner" concept anywhere in it.
5. **`.github/CODEOWNERS`** — **does not exist.** Confirmed via `find . -iname CODEOWNERS` (no match) — verified fact, not an assumption.
6. **Branch protection on `main`** (`gh api repos/kha997/zenamanagephp/branches/main/protection`, full JSON captured):
   - `required_status_checks.contexts`: **`["test-routes-guardrails"]` only** — i.e. of the ~30 CI checks that actually run on a PR (per `gh pr checks 238`), exactly **one** is a required merge gate today.
   - `required_status_checks.strict`: `true` (branch must be up to date).
   - `enforce_admins.enabled`: `true`.
   - **No `required_pull_request_reviews` block is present in the response at all** — this repository's branch protection does not currently require any PR review (from anyone, code-owner or not) before merge.
   - `allow_force_pushes: false`, `allow_deletions: false`.
7. **Repository collaborators** (`gh api repos/kha997/zenamanagephp/collaborators`): **exactly one** — `kha997`, with `admin/maintain/push/triage/pull` all `true`. `kha997` is simultaneously the repo owner and the only human account with write access to this repository today.
8. **`gh` CLI identity in this environment**: authenticated as `kha997` (keyring), scopes `gist, read:org, repo, workflow`. This means **the acting agent, in this session, already holds credentials sufficient to both draft a governance PR and satisfy any CODEOWNERS-review requirement placed on `kha997`** — a material fact for Task 8's honesty requirements, not merely a footnote.
9. **CI workflows** — 15 files under `.github/workflows/`. The large suite (`automated-testing.yml`, ~1550 lines, 12+ jobs, the same suite that produced the 30 checks on PR #238) declares, on **both** its `pull_request` and `push` triggers:
   ```yaml
   paths-ignore:
     - 'docs/**'
     - '**/*.md'
   ```
   **A documentation-only PR — which is what nearly all `owner-governance-lint`-relevant changes will be — does not trigger `automated-testing.yml` at all**, including its existing `docs-lint.sh` step. This is an existing repository characteristic, not something this plan is asked to fix, but it means `owner-governance-lint` cannot be added as a step inside that suite and expect to run; it needs its own workflow with its own `paths` trigger (Task 9).
10. **`routes-guardrails.yml`** (the one required check) — triggers on `pull_request` (no path filter) and `push: main`; runs a MySQL service, Laravel migrations, two PHPUnit filters, and a route-guard PHP script. Confirmed this is the *only* file whose job name (`test-routes-guardrails`) matches the required-status-check context string.
11. **`composer.json` `ssot:lint` script** (exact chain, `composer.json` read directly): `@ssot:guard-baselines` → `php artisan optimize:clear` → `bash scripts/ssot/dump_routes.sh` → `php scripts/ssot/find_orphan_test_routes.php` → `bash scripts/ssot/lint_tests.sh` → `@lint:domain-ownership`.
12. **`scripts/ci/docs-lint.sh`** — pure Bash, no PHP/Laravel dependency, scans `git ls-files -- '*.md'`, emits `::error file=...,line=...::` GitHub annotations and a `$GITHUB_STEP_SUMMARY` block, explicit PASS/FAIL exit code. Confirmed as the closest existing analogue for a docs-only structural lint and the pattern this plan's `owner_governance_lint.php` follows for its CI-facing output contract (Task 5/9).
13. **`vendor/symfony/yaml` is present** (`composer.lock` shows it as a transitive requirement of `zircote/swagger-php`; the package directory exists on disk under `vendor/symfony/yaml`). Confirmed usable from a standalone PHP CLI script via `require __DIR__.'/../../vendor/autoload.php'; use Symfony\Component\Yaml\Yaml;` — no new Composer dependency needed.
14. **`tests/Unit/RouteSsotGuardTest.php`** — confirmed pattern for a plain `PHPUnit\Framework\TestCase` (no `RefreshDatabase`, no HTTP kernel) asserting on file existence/content, run via `php artisan test --filter <ClassName>`. This plan's Task 5 fixture tests follow this exact pattern.
15. **`.superpowers/sdd/`** — confirmed real, populated ledger from GAP-031's own execution: `progress.md`, `task-N-brief.md`/`task-N-report.md` pairs (10 of each), `review-<sha>..<sha>.diff` per task, and a final `INTEGRATION-REVIEW-PACKET.diff` (commit list + files-changed + full diff, `d9c7ed4d..HEAD`). This is the actual, verified SDD integration-review packet convention this plan's Task 7 must extend, not invent.
16. **No standalone "stop report" template file exists anywhere in the repo.** Stop-report content requirements live entirely inside `PROJECT_CONSTITUTION.md` §8 ("Evidence Before Claims") and §10 ("Completion Definition") — confirmed by full read of the Constitution during the design-spec session and re-confirmed here. Task 6/7 amend those sections directly; there is no separate stop-report file to edit.
17. **Work-ID-to-filename linkage today is informal.** `docs/superpowers/specs/*.md` and `docs/superpowers/plans/*.md` filenames follow `YYYY-MM-DD-<slug>.md`; a GAP ID appears in the slug only when an author chose to include it (e.g. `2026-08-04-gap031-document-approval-workflow-design.md`). There is **no existing enforced 1:1 mapping** between a `GAP-NNN` register entry and a specific spec/plan filename. This plan's `owner-governance-lint` (Task 5/9) is the first mechanism in this repository that will enforce such a mapping, and only for work IDs opted in after the effective date (Task 9's compatibility boundary).
18. **Only `GAP-NNN` (three digits, `GAP-001` through the currently-highest `GAP-033`) is actually found anywhere in this repository as a canonical work-ID prefix** (`grep -rn "ZMC-[0-9]\|WP-[0-9]"` across all tracked Markdown/YAML: **zero matches**). The approved design's §6.4 and this plan's instructions both name `ZMC-*`/`WP-*` as identifier families to preserve — this plan honors that instruction (the schema, Task 1, accepts the pattern generically, so it does not break if such IDs appear later) but records honestly that **no such ID currently exists in this codebase to test against**; GAP-031 is the only real, verifiable non-`OWN-*` work ID available for fixtures.
19. **PR #238 re-verified at plan time**: `gh pr view 238 --json state,isDraft,mergeable` → `{"isDraft":true,"mergeable":"MERGEABLE","state":"OPEN"}`. Unchanged since the design-amendment session. Task 4's Gate 3 `awaiting_owner` worked example is built directly from this live state, not fabricated.

---

## File Structure

```text
docs/owner-governance/
├── OWNER_OPERATING_MODEL.md                    # Task 2
├── OWNER_DECISION_RULES.md                     # Task 2
├── OWNER_LANGUAGE_GUIDE.md                     # Task 2
├── packet-schema.yml                           # Task 1 — single source of truth for frontmatter/enums
├── enforcement-boundary.yml                    # Task 9 — effective-date + legacy exemption
├── legacy-work-ids.txt                         # Task 9 — generated, one work_id per line
├── templates/
│   ├── gate-1-business-request.md              # Task 3
│   ├── gate-2-business-design.md               # Task 3
│   └── gate-3-release-decision.md              # Task 3
└── examples/
    └── GAP-031-owner-release-packet.md          # Task 4

docs/owner-decisions/
└── GAP-031/
    ├── 01-request.md                            # Task 4
    ├── 02-design.md                             # Task 4
    ├── 03-release.md                             # Task 4 — gate_status: blocked_technical (superseded)
    └── 03-release-v2.md                          # Task 4 — gate_status: awaiting_owner (current)

scripts/ssot/
└── owner_governance_lint.php                    # Task 5

scripts/ci/
└── check-gate3-before-ready.sh                  # Task 9 — the one genuinely hybrid (GitHub-API) check

tests/Unit/OwnerGovernance/
├── OwnerGovernanceLintFixtureTest.php           # Task 5
└── fixtures/
    ├── valid-gate-1.md                          # Task 5
    ├── valid-gate-2.md                          # Task 5
    ├── valid-gate-3-blocked.md                  # Task 5
    ├── valid-gate-3-awaiting.md                 # Task 5
    ├── invalid-missing-frontmatter.md           # Task 5
    ├── invalid-bad-enum.md                      # Task 5
    ├── invalid-status-decision-contradiction.md # Task 5
    ├── invalid-blocked-requests-decision.md     # Task 5
    ├── invalid-todo-placeholder.md               # Task 5
    └── invalid-missing-provenance.md             # Task 5

.github/
├── CODEOWNERS                                    # Task 8 — new file
├── PULL_REQUEST_TEMPLATE.md                      # Task 7 — modified
└── workflows/
    └── owner-governance-lint.yml                 # Task 9 — new file

PROJECT_CONSTITUTION.md                           # Task 6 — modified (new §3a, Appendix B row)
docs/agent-ssot-rules.md                          # Task 6 — modified (new Rule 9)
```

---

### Task 1: Canonical machine-readable contract

**Files:**
- Create: `docs/owner-governance/packet-schema.yml`
- Create: `tests/Unit/OwnerGovernance/fixtures/valid-gate-1.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/valid-gate-2.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/valid-gate-3-blocked.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/valid-gate-3-awaiting.md`
- Test: `tests/Unit/OwnerGovernance/OwnerGovernanceSchemaFixtureTest.php`

**Interfaces:**
- Consumes: nothing (first task).
- Produces: `docs/owner-governance/packet-schema.yml` — the field/enum contract every later task (2, 3, 4, 5, 7) reads from. Produces 4 valid fixture files that Task 5's lint tests reuse verbatim (do not duplicate their content elsewhere — reference these exact paths).

- [ ] **Step 1: Write the schema file**

Create `docs/owner-governance/packet-schema.yml`:

```yaml
# Owner Control Layer — canonical packet frontmatter schema.
# Single source of truth for owner_governance_lint.php (scripts/ssot/owner_governance_lint.php)
# and for every packet template (docs/owner-governance/templates/*.md).
# Do not duplicate these enums inline elsewhere — read this file.

work_id_pattern: '^(GAP-[0-9]{3}|ZMC-[0-9]{3,}|WP-[0-9]{3,}|OWN-[0-9]{4}-[0-9]{3})$'

gates:
  1:
    file_name: "01-request.md"
    gate_status_values: [not_started, preparing, awaiting_owner, approved, changes_requested, deferred, superseded]
    owner_decision_values: [none, approved, more_info_requested, declined, deferred]
    requires_technical_readiness: false
  2:
    file_name: "02-design.md"
    gate_status_values: [not_started, preparing, awaiting_owner, approved, changes_requested, deferred, superseded]
    owner_decision_values: [none, approved, changes_requested, declined]
    requires_technical_readiness: false
  3:
    file_name: "03-release.md"
    gate_status_values: [not_started, preparing, blocked_technical, awaiting_owner, approved, changes_requested, deferred, superseded]
    owner_decision_values: [none, approved, correction_requested, deferred]
    requires_technical_readiness: true

# gate_status -> owner_decision.value compatibility (design §3.6 mapping table).
# A packet whose gate_status is not listed here as a key has no owner_decision
# constraint beyond its per-gate enum above (not_started/preparing/blocked_technical
# all require owner_decision.value == "none").
gate_status_requires_owner_decision:
  not_started: ["none"]
  preparing: ["none"]
  blocked_technical: ["none"]
  awaiting_owner: ["none"]
  approved: ["approved"]
  changes_requested: ["changes_requested", "correction_requested", "more_info_requested"]
  deferred: ["deferred", "declined"]
  superseded: []  # any value is legal on a superseded packet; it is frozen history

technical_readiness_values: [not_checked, blocked, ready]
decision_provenance_trust_level_values: [claimed_repo_record, authenticated_decision_center]
decision_requested_values: [null, "approve_or_proceed", "approve_or_more_info_or_decline_or_defer", "approve_or_changes_or_decline", "approve_or_correction_or_defer"]
residual_risk_rating_values: [none, low, medium, high]

required_top_level_fields:
  - work_id
  - gate
  - gate_status
  - owner_decision
  - decision_requested
  - references
  - decision_provenance
  - supersedes
  - superseded_by
  - timestamps
  - generated_by

required_reference_fields: [spec, plan, branch, pr, release]
required_provenance_fields: [trust_level, recorded_by, recorded_at, owner_response_reference, reconciliation_required]
required_timestamp_fields: [created_at, updated_at]

# Gate-3-only fields (validator must reject their presence on gate 1/2 files
# and require them on gate 3 files).
gate_3_only_fields:
  - technical_readiness
  - residual_risk_rating
  - mandatory_technical_gate_summary

placeholder_tokens: ["TODO", "TBD", "TBA", "???"]
```

- [ ] **Step 2: Write the four valid fixtures**

Create `tests/Unit/OwnerGovernance/fixtures/valid-gate-1.md`:

```markdown
---
work_id: GAP-031
gate: 1
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: "owner (session 2026-08-04, GAP-031 retrofit)"
  recorded_at: "2026-08-04T09:00:00+07:00"
  owner_response_reference: "conversation: GAP-031 owner-control-layer retrofit, 2026-08-04"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-04T09:00:00+07:00"
  updated_at: "2026-08-04T09:00:00+07:00"
generated_by: agent
---

## Vấn đề vận hành

Trên trang web, nút Duyệt/Từ chối một tài liệu không hoạt động đúng — chưa từng được nối vào đường dẫn nào người dùng thật bấm tới được, và một người chỉ có quyền sửa tài liệu vẫn có thể tự ghi trạng thái "đã duyệt"/"bị từ chối" mà không cần ai thực sự bấm nút duyệt.

## Người dùng bị ảnh hưởng

Bất kỳ ai có quyền duyệt tài liệu trong dự án (thường là quản lý dự án), và bất kỳ ai nộp tài liệu chờ duyệt.

## Bằng chứng

Đội kỹ thuật xác nhận: màn hình duyệt trên web chưa từng được nối route thật; đồng thời phát hiện một người chỉ có quyền sửa có thể tự đặt trạng thái duyệt mà không qua bước duyệt thật.

## Tác động nếu không xử lý

Hồ sơ tài liệu tiếp tục bị mắc kẹt hoặc bị lách quyền duyệt; không ai biết chắc ai đã thực sự duyệt cái gì.

## Phạm vi đề xuất

Nối màn hình web vào đúng quy trình duyệt đã có ở API, và khoá đường lách quyền duyệt.

## Loại trừ rõ ràng

Không đổi cấu trúc dữ liệu. Không có "người duyệt được chỉ định riêng cho từng hồ sơ" (đó là việc khác).

## Đề xuất

Xử lý ngay — đây là lỗ hổng phân quyền, không phải cải tiến trải nghiệm.

## Quyết định

☑ Đồng ý tiến hành thiết kế (Gate 2)
```

Create `tests/Unit/OwnerGovernance/fixtures/valid-gate-2.md` (same frontmatter shape, `gate: 2`, `gate_status: approved`, `owner_decision.value: approved`, body covering before/after workflow — full text below):

```markdown
---
work_id: GAP-031
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: "owner (session 2026-08-04, GAP-031 retrofit)"
  recorded_at: "2026-08-04T09:30:00+07:00"
  owner_response_reference: "conversation: GAP-031 owner-control-layer retrofit, 2026-08-04"
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-04T09:30:00+07:00"
  updated_at: "2026-08-04T09:30:00+07:00"
generated_by: agent
---

## Trước / Sau

**Trước:** nút Duyệt/Từ chối trên web không hoạt động; ai có quyền sửa có thể tự đặt trạng thái duyệt.
**Sau:** người có quyền duyệt mở danh sách "Chờ duyệt", bấm Duyệt hoặc Từ chối; hệ thống ghi ai quyết định, khi nào, ghi chú gì. Chỉ một cách hợp lệ để chuyển trạng thái.

## Vai trò bị ảnh hưởng

Quản lý dự án (người duyệt), người nộp tài liệu.

## Được phép / Không được phép

Người có quyền duyệt: Duyệt, Từ chối. Người chỉ có quyền sửa: không còn tự đặt được trạng thái duyệt.

## Trạng thái và bước tiếp theo

Nháp → Đã nộp → (Đã duyệt hoặc Bị từ chối). Không có trạng thái nào khác.

## Ngoại lệ

Nếu người duyệt nghỉ việc giữa chừng, tài liệu vẫn ở "Đã nộp" chờ người có quyền duyệt khác xử lý (không tự động chuyển).

## Hành vi người dùng nhìn thấy

Danh sách "Chờ duyệt" hiển thị đúng tài liệu; nút Duyệt/Từ chối hoạt động thật.

## Kịch bản chấp nhận

Khi người có quyền duyệt bấm Duyệt, trạng thái đổi thành Đã duyệt và người nộp thấy được ai duyệt, khi nào.

## Loại trừ phạm vi

Không có người duyệt được chỉ định riêng cho từng hồ sơ (GAP-033, việc khác).

## Quyết định

☑ Đồng ý tiến hành triển khai
```

Create `tests/Unit/OwnerGovernance/fixtures/valid-gate-3-blocked.md` — `gate: 3`, `gate_status: blocked_technical`, `owner_decision.value: none`, `decision_requested: null`, plus the Gate-3-only fields:

```markdown
---
work_id: GAP-031
gate: 3
gate_status: blocked_technical
technical_readiness:
  value: blocked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: docs/owner-decisions/GAP-031/03-release-v2.md
timestamps:
  created_at: "2026-08-04T10:00:00+07:00"
  updated_at: "2026-08-04T10:00:00+07:00"
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "Chưa chạy xong kiểm tra hai người cùng thao tác một lúc trên MySQL thật."
---

## BLOCKED — OWNER ACTION NOT REQUIRED

**Mục tiêu nghiệp vụ:** đóng lỗ hổng cho phép lách quyền duyệt tài liệu.
**Tiến độ:** phần lớn code đã xong, đang chờ chạy kiểm tra hai người cùng thao tác một lúc trên MySQL thật.
**Lý do chặn:** một phép kiểm tra an toàn dữ liệu quan trọng chưa chạy xong.
**Rủi ro nếu phát hành lúc này:** chưa chắc chắn hệ thống xử lý đúng khi hai người duyệt cùng lúc.
**Bước tiếp theo:** đội kỹ thuật đang hoàn tất và chạy lại kiểm tra.
**Cần quyết định từ chủ doanh nghiệp?** Không.
```

Create `tests/Unit/OwnerGovernance/fixtures/valid-gate-3-awaiting.md` — the current, live state (`gate_status: awaiting_owner`, `technical_readiness.value: ready`, supersedes the blocked one):

```markdown
---
work_id: GAP-031
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-031/03-release.md
superseded_by: null
timestamps:
  created_at: "2026-08-04T11:00:00+07:00"
  updated_at: "2026-08-04T11:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "30/30 kiểm tra bắt buộc đã đạt, gồm kiểm tra hai người cùng thao tác một lúc trên MySQL thật."
---

*(Full owner-facing body reused verbatim from `docs/owner-governance/examples/GAP-031-owner-release-packet.md` — see Task 4, Step 1. Not duplicated here to keep this fixture focused on frontmatter validity; the lint validates frontmatter and placeholder-scans the body, it does not require the body text to be identical across files.)*

## Gói quyết định phát hành — GAP-031: Duyệt hồ sơ tài liệu

Toàn bộ 30 kiểm tra tự động bắt buộc đã đạt. Không có rủi ro dữ liệu hoặc lộ dữ liệu giữa các khách hàng. Có thể hoàn tác an toàn.

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành
```

- [ ] **Step 3: Write the failing fixture-shape test**

Create `tests/Unit/OwnerGovernance/OwnerGovernanceSchemaFixtureTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class OwnerGovernanceSchemaFixtureTest extends TestCase
{
    private function schema(): array
    {
        $path = dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml';
        $this->assertFileExists($path, 'packet-schema.yml must exist (Task 1, Step 1).');

        return Yaml::parseFile($path);
    }

    private function frontmatterOf(string $fixtureRelativePath): array
    {
        $path = __DIR__ . '/fixtures/' . $fixtureRelativePath;
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertNotFalse($content);

        preg_match('/^---\n(.*?)\n---\n/s', $content, $matches);
        $this->assertArrayHasKey(1, $matches, "Fixture {$fixtureRelativePath} must start with a --- frontmatter block.");

        return Yaml::parse($matches[1]);
    }

    public function test_schema_defines_all_three_gates(): void
    {
        $schema = $this->schema();
        $this->assertSame([1, 2, 3], array_map('intval', array_keys($schema['gates'])));
    }

    public function test_valid_gate_1_fixture_matches_schema_enum(): void
    {
        $schema = $this->schema();
        $fm = $this->frontmatterOf('valid-gate-1.md');

        $this->assertSame(1, $fm['gate']);
        $this->assertContains($fm['gate_status'], $schema['gates'][1]['gate_status_values']);
        $this->assertContains($fm['owner_decision']['value'], $schema['gates'][1]['owner_decision_values']);
        $this->assertMatchesRegularExpression('/' . $schema['work_id_pattern'] . '/', $fm['work_id']);
    }

    public function test_valid_gate_2_fixture_matches_schema_enum(): void
    {
        $schema = $this->schema();
        $fm = $this->frontmatterOf('valid-gate-2.md');

        $this->assertSame(2, $fm['gate']);
        $this->assertContains($fm['gate_status'], $schema['gates'][2]['gate_status_values']);
        $this->assertContains($fm['owner_decision']['value'], $schema['gates'][2]['owner_decision_values']);
    }

    public function test_valid_gate_3_blocked_fixture_has_no_decision_requested(): void
    {
        $fm = $this->frontmatterOf('valid-gate-3-blocked.md');

        $this->assertSame('blocked_technical', $fm['gate_status']);
        $this->assertSame('none', $fm['owner_decision']['value']);
        $this->assertNull($fm['decision_requested']);
        $this->assertSame('blocked', $fm['technical_readiness']['value']);
    }

    public function test_valid_gate_3_awaiting_fixture_exposes_decision_requested(): void
    {
        $fm = $this->frontmatterOf('valid-gate-3-awaiting.md');

        $this->assertSame('awaiting_owner', $fm['gate_status']);
        $this->assertSame('ready', $fm['technical_readiness']['value']);
        $this->assertNotNull($fm['decision_requested']);
        $this->assertSame('docs/owner-decisions/GAP-031/03-release.md', $fm['supersedes']);
    }
}
```

- [ ] **Step 4: Run test to verify it fails (RED)**

Run: `php artisan test --filter OwnerGovernanceSchemaFixtureTest`
Expected: FAIL — `packet-schema.yml` and/or fixture files not found (since Steps 1–2 above are the actual creation steps; run this command *before* creating the files to confirm RED, then create them and re-run for GREEN). If executing this plan literally task-by-task, Steps 1–2 already created the files — treat Step 3's test as validating them, and this step as the first real run, expected PASS. If a strict RED/GREEN cycle is required, temporarily `mv docs/owner-governance/packet-schema.yml docs/owner-governance/packet-schema.yml.bak` before this run, confirm FAIL, then `mv` it back.

- [ ] **Step 5: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter OwnerGovernanceSchemaFixtureTest`
Expected: `OK (5 tests, ...)`, all assertions green.

- [ ] **Step 6: Commit**

```bash
git add docs/owner-governance/packet-schema.yml tests/Unit/OwnerGovernance/fixtures/valid-gate-1.md tests/Unit/OwnerGovernance/fixtures/valid-gate-2.md tests/Unit/OwnerGovernance/fixtures/valid-gate-3-blocked.md tests/Unit/OwnerGovernance/fixtures/valid-gate-3-awaiting.md tests/Unit/OwnerGovernance/OwnerGovernanceSchemaFixtureTest.php
git commit -m "feat(owner-governance): add canonical packet schema and valid gate fixtures (task 1)"
```

**Reviewer acceptance criteria:** `packet-schema.yml` is the only place enum values are hard-coded for gates/statuses; all 4 fixtures parse as valid YAML frontmatter and pass `OwnerGovernanceSchemaFixtureTest`; no gate/status/decision enum value appears in the fixtures that is absent from the schema file.

---

### Task 2: Owner operating documents

**Files:**
- Create: `docs/owner-governance/OWNER_OPERATING_MODEL.md`
- Create: `docs/owner-governance/OWNER_DECISION_RULES.md`
- Create: `docs/owner-governance/OWNER_LANGUAGE_GUIDE.md`
- Test: `tests/Unit/OwnerGovernance/OwnerOperatingDocsTest.php`

**Interfaces:**
- Consumes: `docs/owner-governance/packet-schema.yml` (Task 1) — the operating model must name the exact `gate_status` values it documents, and they must match the schema (test enforces this).
- Produces: three Markdown documents other tasks (3, 4, 6, 7) link to by exact path; the boundary-growth rule text in `OWNER_LANGUAGE_GUIDE.md` is quoted verbatim by Task 6's constitution amendment.

- [ ] **Step 1: Write `OWNER_OPERATING_MODEL.md`**

```markdown
# Owner Operating Model

This document is the single source of truth for how the three owner gates work in this repository. It is itself an Engineering Evidence Layer (EEL) document — written for agents and reviewers, not for the owner to read directly (the owner reads gate packets, §"Owner Decision Packets" below, and the plain-Vietnamese templates in `templates/`).

## What the owner decides

Product goals, business scope, business rules, user-visible workflow, acceptable business risk, and whether a verified change may be released — exactly the three gate decisions below. Nothing else.

## What engineering decides

Everything else: class/method names, database schema (within the approved business design), test structure, framework choices, query implementation, internal technical organization, CI configuration, refactoring.

## The three gates

1. **Gate 1 — Business Request Approval.** Is the operational problem real, important, correctly scoped? No implementation plan or code exists yet when this gate opens.
2. **Gate 2 — Business Design Approval.** Does the proposed before/after workflow, role/rule set, and acceptance-scenario list match what the business wants? No implementation plan or code exists yet when this gate opens.
3. **Gate 3 — Release Approval.** Is a *verified, already-built* change ready to go live? Implementation, testing, review, and CI happen freely between Gate 2 approval and Gate 3 — Gate 3 authorizes only the release act (merge/deploy/production-data-change/user-release), never the engineering work that already happened.

## Packet lifecycle (`gate_status`)

`gate_status` moves through exactly these values (full transition tables per gate in `docs/superpowers/specs/2026-08-04-non-technical-owner-control-layer-design.md` §3.6):

```
not_started → preparing → [blocked_technical (Gate 3 only)] → awaiting_owner → approved | changes_requested | deferred
changes_requested → preparing
deferred → preparing | superseded
approved → superseded
```

**Only `gate_status: awaiting_owner` exposes an owner decision action.** Every other value is either "nothing to decide yet" or "already decided, terminal for this packet revision."

## `technical_readiness` vs `owner_decision`

Two independent fields on every Gate 3 packet:
- `technical_readiness.value` (`not_checked`/`blocked`/`ready`) — set exclusively from engineering evidence (CI, review, tenant-isolation/RBAC checks). Never an agent opinion, never owner-set.
- `owner_decision.value` — set exclusively by a recorded owner action. Never inferred from `technical_readiness`.

Release requires `technical_readiness: ready` AND `owner_decision: approved` AND repository requirements (branch protection, required reviews, green required CI) — all three, evaluated at release time, never cached from an earlier moment.

## What the owner may never override

Red status on: data integrity, tenant isolation, security, authorization, required CI, failed migrations, destructive-data risk, missing mandatory evidence. If any of these is red, `technical_readiness` cannot be `ready`, `gate_status` cannot reach `awaiting_owner`, and no approval action is offered — not because the packet is hidden, but because the decision surface does not exist while readiness is not `ready`.

## Owner Decision Packets

One packet file per gate per work ID, at `docs/owner-decisions/<WORK-ID>/0X-<name>.md`, conforming to `docs/owner-governance/packet-schema.yml`. Packet body content follows the matching template in `docs/owner-governance/templates/`.

## Immutability and supersession

A packet is never edited in place once `owner_decision.value` is not `none`. A new file (`03-release-v2.md`, etc.) is created instead, with `supersedes`/`superseded_by` frontmatter linking the two. See `docs/owner-decisions/GAP-031/` for a real example of this (Task 4).
```

- [ ] **Step 2: Write `OWNER_DECISION_RULES.md`**

```markdown
# Owner Decision Rules

Precise escalation matrix: which findings require the owner, which do not.

## Requires owner involvement

A finding requires routing to the owner (a new or amended gate packet) when it changes any of: product goal, scope, business rules, user roles or authority, data visibility, approval responsibility, workflow states, financial or legal behavior, user-facing acceptance criteria, risk accepted by the business, release timing.

## Does NOT require owner involvement

No owner decision is needed for implementation choices that remain inside the approved Gate 2 design: class or method names, refactoring structure, test mechanics, framework patterns, query implementation, internal technical organization.

## The four-question anti-escalation test

Before drafting an owner-facing escalation, an agent must answer:

1. Does this change what a user can do?
2. Does this change what data they can see?
3. Does this change who is responsible for a decision?
4. Does this change what risk the business carries?

**If the answer to all four is no, this is an implementation detail — resolve it technically, do not escalate to the owner.** Manufacturing an owner-escalation for a finding that fails this test is itself a governance failure: it trains the owner to stop reading packets carefully.

## Routing rule by lifecycle position

- Finding surfaces *before* Gate 2 approval → fold into the Gate 2 packet draft, no separate escalation.
- Finding surfaces *after* Gate 2 approval, *before* Gate 3 → requires a Gate 2 packet **revision** (new file, `supersedes` the prior one) before implementation continues on the affected part.
- Finding surfaces *after* Gate 3 approval (post-release) → requires a new Gate 1 (it is operationally a new problem), unless it is a rollback/incident, which follows incident process and is retrospectively logged in `OPERATIONAL_GAP_REGISTER.md`.

## Fixed decision enums (must match `docs/owner-governance/packet-schema.yml` exactly)

```
gate_1 owner_decision.value: none | approved | more_info_requested | declined | deferred
gate_2 owner_decision.value: none | approved | changes_requested | declined
gate_3 owner_decision.value: none | approved | correction_requested | deferred
```

No other values are valid at any gate. `owner-governance-lint` (Task 5) rejects anything else.
```

- [ ] **Step 3: Write `OWNER_LANGUAGE_GUIDE.md`**

```markdown
# Owner Language Guide

Required reading for any agent drafting an Owner Control Layer (OCL) packet field. **Not** a general software-engineering glossary, not a UI localization file, not an engineering style guide — it exists solely to keep OCL packet language consistent across work IDs and agents.

## Growth rule

**The glossary below grows only when a term has actually appeared in a real owner packet.** Do not pre-populate translations for terms that have not yet come up in a real packet — this stays a living record of actual drafting decisions under real pressure, not a speculative dictionary.

## Approved plain-Vietnamese replacements (seeded from the GAP-031 worked example, Task 4 — the first real packet this repo produces)

| Technical term | Plain Vietnamese |
|---|---|
| tenant isolation | "dữ liệu của một khách hàng không bị khách hàng khác nhìn thấy hoặc thao tác" |
| race condition / concurrency | "hai người cùng thao tác trên một hồ sơ cùng lúc" |
| RBAC / authorization | "ai được phép làm gì" |
| rollback | "có thể hoàn tác / quay lại phiên bản trước" |
| audit trail / decision metadata | "ai đã quyết định, quyết định gì, khi nào" |

## Prohibited or discouraged technical terms

Never appear untranslated in an OCL packet field: class/method names (e.g. `DocumentWorkflowService`), SQL, HTTP status codes, framework names (Laravel, PHPStan, Sanctum), CI job names, git/branch/PR jargon beyond "thay đổi này" ("this change").

## Acceptable residual-risk wording (examples)

Acceptable: "nếu phát hành lúc này, có khả năng hai người vô tình ghi đè quyết định của nhau."
Not acceptable: "TOCTOU race condition in the decide() method."

## What must remain in the Engineering Evidence Layer, never pasted into an OCL packet

Lock strategy, query plans, migration diffs, stack traces, CI job names/logs, PHPStan output, route tables.
```

- [ ] **Step 4: Write the failing structural test**

Create `tests/Unit/OwnerGovernance/OwnerOperatingDocsTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class OwnerOperatingDocsTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_all_three_operating_documents_exist(): void
    {
        $root = $this->repoRoot();
        $this->assertFileExists($root . '/docs/owner-governance/OWNER_OPERATING_MODEL.md');
        $this->assertFileExists($root . '/docs/owner-governance/OWNER_DECISION_RULES.md');
        $this->assertFileExists($root . '/docs/owner-governance/OWNER_LANGUAGE_GUIDE.md');
    }

    public function test_operating_model_gate_status_values_match_schema(): void
    {
        $root = $this->repoRoot();
        $schema = Yaml::parseFile($root . '/docs/owner-governance/packet-schema.yml');
        $modelContent = file_get_contents($root . '/docs/owner-governance/OWNER_OPERATING_MODEL.md');

        foreach ($schema['gates'][3]['gate_status_values'] as $value) {
            $this->assertStringContainsString(
                $value,
                $modelContent,
                "OWNER_OPERATING_MODEL.md must mention gate_status value '{$value}' (sourced from packet-schema.yml)."
            );
        }
    }

    public function test_decision_rules_enums_match_schema_exactly(): void
    {
        $root = $this->repoRoot();
        $schema = Yaml::parseFile($root . '/docs/owner-governance/packet-schema.yml');
        $rulesContent = file_get_contents($root . '/docs/owner-governance/OWNER_DECISION_RULES.md');

        foreach ([1, 2, 3] as $gate) {
            foreach ($schema['gates'][$gate]['owner_decision_values'] as $value) {
                $this->assertStringContainsString(
                    $value,
                    $rulesContent,
                    "OWNER_DECISION_RULES.md gate {$gate} enum listing must contain '{$value}'."
                );
            }
        }
    }

    public function test_language_guide_states_growth_rule(): void
    {
        $root = $this->repoRoot();
        $content = file_get_contents($root . '/docs/owner-governance/OWNER_LANGUAGE_GUIDE.md');
        $this->assertStringContainsString('grows only when', $content);
    }
}
```

- [ ] **Step 5: Run test to verify it fails (RED)**

Run: `php artisan test --filter OwnerOperatingDocsTest`
Expected (before Steps 1–3 exist): FAIL with file-not-found on the first assertion.

- [ ] **Step 6: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter OwnerOperatingDocsTest`
Expected: `OK (4 tests, ...)`.

- [ ] **Step 7: Commit**

```bash
git add docs/owner-governance/OWNER_OPERATING_MODEL.md docs/owner-governance/OWNER_DECISION_RULES.md docs/owner-governance/OWNER_LANGUAGE_GUIDE.md tests/Unit/OwnerGovernance/OwnerOperatingDocsTest.php
git commit -m "docs(owner-governance): add operating model, decision rules, language guide (task 2)"
```

**Reviewer acceptance criteria:** all three documents exist, none contains an enum value absent from `packet-schema.yml`, `OWNER_LANGUAGE_GUIDE.md` explicitly states the bounded-growth rule, none of the three documents describes implementation details (class names, migrations) as something the owner reads.

---

### Task 3: Three owner packet templates

**Files:**
- Create: `docs/owner-governance/templates/gate-1-business-request.md`
- Create: `docs/owner-governance/templates/gate-2-business-design.md`
- Create: `docs/owner-governance/templates/gate-3-release-decision.md`
- Test: `tests/Unit/OwnerGovernance/PacketTemplateTest.php`

**Interfaces:**
- Consumes: `docs/owner-governance/packet-schema.yml` (Task 1) frontmatter field list.
- Produces: three template files that Task 4 copies and fills in for the real GAP-031 packets — every field name used in Task 4's real packets must appear as a placeholder in these templates first.

- [ ] **Step 1: Write `templates/gate-1-business-request.md`**

```markdown
---
work_id: <GAP-NNN or OWN-YYYY-NNN>
gate: 1
gate_status: preparing
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: null
  plan: null
  branch: null
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: <ISO-8601>
  updated_at: <ISO-8601>
generated_by: agent
---

## Owner Summary
<1–2 sentences, plain Vietnamese, no jargon.>

## Vấn đề vận hành
<What is broken today, in terms of a real workflow moment.>

## Người dùng bị ảnh hưởng
<Which roles/people hit this, and when.>

## Bằng chứng
<Plain description of how we know this is real — never a stack trace.>

## Tác động nếu không xử lý
<What keeps going wrong if nothing changes.>

## Phạm vi đề xuất
<One paragraph.>

## Loại trừ rõ ràng
<What this request does NOT cover.>

## Đề xuất
<Team's recommended action — fix now / defer / decline — one sentence why.>

## Decision Needed
Owner chooses one: Approve to proceed to design (Gate 2) / Request more information / Decline / Defer.

## What the owner is NOT being asked to decide
<Explicit negative-space statement — e.g. "not being asked to approve any implementation approach, only whether this problem is real and worth solving.">
```

- [ ] **Step 2: Write `templates/gate-2-business-design.md`**

```markdown
---
work_id: <GAP-NNN or OWN-YYYY-NNN>
gate: 2
gate_status: preparing
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: <path to docs/superpowers/specs/*.md>
  plan: null
  branch: <branch name>
  pr: null
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: null
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: <ISO-8601>
  updated_at: <ISO-8601>
generated_by: agent
---

## Owner Summary
<1–2 sentences.>

## Trước / Sau
**Trước:** <numbered steps, current state>
**Sau:** <numbered steps, proposed state>

## Vai trò bị ảnh hưởng
<Which roles see a change, and what changes for each.>

## Được phép / Không được phép
<Plain statement of who can now do what, what remains blocked.>

## Trạng thái và bước tiếp theo
<If the workflow has states, name them in plain Vietnamese, say what happens next in each.>

## Ngoại lệ
<Known edge cases in business terms.>

## Hành vi người dùng nhìn thấy
<What changes on screen/in notifications, functionally, not visually.>

## Kịch bản chấp nhận
<"Given X, when Y, the system must Z" — becomes the Gate 3 acceptance checklist.>

## Loại trừ phạm vi
<Carried/refined from Gate 1.>

## Decision Needed
Owner chooses one: Approve to proceed to implementation / Request changes to the design / Decline.

## What the owner is NOT being asked to decide
<e.g. "not being asked to approve class names, database tables, or locking strategy — only whether this workflow, these roles, and these rules match what the business wants.">
```

- [ ] **Step 3: Write `templates/gate-3-release-decision.md`**

This template must support both `blocked_technical` and `awaiting_owner` states explicitly, as two labeled variants in one file (the real packets, Task 4, are two separate files, but the template documents both shapes together for the agent drafting them):

```markdown
---
# === VARIANT A: blocked_technical (read-only, no decision offered) ===
work_id: <GAP-NNN or OWN-YYYY-NNN>
gate: 3
gate_status: blocked_technical
technical_readiness:
  value: blocked
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references:
  spec: <path>
  plan: <path>
  branch: <branch name>
  pr: <PR URL or null>
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: <path to the -v2 file once it exists, else null>
timestamps:
  created_at: <ISO-8601>
  updated_at: <ISO-8601>
generated_by: agent
residual_risk_rating: <none|low|medium|high>
mandatory_technical_gate_summary: "<one plain-language line naming which mandatory check has not passed yet — never a CI job name>"
---

## BLOCKED — OWNER ACTION NOT REQUIRED

**Mục tiêu nghiệp vụ:** <one sentence>
**Tiến độ:** <plain-language progress>
**Lý do chặn:** <plain-language blocking reason, never a raw CI job name>
**Rủi ro nếu phát hành lúc này:** <business-terms risk>
**Bước tiếp theo:** <one short phrase>
**Cần quyết định từ chủ doanh nghiệp?** Không.

---

<!--
=== VARIANT B: awaiting_owner (decision offered) — separate file, e.g. 03-release-v2.md, `supersedes` the blocked one ===

work_id: <same>
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references: <same shape as Variant A, now with pr populated>
decision_provenance: <same shape, still claimed_repo_record>
supersedes: <path to the blocked_technical file>
superseded_by: null
timestamps: <new created_at/updated_at>
generated_by: agent
residual_risk_rating: <none|low|medium|high>
mandatory_technical_gate_summary: "<one plain-language line confirming what passed>"
-->

## Gói quyết định phát hành

**1. Vấn đề đã xảy ra là gì?** <carried from Gate 1>
**2. Người dùng nào bị ảnh hưởng?** <carried from Gate 1/2>
**3. Bây giờ người dùng có thể làm gì?** <carried/refined from Gate 2 workflow_after>
**4. Rủi ro nào đã được đóng lại?** <owner-level risk closed, plain language>
**5. Đã kiểm thử những gì?** <plain-language test summary — never a CI job name or log>
**6. Điều gì KHÔNG nằm trong phạm vi lần này?** <exclusions>
**7. Vì sao các gap liên quan vẫn để riêng?** <if applicable>
**8. Rủi ro còn lại là gì?** <residual_risks_plain_language>
**9. Có thể hoàn tác không?** <rollback_impact>
**10. Đề xuất của đội kỹ thuật:** <release_recommendation>

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
<e.g. "not being asked to inspect CI logs, source code, or review comments — only whether the demonstrated behavior and residual risk are acceptable to release.">
```

- [ ] **Step 4: Write the failing template-coverage test**

Create `tests/Unit/OwnerGovernance/PacketTemplateTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class PacketTemplateTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_all_three_templates_exist(): void
    {
        $root = $this->repoRoot();
        $this->assertFileExists($root . '/docs/owner-governance/templates/gate-1-business-request.md');
        $this->assertFileExists($root . '/docs/owner-governance/templates/gate-2-business-design.md');
        $this->assertFileExists($root . '/docs/owner-governance/templates/gate-3-release-decision.md');
    }

    /** @dataProvider requiredSectionsProvider */
    public function test_templates_contain_required_sections(string $file, array $requiredSections): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/owner-governance/templates/' . $file);
        foreach ($requiredSections as $section) {
            $this->assertStringContainsString($section, $content, "{$file} must contain section '{$section}'.");
        }
    }

    public static function requiredSectionsProvider(): array
    {
        $common = ['## Decision Needed', 'What the owner is NOT being asked to decide'];

        return [
            'gate 1' => ['gate-1-business-request.md', array_merge($common, ['## Vấn đề vận hành', '## Loại trừ rõ ràng'])],
            'gate 2' => ['gate-2-business-design.md', array_merge($common, ['## Trước / Sau', '## Kịch bản chấp nhận'])],
            'gate 3' => ['gate-3-release-decision.md', ['BLOCKED — OWNER ACTION NOT REQUIRED', 'Gói quyết định phát hành', 'What the owner is NOT being asked to decide']],
        ];
    }

    public function test_gate_3_template_documents_both_blocked_and_awaiting_variants(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/owner-governance/templates/gate-3-release-decision.md');
        $this->assertStringContainsString('gate_status: blocked_technical', $content);
        $this->assertStringContainsString('gate_status: awaiting_owner', $content);
    }
}
```

- [ ] **Step 5: Run test to verify it fails (RED)**

Run: `php artisan test --filter PacketTemplateTest`
Expected (before Steps 1–3): FAIL, file-not-found.

- [ ] **Step 6: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter PacketTemplateTest`
Expected: `OK (5 tests, ...)`.

- [ ] **Step 7: Commit**

```bash
git add docs/owner-governance/templates/ tests/Unit/OwnerGovernance/PacketTemplateTest.php
git commit -m "docs(owner-governance): add gate 1/2/3 packet templates (task 3)"
```

**Reviewer acceptance criteria:** all three templates present, Gate 3 template documents both `blocked_technical` and `awaiting_owner` variants explicitly, every template has a "Decision Needed" and "What the owner is NOT being asked to decide" section, no template names a database table or class.

---

### Task 4: Complete GAP-031 worked example

**Files:**
- Create: `docs/owner-decisions/GAP-031/01-request.md`
- Create: `docs/owner-decisions/GAP-031/02-design.md`
- Create: `docs/owner-decisions/GAP-031/03-release.md`
- Create: `docs/owner-decisions/GAP-031/03-release-v2.md`
- Create: `docs/owner-governance/examples/GAP-031-owner-release-packet.md`
- Test: `tests/Unit/OwnerGovernance/Gap031WorkedExampleTest.php`

**Interfaces:**
- Consumes: `docs/owner-governance/packet-schema.yml` (Task 1), the four fixtures from Task 1 Step 2 (this task's real files are near-identical in frontmatter shape to those fixtures — the fixtures live under `tests/`, these live under `docs/owner-decisions/GAP-031/`, deliberately duplicated rather than symlinked so the `docs/` tree is self-contained documentation independent of the test tree), the three templates from Task 3.
- Produces: the only real, non-fixture packet set in this plan — consumed by Task 5's lint (as a real-world smoke-test target, not just synthetic fixtures) and Task 10's final verification.

- [ ] **Step 1: Write `docs/owner-decisions/GAP-031/01-request.md`**

Identical structure to Task 1's `valid-gate-1.md` fixture (reuse that exact content — it already **is** GAP-031's real Gate 1 packet, not a synthetic example). Copy the fixture content verbatim to this path.

- [ ] **Step 2: Write `docs/owner-decisions/GAP-031/02-design.md`**

Copy Task 1's `valid-gate-2.md` fixture content verbatim to this path (same reasoning as Step 1).

- [ ] **Step 3: Write `docs/owner-decisions/GAP-031/03-release.md`**

Copy Task 1's `valid-gate-3-blocked.md` fixture content verbatim to this path.

- [ ] **Step 4: Write `docs/owner-decisions/GAP-031/03-release-v2.md`**

Copy Task 1's `valid-gate-3-awaiting.md` fixture content verbatim to this path, but replace the placeholder body comment with the **full real packet body** (this is the one file where the complete plain-Vietnamese narrative belongs, not a shortened stand-in):

```markdown
---
work_id: GAP-031
gate: 3
gate_status: awaiting_owner
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_correction_or_defer"
references:
  spec: docs/superpowers/specs/2026-08-04-gap031-document-approval-workflow-design.md
  plan: docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md
  branch: feature/gap031-document-approval-workflow
  pr: https://github.com/kha997/zenamanagephp/pull/238
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: docs/owner-decisions/GAP-031/03-release.md
superseded_by: null
timestamps:
  created_at: "2026-08-04T11:00:00+07:00"
  updated_at: "2026-08-04T11:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "30/30 kiểm tra bắt buộc đã đạt, gồm kiểm tra hai người cùng thao tác một lúc trên MySQL thật."
---

## Owner Summary
Nút Duyệt/Từ chối tài liệu trên web nay hoạt động thật và không còn đường lách quyền duyệt. Toàn bộ kiểm tra bắt buộc đã đạt, sẵn sàng phát hành, chờ quyết định phát hành.

## Gói quyết định phát hành — GAP-031: Duyệt hồ sơ tài liệu

**1. Vấn đề đã xảy ra là gì?**
Trên trang web, nút "Duyệt" và "Từ chối" một tài liệu thực ra không hoạt động đúng — chức năng này chưa từng được nối vào bất kỳ đường dẫn nào mà người dùng thật có thể bấm tới, và nếu vô tình chạm được, nó sẽ ghi vào một trạng thái không tồn tại ở bất kỳ nơi nào khác trong hệ thống. Cùng lúc đó, đội kỹ thuật phát hiện một lỗ hổng nghiêm trọng hơn: một người chỉ có quyền "sửa tài liệu" vẫn có thể tự ghi trạng thái "đã duyệt" hoặc "bị từ chối" mà không cần ai thực sự bấm nút duyệt.

**2. Người dùng nào bị ảnh hưởng?**
Bất kỳ ai có quyền duyệt tài liệu trong dự án (thường là quản lý dự án), và bất kỳ ai nộp tài liệu chờ duyệt.

**3. Bây giờ người dùng có thể làm gì?**
Trên giao diện web, người có quyền duyệt tài liệu có thể mở danh sách "Chờ duyệt", bấm Duyệt hoặc Từ chối trực tiếp, và hệ thống ghi lại rõ ràng ai đã quyết định, quyết định gì, khi nào. Vòng đời tài liệu duy nhất là: Nháp → Đã nộp → (Đã duyệt hoặc Bị từ chối).

**4. Rủi ro phân quyền nào đã được đóng lại?**
Trước đây, người chỉ có quyền "sửa" có thể tự đặt trạng thái tài liệu thành "đã duyệt"/"bị từ chối" mà không qua bước duyệt thật. Việc này đã được chặn hoàn toàn — chỉ có đúng một cách hợp lệ để chuyển trạng thái, và cách đó luôn qua kiểm tra quyền "duyệt tài liệu."

**5. Đã kiểm thử những gì?**
Toàn bộ 30 kiểm tra tự động bắt buộc đều đạt, gồm kiểm tra riêng cho tình huống hai người cùng bấm Duyệt một tài liệu cùng lúc. Đã kiểm tra dữ liệu tài liệu của một khách hàng không thể bị khách hàng khác nhìn thấy hoặc thao tác.

**6. Điều gì KHÔNG nằm trong phạm vi lần này?**
Không có thay đổi cấu trúc dữ liệu. Không khôi phục lại trạng thái cũ đã bị xoá.

**7. Vì sao GAP-032 và GAP-033 vẫn để riêng?**
GAP-032 (ý nghĩa các trạng thái tài liệu cũ) và GAP-033 (người duyệt được chỉ định riêng cho từng hồ sơ) là các quyết định nghiệp vụ riêng, không phải một phần của việc vá lỗ hổng bảo mật này.

**8. Rủi ro còn lại là gì?**
Không có rủi ro mất/lộ dữ liệu. Rủi ro còn lại thuần tuý là phạm vi sản phẩm (GAP-032/GAP-033), không xấu đi so với trước.

**9. Có thể hoàn tác không?**
Có — không đổi cấu trúc dữ liệu, có thể quay lại phiên bản trước an toàn.

**10. Đề xuất của đội kỹ thuật:** Phát hành (Approve).

**Quyết định của chủ doanh nghiệp:** ☐ Phát hành  ☐ Yêu cầu chỉnh sửa nghiệp vụ  ☐ Hoãn phát hành

## What the owner is NOT being asked to decide
Không được yêu cầu mở PR #238, đọc log CI, xem mã nguồn, hay đọc bình luận review — mọi kết luận trên đã được đội kỹ thuật xác minh; owner chỉ quyết định có phát hành hay không.
```

- [ ] **Step 5: Write `docs/owner-governance/examples/GAP-031-owner-release-packet.md`**

This is the illustrative, standalone copy referenced by `docs/owner-governance/` (as opposed to the live packet under `docs/owner-decisions/`) — content identical to Step 4's body (without frontmatter, since this is documentation, not a live packet), prefixed with a one-paragraph explanatory header:

```markdown
# GAP-031 — Sample Owner Release Packet (Gate 3, awaiting_owner state)

This is a worked example of a Gate 3 packet body, referenced by `OWNER_OPERATING_MODEL.md` and `docs/superpowers/specs/2026-08-04-non-technical-owner-control-layer-design.md` §9. The live, frontmatter-bearing version of this exact content is `docs/owner-decisions/GAP-031/03-release-v2.md` — read that file for the machine-readable fields; this file exists purely as a plain-language reference an agent can point a curious owner to without also showing them YAML.

---

<!-- Body identical to docs/owner-decisions/GAP-031/03-release-v2.md, from "## Gói quyết định phát hành" through the end. -->
```

- [ ] **Step 6: Write the failing worked-example test**

Create `tests/Unit/OwnerGovernance/Gap031WorkedExampleTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class Gap031WorkedExampleTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_all_four_gap031_packet_files_exist(): void
    {
        $root = $this->repoRoot();
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/01-request.md');
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/02-design.md');
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/03-release.md');
        $this->assertFileExists($root . '/docs/owner-decisions/GAP-031/03-release-v2.md');
        $this->assertFileExists($root . '/docs/owner-governance/examples/GAP-031-owner-release-packet.md');
    }

    public function test_release_v2_supersedes_release_v1_without_contradiction(): void
    {
        $root = $this->repoRoot();

        $v1 = Yaml::parse($this->frontmatterOf($root . '/docs/owner-decisions/GAP-031/03-release.md'));
        $v2 = Yaml::parse($this->frontmatterOf($root . '/docs/owner-decisions/GAP-031/03-release-v2.md'));

        $this->assertSame('blocked_technical', $v1['gate_status']);
        $this->assertSame('docs/owner-decisions/GAP-031/03-release-v2.md', $v1['superseded_by']);

        $this->assertSame('awaiting_owner', $v2['gate_status']);
        $this->assertSame('docs/owner-decisions/GAP-031/03-release.md', $v2['supersedes']);

        // No two non-superseded Gate 3 packets for the same work_id may both be "current".
        $this->assertNotNull($v1['superseded_by'], 'v1 must point forward — it is not the current record.');
        $this->assertNull($v2['superseded_by'], 'v2 is the current record — nothing supersedes it yet.');
    }

    public function test_awaiting_owner_packet_does_not_ask_owner_to_read_pr_or_ci(): void
    {
        $root = $this->repoRoot();
        $content = file_get_contents($root . '/docs/owner-decisions/GAP-031/03-release-v2.md');

        foreach (['gh pr', 'CI log', 'source code', 'PR #238', 'stack trace'] as $forbidden) {
            $this->assertStringNotContainsStringIgnoringCase(
                $forbidden,
                $this->stripFrontmatterAndReferences($content),
                "Owner-facing body must not ask the owner to inspect '{$forbidden}'."
            );
        }
    }

    private function frontmatterOf(string $path): string
    {
        $content = file_get_contents($path);
        preg_match('/^---\n(.*?)\n---\n/s', $content, $matches);

        return $matches[1] ?? '';
    }

    private function stripFrontmatterAndReferences(string $content): string
    {
        // Remove the YAML frontmatter block (which legitimately contains a PR URL
        // under `references.pr` — that is an EEL link, not owner-facing prose).
        return (string) preg_replace('/^---\n.*?\n---\n/s', '', $content);
    }
}
```

- [ ] **Step 7: Run test to verify it fails (RED)**

Run: `php artisan test --filter Gap031WorkedExampleTest`
Expected (before Steps 1–5): FAIL, file-not-found.

- [ ] **Step 8: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter Gap031WorkedExampleTest`
Expected: `OK (3 tests, ...)`.

- [ ] **Step 9: Commit**

```bash
git add docs/owner-decisions/GAP-031/ docs/owner-governance/examples/GAP-031-owner-release-packet.md tests/Unit/OwnerGovernance/Gap031WorkedExampleTest.php
git commit -m "docs(owner-governance): add complete GAP-031 worked-example packet lifecycle (task 4)"
```

**Reviewer acceptance criteria:** all four GAP-031 packet files exist and are schema-valid; `03-release.md`/`03-release-v2.md` form a correct supersession pair (no two "current" Gate 3 records for the same work ID); the `awaiting_owner` packet body never asks the owner to inspect PR #238, CI, or source code, per the design's own requirement (§9 of the design spec).

---

### Task 5: `owner-governance-lint`

**Files:**
- Create: `scripts/ssot/owner_governance_lint.php`
- Create: `tests/Unit/OwnerGovernance/fixtures/invalid-missing-frontmatter.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/invalid-bad-enum.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/invalid-status-decision-contradiction.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/invalid-blocked-requests-decision.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/invalid-todo-placeholder.md`
- Create: `tests/Unit/OwnerGovernance/fixtures/invalid-missing-provenance.md`
- Create: `tests/Unit/OwnerGovernance/OwnerGovernanceLintFixtureTest.php`

**Interfaces:**
- Consumes: `docs/owner-governance/packet-schema.yml` (Task 1), the 4 valid + 6 invalid fixtures (all under `tests/Unit/OwnerGovernance/fixtures/`).
- Produces: `bin/owner-governance-lint` behavior contract — callable as `php scripts/ssot/owner_governance_lint.php <path-or-directory> [...]`, exit code `0` (all packets structurally valid) or `1` (at least one violation), used directly by Task 9's CI workflow and indirectly (as a library function `Owner_Governance_Lint\validate_packet(string $content): array`) by this task's own PHPUnit tests.

- [ ] **Step 1: Write the 6 invalid fixtures**

`tests/Unit/OwnerGovernance/fixtures/invalid-missing-frontmatter.md`:

```markdown
# No frontmatter block at all

This file has a Markdown body but no leading `---` YAML block, which every packet must have.
```

`tests/Unit/OwnerGovernance/fixtures/invalid-bad-enum.md`:

```markdown
---
work_id: GAP-031
gate: 3
gate_status: pending_review
technical_readiness:
  value: mostly_ready
  generated_by: engineering_evidence
owner_decision:
  value: none
  authority: human_owner
decision_requested: null
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T00:00:00+07:00"}
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "n/a"
---

`gate_status: pending_review` and `technical_readiness.value: mostly_ready` are not in packet-schema.yml — both must be rejected.
```

`tests/Unit/OwnerGovernance/fixtures/invalid-status-decision-contradiction.md`:

```markdown
---
work_id: GAP-031
gate: 3
gate_status: approved
technical_readiness: {value: ready, generated_by: engineering_evidence}
owner_decision: {value: none, authority: human_owner}
decision_requested: null
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T00:00:00+07:00"}
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "n/a"
---

`gate_status: approved` requires `owner_decision.value: approved` per packet-schema.yml's `gate_status_requires_owner_decision` map — here it is `none`, a contradiction.
```

`tests/Unit/OwnerGovernance/fixtures/invalid-blocked-requests-decision.md`:

```markdown
---
work_id: GAP-031
gate: 3
gate_status: blocked_technical
technical_readiness: {value: blocked, generated_by: engineering_evidence}
owner_decision: {value: none, authority: human_owner}
decision_requested: "approve_or_correction_or_defer"
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T00:00:00+07:00"}
generated_by: agent
residual_risk_rating: medium
mandatory_technical_gate_summary: "n/a"
---

A `blocked_technical` packet must never set `decision_requested` — this violates "only awaiting-owner packets request a decision."
```

`tests/Unit/OwnerGovernance/fixtures/invalid-todo-placeholder.md`:

```markdown
---
work_id: GAP-031
gate: 1
gate_status: preparing
owner_decision: {value: none, authority: human_owner}
decision_requested: null
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T00:00:00+07:00"}
generated_by: agent
---

## Vấn đề vận hành
TBD — need to fill this in later.
```

`tests/Unit/OwnerGovernance/fixtures/invalid-missing-provenance.md`:

```markdown
---
work_id: GAP-031
gate: 2
gate_status: approved
owner_decision: {value: approved, authority: human_owner}
decision_requested: null
references: {spec: null, plan: null, branch: null, pr: null, release: null}
decision_provenance: {trust_level: claimed_repo_record, recorded_by: null, recorded_at: null, owner_response_reference: null, reconciliation_required: false}
supersedes: null
superseded_by: null
timestamps: {created_at: "2026-08-04T00:00:00+07:00", updated_at: "2026-08-04T00:00:00+07:00"}
generated_by: agent
---

`owner_decision.value: approved` but `decision_provenance.recorded_by`/`recorded_at` are both null — an "approved" record must carry honest, non-null provenance attribution (who/when it was recorded), even at `trust_level: claimed_repo_record`.
```

- [ ] **Step 2: Run the fixture test to verify it fails (RED)**

The test file (Step 4 below) does not exist yet, so run a placeholder discovery command first to confirm no matching test currently exists:

Run: `php artisan test --filter OwnerGovernanceLintFixtureTest`
Expected: `No tests executed!` (class not found) — confirms this is genuinely new coverage before Step 3/4 are written.

- [ ] **Step 3: Write `scripts/ssot/owner_governance_lint.php`**

```php
<?php declare(strict_types=1);

/**
 * owner-governance-lint — structural validator for docs/owner-decisions/**
 * packet files against docs/owner-governance/packet-schema.yml.
 *
 * Usage: php scripts/ssot/owner_governance_lint.php [path-or-directory ...]
 * With no arguments, scans docs/owner-decisions/ recursively.
 * Exit 0 = no violations. Exit 1 = at least one violation (message printed,
 * plus a ::error GitHub Actions annotation per violation when GITHUB_ACTIONS
 * is set — mirrors scripts/ci/docs-lint.sh's existing annotation contract).
 *
 * This script validates STRUCTURE and ENUM MEMBERSHIP only. It never
 * evaluates whether a decision is authentic — see decision_provenance.trust_level,
 * which this script treats as a required, schema-valid field, not a fact it verifies.
 */

require __DIR__ . '/../../vendor/autoload.php';

use Symfony\Component\Yaml\Yaml;

final class OwnerGovernanceLintViolation
{
    public function __construct(
        public readonly string $file,
        public readonly string $rule,
        public readonly string $message,
    ) {
    }
}

/**
 * @return OwnerGovernanceLintViolation[]
 */
function owner_governance_validate_packet(string $filePath, string $content, array $schema): array
{
    $violations = [];

    if (!preg_match('/^---\n(.*?)\n---\n(.*)$/s', $content, $matches)) {
        return [new OwnerGovernanceLintViolation($filePath, 'frontmatter', 'Missing or malformed --- frontmatter block.')];
    }

    try {
        $fm = Yaml::parse($matches[1]);
    } catch (\Throwable $e) {
        return [new OwnerGovernanceLintViolation($filePath, 'frontmatter', 'Frontmatter is not valid YAML: ' . $e->getMessage())];
    }

    if (!is_array($fm)) {
        return [new OwnerGovernanceLintViolation($filePath, 'frontmatter', 'Frontmatter did not parse to a mapping.')];
    }

    $body = $matches[2];

    // Required top-level fields present.
    foreach ($schema['required_top_level_fields'] as $field) {
        if (!array_key_exists($field, $fm)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'required-field', "Missing required field '{$field}'.");
        }
    }
    if ($violations !== []) {
        return $violations; // Can't check anything downstream reliably without the basics.
    }

    // work_id pattern.
    if (!preg_match('/' . $schema['work_id_pattern'] . '/', (string) $fm['work_id'])) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'work-id-pattern', "work_id '{$fm['work_id']}' does not match the canonical pattern.");
    }

    // gate must be 1, 2, or 3 and must have a schema entry.
    $gate = $fm['gate'] ?? null;
    if (!in_array($gate, [1, 2, 3], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate-value', "gate must be 1, 2, or 3, got '" . var_export($gate, true) . "'.");

        return $violations;
    }
    $gateSchema = $schema['gates'][$gate];

    // gate_status enum, per-gate.
    $gateStatus = $fm['gate_status'] ?? null;
    if (!in_array($gateStatus, $gateSchema['gate_status_values'], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate-status-enum', "gate_status '{$gateStatus}' is not valid for gate {$gate}.");
    }

    // owner_decision.value enum, per-gate.
    $ownerDecisionValue = $fm['owner_decision']['value'] ?? null;
    if (!in_array($ownerDecisionValue, $gateSchema['owner_decision_values'], true)) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'owner-decision-enum', "owner_decision.value '{$ownerDecisionValue}' is not valid for gate {$gate}.");
    }

    // gate_status <-> owner_decision compatibility (the contradiction check).
    if ($gateStatus !== null && array_key_exists($gateStatus, $schema['gate_status_requires_owner_decision'])) {
        $allowed = $schema['gate_status_requires_owner_decision'][$gateStatus];
        if ($allowed !== [] && !in_array($ownerDecisionValue, $allowed, true)) {
            $violations[] = new OwnerGovernanceLintViolation(
                $filePath,
                'status-decision-contradiction',
                "gate_status '{$gateStatus}' requires owner_decision.value to be one of [" . implode(', ', $allowed) . "], got '{$ownerDecisionValue}'."
            );
        }
    }

    // decision_requested rule: non-null iff gate_status is awaiting_owner.
    $decisionRequested = $fm['decision_requested'] ?? null;
    if ($gateStatus === 'awaiting_owner' && $decisionRequested === null) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'decision-requested-missing', "gate_status is 'awaiting_owner' but decision_requested is null.");
    }
    if ($gateStatus !== 'awaiting_owner' && $decisionRequested !== null) {
        $violations[] = new OwnerGovernanceLintViolation($filePath, 'decision-requested-leaked', "Only 'awaiting_owner' packets may set decision_requested; gate_status is '{$gateStatus}'.");
    }

    // Honest provenance: any non-'none' owner_decision must carry real attribution.
    if ($ownerDecisionValue !== 'none') {
        $provenance = $fm['decision_provenance'] ?? [];
        foreach (['trust_level', 'recorded_by', 'recorded_at'] as $requiredProvenanceField) {
            if (empty($provenance[$requiredProvenanceField])) {
                $violations[] = new OwnerGovernanceLintViolation(
                    $filePath,
                    'dishonest-provenance',
                    "owner_decision.value is '{$ownerDecisionValue}' but decision_provenance.{$requiredProvenanceField} is empty — a recorded decision must carry attribution."
                );
            }
        }
        $trustLevel = $provenance['trust_level'] ?? null;
        if ($trustLevel !== null && !in_array($trustLevel, $schema['decision_provenance_trust_level_values'], true)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'trust-level-enum', "decision_provenance.trust_level '{$trustLevel}' is not a recognized value.");
        }
    }

    // Gate-3-only fields: required on gate 3, forbidden on gate 1/2.
    foreach ($schema['gate_3_only_fields'] as $gate3Field) {
        $present = array_key_exists($gate3Field, $fm);
        if ($gateSchema['requires_technical_readiness'] && !$present) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate3-field-missing', "Gate 3 packet is missing required field '{$gate3Field}'.");
        }
        if (!$gateSchema['requires_technical_readiness'] && $present) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'gate3-field-leaked', "Field '{$gate3Field}' is Gate-3-only but present on a gate {$gate} packet.");
        }
    }
    if ($gateSchema['requires_technical_readiness']) {
        $trValue = $fm['technical_readiness']['value'] ?? null;
        if (!in_array($trValue, $schema['technical_readiness_values'], true)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'technical-readiness-enum', "technical_readiness.value '{$trValue}' is not valid.");
        }
        // technical_readiness must never be owner-authored: this script does not
        // (and cannot, from a single file) prove *who* wrote a value — it only
        // enforces the generated_by tag is present and correct as a documented
        // convention, matching design §3.7's "generated_by: engineering_evidence".
        if (($fm['technical_readiness']['generated_by'] ?? null) !== 'engineering_evidence') {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'technical-readiness-provenance', "technical_readiness.generated_by must be 'engineering_evidence'.");
        }
    }

    // Supersession links must resolve to files that actually exist in the repo.
    $repoRoot = dirname(__DIR__, 2);
    foreach (['supersedes', 'superseded_by'] as $supersessionField) {
        $target = $fm[$supersessionField] ?? null;
        if ($target !== null && !is_file($repoRoot . '/' . $target)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'dangling-supersession-link', "{$supersessionField} points to '{$target}', which does not exist.");
        }
    }

    // references.spec / references.plan, if non-null, must resolve.
    foreach (['spec', 'plan'] as $refField) {
        $target = $fm['references'][$refField] ?? null;
        if ($target !== null && !is_file($repoRoot . '/' . $target)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'dangling-eel-link', "references.{$refField} points to '{$target}', which does not exist.");
        }
    }

    // No placeholder tokens anywhere in the body.
    foreach ($schema['placeholder_tokens'] as $token) {
        if (str_contains($body, $token)) {
            $violations[] = new OwnerGovernanceLintViolation($filePath, 'placeholder-token', "Body contains forbidden placeholder token '{$token}'.");
        }
    }

    return $violations;
}

// --- CLI entrypoint ---
if (($argv[0] ?? '') === basename(__FILE__) || PHP_SAPI === 'cli') {
    $repoRoot = dirname(__DIR__, 2);
    $schema = Yaml::parseFile($repoRoot . '/docs/owner-governance/packet-schema.yml');

    $targets = array_slice($argv, 1);
    if ($targets === []) {
        $targets = [$repoRoot . '/docs/owner-decisions'];
    }

    $files = [];
    foreach ($targets as $target) {
        if (is_dir($target)) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $fileInfo) {
                if ($fileInfo->getExtension() === 'md') {
                    $files[] = $fileInfo->getPathname();
                }
            }
        } elseif (is_file($target)) {
            $files[] = $target;
        }
    }

    $allViolations = [];
    foreach ($files as $file) {
        $content = file_get_contents($file);
        $relative = str_starts_with($file, $repoRoot) ? ltrim(substr($file, strlen($repoRoot)), '/') : $file;
        foreach (owner_governance_validate_packet($relative, $content, $schema) as $violation) {
            $allViolations[] = $violation;
        }
    }

    $isGithubActions = (getenv('GITHUB_ACTIONS') === 'true');
    foreach ($allViolations as $violation) {
        if ($isGithubActions) {
            echo "::error file={$violation->file}::owner-governance-lint [{$violation->rule}]: {$violation->message}\n";
        } else {
            echo "{$violation->file} [{$violation->rule}]: {$violation->message}\n";
        }
    }

    if ($allViolations !== []) {
        printf("\n❌ owner-governance-lint FAIL (%d violation(s) across %d file(s) scanned)\n", count($allViolations), count($files));
        exit(1);
    }

    printf("✅ owner-governance-lint PASS (%d file(s) scanned, 0 violations)\n", count($files));
    exit(0);
}
```

- [ ] **Step 4: Write `tests/Unit/OwnerGovernance/OwnerGovernanceLintFixtureTest.php`**

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

require_once dirname(__DIR__, 3) . '/scripts/ssot/owner_governance_lint.php';

class OwnerGovernanceLintFixtureTest extends TestCase
{
    private array $schema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = Yaml::parseFile(dirname(__DIR__, 3) . '/docs/owner-governance/packet-schema.yml');
    }

    private function contentOf(string $fixture): string
    {
        return file_get_contents(__DIR__ . '/fixtures/' . $fixture);
    }

    /** @dataProvider validFixtures */
    public function test_valid_fixtures_produce_zero_violations(string $fixture): void
    {
        $violations = \owner_governance_validate_packet($fixture, $this->contentOf($fixture), $this->schema);
        $this->assertCount(0, $violations, "{$fixture} should be valid but got: " . json_encode(array_map(fn ($v) => $v->message, $violations)));
    }

    public static function validFixtures(): array
    {
        return [
            ['valid-gate-1.md'],
            ['valid-gate-2.md'],
            ['valid-gate-3-blocked.md'],
            ['valid-gate-3-awaiting.md'],
        ];
    }

    /** @dataProvider invalidFixtures */
    public function test_invalid_fixtures_produce_at_least_one_violation_of_the_expected_rule(string $fixture, string $expectedRule): void
    {
        $violations = \owner_governance_validate_packet($fixture, $this->contentOf($fixture), $this->schema);
        $rules = array_map(fn ($v) => $v->rule, $violations);

        $this->assertNotEmpty($violations, "{$fixture} should produce at least one violation.");
        $this->assertContains($expectedRule, $rules, "{$fixture} should trigger rule '{$expectedRule}', got: " . implode(', ', $rules));
    }

    public static function invalidFixtures(): array
    {
        return [
            ['invalid-missing-frontmatter.md', 'frontmatter'],
            ['invalid-bad-enum.md', 'gate-status-enum'],
            ['invalid-status-decision-contradiction.md', 'status-decision-contradiction'],
            ['invalid-blocked-requests-decision.md', 'decision-requested-leaked'],
            ['invalid-todo-placeholder.md', 'placeholder-token'],
            ['invalid-missing-provenance.md', 'dishonest-provenance'],
        ];
    }
}
```

- [ ] **Step 5: Run test to verify it fails (RED)**

Run: `php artisan test --filter OwnerGovernanceLintFixtureTest`
Expected (before `owner_governance_lint.php` exists): FAIL — `require_once` on a missing file, fatal error.

- [ ] **Step 6: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter OwnerGovernanceLintFixtureTest`
Expected: `OK (10 tests, ...)` (4 valid + 6 invalid).

- [ ] **Step 7: Verify the CLI entrypoint directly (not just the library function)**

Run: `php scripts/ssot/owner_governance_lint.php tests/Unit/OwnerGovernance/fixtures/valid-gate-3-awaiting.md`
Expected: `✅ owner-governance-lint PASS (1 file(s) scanned, 0 violations)`, exit code `0`.

Run: `php scripts/ssot/owner_governance_lint.php tests/Unit/OwnerGovernance/fixtures/invalid-bad-enum.md`
Expected: two `[gate-status-enum]`/`[technical-readiness-enum]`-style lines printed, `❌ owner-governance-lint FAIL`, exit code `1` (verify with `echo $?`).

Run: `php scripts/ssot/owner_governance_lint.php`
Expected (scanning real `docs/owner-decisions/GAP-031/`, created in Task 4): `✅ owner-governance-lint PASS (4 file(s) scanned, 0 violations)`.

- [ ] **Step 8: Commit**

```bash
git add scripts/ssot/owner_governance_lint.php tests/Unit/OwnerGovernance/fixtures/invalid-*.md tests/Unit/OwnerGovernance/OwnerGovernanceLintFixtureTest.php
git commit -m "feat(owner-governance): add owner_governance_lint.php with fixture-based RED/GREEN coverage (task 5)"
```

**Reviewer acceptance criteria:** all 10 fixture cases (4 valid, 6 invalid) pass; the CLI entrypoint run against the real `docs/owner-decisions/GAP-031/` directory (Task 4's output) reports 0 violations; running the CLI against a known-bad fixture exits `1`; the script never sets or reads any field implying it verified a human's identity — only structure and enum membership.

---

### Task 6: Constitution and agent behavior integration

**Files:**
- Modify: `PROJECT_CONSTITUTION.md` (insert new §3a between existing §3 and §4; add one row to Appendix B)
- Modify: `docs/agent-ssot-rules.md` (append Rule 9)
- Test: `tests/Unit/OwnerGovernance/ConstitutionAmendmentTest.php`

**Interfaces:**
- Consumes: `docs/owner-governance/OWNER_OPERATING_MODEL.md` path (Task 2), `docs/owner-governance/OWNER_DECISION_RULES.md` path (Task 2, for the four-question test).
- Produces: the exact §3a heading text (`"## 3a. Owner Gates"`) and Rule 9 heading text (`"9) "`), which Task 7's stop-report amendment references by name.

- [ ] **Step 1: Insert §3a into `PROJECT_CONSTITUTION.md`**

Locate the existing boundary (verified fact #1): section 3 ends right before `## 4. Operational Gap Detection`. Insert immediately before that line:

```markdown
## 3a. Owner Gates

Mọi thay đổi tiến tới lập kế hoạch triển khai hoặc code phải đi qua đúng ba cổng quyết định của chủ doanh nghiệp (owner), theo `docs/owner-governance/OWNER_OPERATING_MODEL.md`:

* **Gate 1 — Business Request Approval**: owner xác nhận vấn đề vận hành có thật, quan trọng, đúng phạm vi. Trước khi Gate 1 được duyệt, agent chỉ được nghiên cứu (đọc code, không viết plan, không viết code sản phẩm).
* **Gate 2 — Business Design Approval**: owner duyệt workflow trước/sau, vai trò, quy tắc. Trước khi Gate 2 được duyệt, không được tạo `docs/superpowers/plans/*` cho work ID đó, không viết code sản phẩm.
* **Gate 3 — Release Approval**: owner quyết định một thay đổi đã được kiểm chứng có được phát hành hay không. **Gate 3 không chặn việc triển khai, kiểm thử, review kỹ thuật, hay chuẩn bị demo** — các việc đó được phép ngay khi Gate 2 duyệt xong, không cần chờ Gate 3. Gate 3 chỉ chặn: merge (khi owner approval là điều kiện bắt buộc), deploy, thay đổi dữ liệu production, phát hành cho người dùng thật, và việc tuyên bố thay đổi "đã được owner duyệt."

`technical_readiness` (bằng chứng kỹ thuật) và `owner_decision` (quyết định của owner) là hai trường độc lập — không agent nào được suy ra quyết định owner từ trạng thái kỹ thuật sẵn sàng, và owner không thể override một cổng kỹ thuật đỏ (toàn vẹn dữ liệu, tenant isolation, bảo mật, phân quyền, CI bắt buộc).

SSOT cho cơ chế cổng: `docs/owner-governance/OWNER_OPERATING_MODEL.md`. Điều khoản §8 (Evidence Before Claims) và Phụ lục A của văn bản này **vẫn có hiệu lực đầy đủ, không đổi** — các cổng owner là một lớp quyết định bổ sung, không thay thế kỷ luật bằng chứng kỹ thuật hiện có.

```

Then add one row to Appendix B's Governance Map table (insert as a new row, keeping the table's existing `| Chủ đề | Nguồn SSOT |` format):

```markdown
| Cổng quyết định owner và gói quyết định (packet) | `docs/owner-governance/OWNER_OPERATING_MODEL.md` |
```

- [ ] **Step 2: Append Rule 9 to `docs/agent-ssot-rules.md`**

Append after the existing Rule 8:

```markdown

## 9) Owner-facing content is a distinct artifact, not a technical report
Khi tạo Owner Decision Packet (`docs/owner-decisions/<WORK-ID>/0X-*.md`), không trích `route:list`, số dòng migration, hay code controller làm **nội dung** packet — trích chúng trong Engineering Evidence Layer đã liên kết (`references.spec`/`references.plan`) thay vào đó. Packet phải đọc được bởi người không hiểu bất kỳ quy tắc nào từ 1 đến 8 ở trên.

Mọi báo cáo agent (stop report) phải có đủ 5 phần: Owner Summary, Technical Evidence Appendix, Decision Needed, Residual Risk, và phần nêu rõ owner KHÔNG được yêu cầu quyết định điều gì. Trước khi đưa một phát hiện lên owner, áp dụng bài kiểm tra 4 câu hỏi ở `docs/owner-governance/OWNER_DECISION_RULES.md` — nếu cả 4 câu đều "không," đây là chi tiết triển khai, giải quyết kỹ thuật, không đưa lên owner.
```

- [ ] **Step 3: Write the failing amendment-location test**

Create `tests/Unit/OwnerGovernance/ConstitutionAmendmentTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class ConstitutionAmendmentTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_constitution_has_owner_gates_section_between_3_and_4(): void
    {
        $content = file_get_contents($this->repoRoot() . '/PROJECT_CONSTITUTION.md');

        $pos3a = strpos($content, '## 3a. Owner Gates');
        $pos4 = strpos($content, '## 4. Operational Gap Detection');

        $this->assertNotFalse($pos3a, '§3a must exist.');
        $this->assertNotFalse($pos4, '§4 must still exist (unmodified heading).');
        $this->assertLessThan($pos4, $pos3a, '§3a must come before §4.');
    }

    public function test_constitution_states_gate_3_does_not_block_preparation(): void
    {
        $content = file_get_contents($this->repoRoot() . '/PROJECT_CONSTITUTION.md');
        $this->assertStringContainsString('không chặn việc triển khai', $content);
    }

    public function test_constitution_governance_map_references_operating_model(): void
    {
        $content = file_get_contents($this->repoRoot() . '/PROJECT_CONSTITUTION.md');
        $this->assertStringContainsString('docs/owner-governance/OWNER_OPERATING_MODEL.md', $content);
    }

    public function test_agent_ssot_rules_has_rule_9(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/agent-ssot-rules.md');
        $this->assertStringContainsString('## 9) Owner-facing content is a distinct artifact', $content);
    }

    public function test_agent_ssot_rules_9_names_all_five_stop_report_sections(): void
    {
        $content = file_get_contents($this->repoRoot() . '/docs/agent-ssot-rules.md');
        foreach (['Owner Summary', 'Technical Evidence Appendix', 'Decision Needed', 'Residual Risk'] as $section) {
            $this->assertStringContainsString($section, $content);
        }
    }
}
```

- [ ] **Step 4: Run test to verify it fails (RED)**

Run: `php artisan test --filter ConstitutionAmendmentTest`
Expected (before Steps 1–2): FAIL on the `assertNotFalse($pos3a, ...)` assertion.

- [ ] **Step 5: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter ConstitutionAmendmentTest`
Expected: `OK (5 tests, ...)`.

- [ ] **Step 6: Commit**

```bash
git add PROJECT_CONSTITUTION.md docs/agent-ssot-rules.md tests/Unit/OwnerGovernance/ConstitutionAmendmentTest.php
git commit -m "docs(constitution): add owner gates section 3a and agent-ssot-rules rule 9 (task 6)"
```

**Reviewer acceptance criteria:** §3a sits between existing §3 and §4 without renumbering any other section; §3a explicitly states Gate 3 does not block preparation (matching the design's corrected lifecycle); Rule 9 names all 5 required stop-report sections; no existing Constitution/SSOT-rules content is deleted or renumbered.

---

### Task 7: PR and integration-review format

**Files:**
- Modify: `.github/PULL_REQUEST_TEMPLATE.md` (add new top section)
- Test: `tests/Unit/OwnerGovernance/PrTemplateTest.php`

**Interfaces:**
- Consumes: field names from `docs/owner-governance/packet-schema.yml` (Task 1) — `gate_status`, `technical_readiness`, `owner_decision` — used verbatim as PR-template labels so a reviewer can grep the same vocabulary across packet and PR.
- Produces: nothing consumed by a later task in this plan (last governance-facing document edited); Task 10's runbook demonstrates filling this section in for a real work item.

- [ ] **Step 1: Modify `.github/PULL_REQUEST_TEMPLATE.md`**

Insert the following as the new first section, **above** the existing `## SSOT Story Reference` line (which stays exactly as-is, first line of the file today):

```markdown
## Owner Summary (read this first — no code required)
- Work ID: <GAP-NNN / OWN-YYYY-NNN>
- Owner gate status: Gate 1 [<gate_status>] · Gate 2 [<gate_status>] · Gate 3 [<gate_status>]
- Technical readiness (Gate 3 only): <not_checked / blocked / ready>
- Owner decision (Gate 3 only): <none / approved / correction_requested / deferred>
- Owner packets: `docs/owner-decisions/<WORK-ID>/`
- What this changes for users (one paragraph, plain Vietnamese, no jargon):
- Business acceptance evidence: <link to Gate 2 packet's acceptance-scenario checklist, or "n/a — no user-facing change">
- Exclusions / deferred gaps: <e.g. "GAP-032 and GAP-033 remain separate">
- Residual risk (plain language): <e.g. "none" / one sentence>
- Decision needed from the owner right now: <the exact decision_requested value, or "none — informational">

```

Everything from `## SSOT Story Reference` onward is unchanged, byte-for-byte, from the current template.

- [ ] **Step 2: Write the failing structural test**

Create `tests/Unit/OwnerGovernance/PrTemplateTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class PrTemplateTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_owner_summary_is_the_first_section(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/PULL_REQUEST_TEMPLATE.md');
        $lines = explode("\n", trim($content));

        $this->assertSame('## Owner Summary (read this first — no code required)', $lines[0]);
    }

    public function test_ssot_story_reference_section_still_present_and_unmodified_below(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/PULL_REQUEST_TEMPLATE.md');
        $this->assertStringContainsString('## SSOT Story Reference', $content);
        $this->assertStringContainsString('## Invariants Checklist (MUST)', $content);
        $this->assertStringContainsString('## SSOT Backlog Update (REQUIRED)', $content);
    }

    public function test_owner_summary_names_gate_status_and_technical_readiness(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/PULL_REQUEST_TEMPLATE.md');
        $this->assertStringContainsString('Owner gate status', $content);
        $this->assertStringContainsString('Technical readiness', $content);
        $this->assertStringContainsString('Owner decision', $content);
    }
}
```

- [ ] **Step 3: Run test to verify it fails (RED)**

Run: `php artisan test --filter PrTemplateTest`
Expected (before Step 1): FAIL — first line of the current template is `## SSOT Story Reference`, not `## Owner Summary...`.

- [ ] **Step 4: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter PrTemplateTest`
Expected: `OK (3 tests, ...)`.

- [ ] **Step 5: Commit**

```bash
git add .github/PULL_REQUEST_TEMPLATE.md tests/Unit/OwnerGovernance/PrTemplateTest.php
git commit -m "docs(pr-template): add owner-readable summary as first section (task 7)"
```

**Reviewer acceptance criteria:** the Owner Summary section is literally the first thing a reviewer sees when opening a new PR; every existing template section (Invariants Checklist, Acceptance Criteria, Evidence/Verification, CI Checks, SSOT Backlog Update, Change Proposal, Notes/Follow-ups) survives unmodified below it; no existing checkbox item was removed or reworded.

---

### Task 8: GitHub identity mitigation

**Files:**
- Create: `.github/CODEOWNERS`
- Modify: branch protection on `main` via `gh api` (not a repo file — a live GitHub configuration change)
- Create: `docs/owner-governance/OWNER_OPERATING_MODEL.md` addendum (append a "GitHub identity mitigation" section — modify, not create new)
- Test: `tests/Unit/OwnerGovernance/CodeownersTest.php`

**Interfaces:**
- Consumes: verified fact #5 (no CODEOWNERS exists), #6 (branch protection has 1 required check, no required reviews), #7 (single collaborator `kha997`), #8 (this session's own `gh` token is `kha997`'s).
- Produces: a real, live change to `main`'s branch protection (Step 3 below) — **this is a repo-wide, hard-to-reverse configuration change, not reversible by `git revert`, and this step must be run only with explicit user confirmation at execution time**, per this session's own operating rules on risky/shared-state actions. Everything else in this task is a normal file commit.

**⚠️ Execution note (not a plan defect — a deliberate flag):** Step 3 changes merge requirements for the entire `main` branch, not just governance paths. The engineer executing this plan must show the exact command to the user and get explicit confirmation before running it, even though the plan itself specifies the exact command.

- [ ] **Step 1: Write `.github/CODEOWNERS`**

```text
# Owner Control Layer governance paths require a review from the verified
# repository owner identity before merge. See Task 8,
# docs/superpowers/plans/2026-08-04-owner-control-layer-repo-governance-foundation.md,
# for what this mitigates and — just as importantly — what it does NOT prove.

/docs/owner-decisions/       @kha997
/docs/owner-governance/      @kha997
/PROJECT_CONSTITUTION.md     @kha997
```

- [ ] **Step 2: Append the identity-mitigation section to `OWNER_OPERATING_MODEL.md`**

Append to the end of `docs/owner-governance/OWNER_OPERATING_MODEL.md` (from Task 2):

```markdown

## GitHub identity mitigation (repository-native phase only)

`.github/CODEOWNERS` requires a review from `@kha997` on any PR touching `docs/owner-decisions/`, `docs/owner-governance/`, or `PROJECT_CONSTITUTION.md`, once branch protection also requires code-owner review (below). This is a **mitigation**, not authentication proof, and this document states the distinction explicitly because it would otherwise be easy to overclaim:

- **What this proves:** a PR touching governance paths could not reach `main` without an approving review recorded under the GitHub account `@kha997`.
- **What this does NOT prove:** that the specific human who owns that account personally read and approved the change with informed intent. It does not defend against a compromised token, a session where an AI agent is operating with that account's own credentials (verified fact #8 — true in this very planning session), or social engineering.
- **This is not upgraded by anything in this plan.** The only mechanism that closes this gap is the future in-app Decision Center's authenticated session (`trust_level: authenticated_decision_center`), out of scope here (see the approved design §6.8/§6.8a/§10.5).

Verified repository facts this mitigation is built on: as of 2026-08-04, `kha997` is the repository's sole collaborator with write access (`gh api repos/kha997/zenamanagephp/collaborators`), and no `.github/CODEOWNERS` file previously existed.
```

- [ ] **Step 3: Update branch protection to require CODEOWNERS review (live GitHub configuration change — confirm with user before running)**

Exact command:

```bash
gh api --method PUT repos/kha997/zenamanagephp/branches/main/protection \
  -F required_status_checks[strict]=true \
  -F 'required_status_checks[contexts][]=test-routes-guardrails' \
  -F required_pull_request_reviews[require_code_owner_reviews]=true \
  -F required_pull_request_reviews[required_approving_review_count]=1 \
  -F enforce_admins=true \
  -F required_linear_history=false \
  -F allow_force_pushes=false \
  -F allow_deletions=false \
  -F block_creations=false \
  -F required_conversation_resolution=false
```

This adds `required_pull_request_reviews` (currently absent, verified fact #6) with `require_code_owner_reviews: true`, while preserving every other currently-configured value exactly (re-stated explicitly in the same call, since this endpoint replaces the whole protection object rather than patching it — confirmed by re-reading the same GitHub API's documented PUT semantics for this endpoint during this planning session; the values above are copied field-for-field from the JSON captured in verified fact #6, with only `required_pull_request_reviews` added).

**Verification command (run immediately after):**

```bash
gh api repos/kha997/zenamanagephp/branches/main/protection --jq '.required_pull_request_reviews.require_code_owner_reviews'
```

Expected output: `true`.

- [ ] **Step 4: Write the failing CODEOWNERS test**

Create `tests/Unit/OwnerGovernance/CodeownersTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;

class CodeownersTest extends TestCase
{
    public function test_codeowners_file_exists_and_covers_governance_paths(): void
    {
        $path = dirname(__DIR__, 3) . '/.github/CODEOWNERS';
        $this->assertFileExists($path);

        $content = file_get_contents($path);
        $this->assertStringContainsString('/docs/owner-decisions/', $content);
        $this->assertStringContainsString('/docs/owner-governance/', $content);
        $this->assertStringContainsString('@kha997', $content);
    }

    public function test_operating_model_states_mitigation_is_not_authentication_proof(): void
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/docs/owner-governance/OWNER_OPERATING_MODEL.md');
        $this->assertStringContainsString('not authentication proof', $content);
        $this->assertStringContainsString('does NOT prove', $content);
    }
}
```

- [ ] **Step 5: Run test to verify it fails (RED)**

Run: `php artisan test --filter CodeownersTest`
Expected (before Step 1–2): FAIL, file-not-found / string-not-found.

- [ ] **Step 6: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter CodeownersTest`
Expected: `OK (2 tests, ...)`.

- [ ] **Step 7: Commit the file changes (Steps 1, 2, 4 — NOT the live API call from Step 3, which has no git artifact to commit)**

```bash
git add .github/CODEOWNERS docs/owner-governance/OWNER_OPERATING_MODEL.md tests/Unit/OwnerGovernance/CodeownersTest.php
git commit -m "chore(owner-governance): add CODEOWNERS for governance paths, document identity-mitigation limits (task 8)"
```

**Reviewer acceptance criteria:** `.github/CODEOWNERS` exists and covers exactly the three governance paths named; `OWNER_OPERATING_MODEL.md` states, in plain unhedged language, what the mitigation does and does not prove; the branch-protection API call (Step 3) was shown to and confirmed by the user before execution, and its verification command's output was captured as evidence, not assumed.

---

### Task 9: CI integration and migration compatibility

**Files:**
- Create: `.github/workflows/owner-governance-lint.yml`
- Create: `scripts/ci/check-gate3-before-ready.sh`
- Create: `docs/owner-governance/enforcement-boundary.yml`
- Create: `docs/owner-governance/legacy-work-ids.txt`
- Create: `scripts/ssot/generate_legacy_work_ids.php`
- Test: `tests/Unit/OwnerGovernance/EnforcementBoundaryTest.php`

**Interfaces:**
- Consumes: `scripts/ssot/owner_governance_lint.php` (Task 5), `OPERATIONAL_GAP_REGISTER.md` (existing, verified fact #3 — source of the legacy work-ID list).
- Produces: a live, required-eventually CI gate (added to branch protection's `required_status_checks.contexts` as a follow-up to Task 8's Step 3, documented but not executed here — see Step 5's note) that Task 10's adoption runbook demonstrates end-to-end.

- [ ] **Step 1: Generate the legacy work-ID exemption list**

Create `scripts/ssot/generate_legacy_work_ids.php`:

```php
<?php declare(strict_types=1);

/**
 * Regenerates docs/owner-governance/legacy-work-ids.txt from
 * OPERATIONAL_GAP_REGISTER.md — every GAP-NNN id present in the register
 * as of the owner-governance effective date is exempt from gate-ordering
 * enforcement (Task 9). This is a ONE-TIME generation script, re-run only
 * if the effective date itself is deliberately moved (it should not be).
 */

$repoRoot = dirname(__DIR__, 2);
$registerPath = $repoRoot . '/OPERATIONAL_GAP_REGISTER.md';
$outPath = $repoRoot . '/docs/owner-governance/legacy-work-ids.txt';

$content = file_get_contents($registerPath);
preg_match_all('/\bGAP-\d{3}\b/', $content, $matches);
$ids = array_values(array_unique($matches[0]));
sort($ids);

$lines = [
    '# Generated by scripts/ssot/generate_legacy_work_ids.php from OPERATIONAL_GAP_REGISTER.md',
    '# Effective date: see docs/owner-governance/enforcement-boundary.yml',
    '# Reason: pre-dates the owner-governance system (2026-08-04) — exempt from',
    '# gate-ordering enforcement, NOT exempt from packet structural validity',
    '# (any packet that DOES exist for one of these IDs must still be schema-valid).',
];
foreach ($ids as $id) {
    $lines[] = $id;
}

file_put_contents($outPath, implode("\n", $lines) . "\n");
echo 'Wrote ' . count($ids) . " legacy work IDs to {$outPath}\n";
```

Run it once: `php scripts/ssot/generate_legacy_work_ids.php`
Expected output: `Wrote 33 legacy work IDs to .../docs/owner-governance/legacy-work-ids.txt` (GAP-001 through GAP-033, per verified fact #3).

- [ ] **Step 2: Write `docs/owner-governance/enforcement-boundary.yml`**

```yaml
# Owner-governance-lint enforcement boundary (Task 9).
# Read by owner-governance-lint.yml's CI job and by owner_governance_lint.php
# when invoked with --enforce-gate-ordering (see Step 3 below).

effective_date: "2026-08-04"

# A work_id is LEGACY (exempt from gate-ordering enforcement — "must have an
# approved Gate 2 packet before a plan exists") if EITHER:
#   (a) it is listed in docs/owner-governance/legacy-work-ids.txt, OR
#   (b) docs/owner-decisions/<work_id>/ does not exist AND the work_id's
#       earliest reference anywhere in git history predates effective_date.
# Condition (b) is what stops new work from "pretending to be legacy": a
# work_id that has never appeared before effective_date, and therefore has
# no git history predating it, can never satisfy (b) — its only path to
# exemption is being explicitly enumerated in legacy-work-ids.txt, which is
# a committed, reviewable, one-time-generated file (Step 1), not something
# a single PR can silently add an entry to and expect to pass review unnoticed.

legacy_exemption_file: "docs/owner-governance/legacy-work-ids.txt"

# Gate-ordering rules that DO NOT apply to legacy-exempt work IDs:
exempt_from:
  - "gate_2_before_plan"     # a docs/superpowers/plans/** file may exist without docs/owner-decisions/<id>/02-design.md
  - "gate_3_before_ready"    # a PR may be marked Ready for review without docs/owner-decisions/<id>/03-release*.md

# Rules that ALWAYS apply, legacy or not (structural validity is never exempt):
never_exempt:
  - "packet_schema_validity"  # any packet file that DOES exist must still pass owner_governance_lint.php
  - "no_placeholder_tokens"
  - "work_id_consistency"
```

- [ ] **Step 3: Extend `owner_governance_lint.php` with a `--enforce-gate-ordering` mode**

Modify `scripts/ssot/owner_governance_lint.php` (from Task 5) — append after the existing CLI entrypoint's violation-reporting block, before the final `exit(0)`:

```php
    // --- Gate-ordering enforcement (Task 9), only runs with --enforce-gate-ordering ---
    if (in_array('--enforce-gate-ordering', $argv, true)) {
        $boundary = Yaml::parseFile($repoRoot . '/docs/owner-governance/enforcement-boundary.yml');
        $legacyIds = array_filter(
            array_map('trim', file($repoRoot . '/' . $boundary['legacy_exemption_file'])),
            fn ($line) => $line !== '' && !str_starts_with($line, '#')
        );

        $planFiles = glob($repoRoot . '/docs/superpowers/plans/*.md');
        $orderingViolations = [];
        foreach ($planFiles as $planFile) {
            $basename = basename($planFile);
            if (!preg_match('/\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b/', $basename, $idMatch)) {
                continue; // No recognizable work_id in the filename — not this lint's concern.
            }
            $workId = $idMatch[1];
            if (in_array($workId, $legacyIds, true)) {
                continue; // Explicitly exempt.
            }

            $gate2Path = $repoRoot . "/docs/owner-decisions/{$workId}/02-design.md";
            if (!is_file($gate2Path)) {
                $orderingViolations[] = new OwnerGovernanceLintViolation($basename, 'gate-2-before-plan', "Plan file references work_id '{$workId}', which has no approved Gate 2 packet ({$gate2Path} missing) and is not in the legacy exemption list.");
                continue;
            }
            $gate2 = Yaml::parse(preg_replace('/^---\n(.*?)\n---\n.*$/s', '$1', file_get_contents($gate2Path)));
            if (($gate2['owner_decision']['value'] ?? null) !== 'approved') {
                $orderingViolations[] = new OwnerGovernanceLintViolation($basename, 'gate-2-not-approved', "Plan file references work_id '{$workId}', whose Gate 2 packet exists but owner_decision.value is not 'approved'.");
            }
        }

        foreach ($orderingViolations as $violation) {
            echo ($isGithubActions ? "::error file={$violation->file}::" : '') . "owner-governance-lint [{$violation->rule}]: {$violation->message}\n";
        }
        if ($orderingViolations !== []) {
            printf("\n❌ owner-governance-lint --enforce-gate-ordering FAIL (%d violation(s))\n", count($orderingViolations));
            exit(1);
        }
        echo "✅ owner-governance-lint --enforce-gate-ordering PASS\n";
    }
```

- [ ] **Step 4: Write `scripts/ci/check-gate3-before-ready.sh`** (the one genuinely hybrid, GitHub-API-dependent check — per the design's own §7.5 admission that this cannot be pure file-tree lint)

```bash
#!/usr/bin/env bash
set -euo pipefail

# Gate-3-before-ready check (Task 9). This is deliberately SEPARATE from
# owner_governance_lint.php: whether a PR is "marked Ready for review" is
# GitHub API state, not repository file state, so it cannot be a pure
# file-tree lint rule — see the approved design spec §7.5's own admission
# that this check is necessarily hybrid.
#
# Usage: PR_NUMBER=<n> bash scripts/ci/check-gate3-before-ready.sh

: "${PR_NUMBER:?PR_NUMBER env var required}"

is_draft="$(gh pr view "$PR_NUMBER" --json isDraft --jq '.isDraft')"
if [ "$is_draft" = "true" ]; then
  echo "PR #$PR_NUMBER is still a draft — gate-3-before-ready does not apply yet."
  exit 0
fi

body="$(gh pr view "$PR_NUMBER" --json body --jq '.body')"
work_id="$(printf '%s' "$body" | grep -oE '(GAP-[0-9]{3}|OWN-[0-9]{4}-[0-9]{3})' | head -n1 || true)"

if [ -z "$work_id" ]; then
  echo "No recognizable Work ID found in PR body's Owner Summary — nothing to check."
  exit 0
fi

if grep -qxF "$work_id" docs/owner-governance/legacy-work-ids.txt 2>/dev/null; then
  echo "Work ID $work_id is legacy-exempt from gate-3-before-ready."
  exit 0
fi

gate3_dir="docs/owner-decisions/$work_id"
if ! ls "$gate3_dir"/03-release*.md >/dev/null 2>&1; then
  echo "::error::PR #$PR_NUMBER is marked Ready for review for work_id $work_id, but no $gate3_dir/03-release*.md packet exists."
  exit 1
fi

echo "Gate 3 packet found for $work_id — gate-3-before-ready check passes structurally (owner_decision may still be 'none', which is correct pre-approval)."
exit 0
```

- [ ] **Step 5: Write `.github/workflows/owner-governance-lint.yml`**

Modeled on `routes-guardrails.yml`'s structure (verified fact #10) but without a MySQL service (not needed — pure PHP + vendored `symfony/yaml`, verified fact #13), and with an explicit `paths` trigger so it actually runs on documentation-only PRs (closing the gap in verified fact #9):

```yaml
name: Owner Governance Lint

on:
  pull_request:
    paths:
      - 'docs/owner-decisions/**'
      - 'docs/owner-governance/**'
      - 'docs/superpowers/plans/**'
      - '.github/PULL_REQUEST_TEMPLATE.md'
      - 'PROJECT_CONSTITUTION.md'
  push:
    branches: [ "main" ]
    paths:
      - 'docs/owner-decisions/**'
      - 'docs/owner-governance/**'
      - 'docs/superpowers/plans/**'

jobs:
  owner-governance-lint:
    name: Owner Governance Lint
    runs-on: ubuntu-latest
    env:
      COMPOSER_PROCESS_TIMEOUT: 0

    steps:
      - name: Checkout
        uses: actions/checkout@v5

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.2"
          tools: composer:v2
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist --no-progress

      - name: Structural validation (schema + enums + contradictions)
        run: php scripts/ssot/owner_governance_lint.php

      - name: Gate-ordering enforcement (effective-date boundary)
        run: php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering

      - name: Gate-3-before-ready check (only on PRs, needs gh + PR number)
        if: github.event_name == 'pull_request'
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
          PR_NUMBER: ${{ github.event.pull_request.number }}
        run: bash scripts/ci/check-gate3-before-ready.sh
```

- [ ] **Step 6: Write the failing enforcement-boundary test**

Create `tests/Unit/OwnerGovernance/EnforcementBoundaryTest.php`:

```php
<?php declare(strict_types=1);

namespace Tests\Unit\OwnerGovernance;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

class EnforcementBoundaryTest extends TestCase
{
    private function repoRoot(): string
    {
        return dirname(__DIR__, 3);
    }

    public function test_enforcement_boundary_file_exists_with_effective_date(): void
    {
        $boundary = Yaml::parseFile($this->repoRoot() . '/docs/owner-governance/enforcement-boundary.yml');
        $this->assertSame('2026-08-04', $boundary['effective_date']);
    }

    public function test_legacy_work_ids_file_contains_gap_031(): void
    {
        $path = $this->repoRoot() . '/docs/owner-governance/legacy-work-ids.txt';
        $this->assertFileExists($path);
        $ids = array_filter(array_map('trim', file($path)), fn ($l) => $l !== '' && !str_starts_with($l, '#'));

        $this->assertContains('GAP-031', $ids, 'GAP-031 pre-dates the owner-governance system and must be legacy-exempt.');
    }

    public function test_owner_governance_workflow_triggers_on_docs_paths(): void
    {
        $content = file_get_contents($this->repoRoot() . '/.github/workflows/owner-governance-lint.yml');
        $this->assertStringContainsString('docs/owner-decisions/**', $content);
        $this->assertStringContainsString('docs/owner-governance/**', $content);
        $this->assertStringContainsString('docs/superpowers/plans/**', $content);
    }

    public function test_lint_script_supports_enforce_gate_ordering_flag(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ssot/owner_governance_lint.php');
        $this->assertStringContainsString('--enforce-gate-ordering', $content);
    }

    public function test_gate3_before_ready_script_checks_legacy_exemption(): void
    {
        $content = file_get_contents($this->repoRoot() . '/scripts/ci/check-gate3-before-ready.sh');
        $this->assertStringContainsString('legacy-work-ids.txt', $content);
    }
}
```

- [ ] **Step 7: Run test to verify it fails (RED)**

Run: `php artisan test --filter EnforcementBoundaryTest`
Expected (before Steps 1–5): FAIL, file-not-found.

- [ ] **Step 8: Run test to verify it passes (GREEN)**

Run: `php artisan test --filter EnforcementBoundaryTest`
Expected: `OK (5 tests, ...)`.

- [ ] **Step 9: Run the extended lint locally against this repo's real state**

Run: `php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering`
Expected: `✅ owner-governance-lint PASS` for structural validation (GAP-031's 4 real packets, Task 4), and `✅ owner-governance-lint --enforce-gate-ordering PASS` (GAP-031 is legacy-exempt; no other work_id-bearing plan file exists yet to check against). Note: `docs/superpowers/plans/2026-08-04-gap031-document-approval-workflow.md`'s filename contains `gap031` lowercase, not `GAP-031` — the regex `\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b` will **not** match it. This is correct and intentional: the ordering check only fires for plan files that *do* reference a recognizable ID; GAP-031's plan predates this whole system and is additionally covered by the explicit legacy list regardless.

- [ ] **Step 10: Run existing repo-wide checks to confirm no regression**

Run: `composer ssot:lint`
Expected: exits `0`, unchanged from pre-task behavior (this task added new scripts, touched no existing SSOT script).

Run: `php artisan test --filter RouteSsotGuardTest` and `php artisan test --filter RouteHygieneTest`
Expected: both still `OK` (unmodified by this task).

- [ ] **Step 11: Commit**

```bash
git add scripts/ssot/generate_legacy_work_ids.php scripts/ssot/owner_governance_lint.php scripts/ci/check-gate3-before-ready.sh docs/owner-governance/enforcement-boundary.yml docs/owner-governance/legacy-work-ids.txt .github/workflows/owner-governance-lint.yml tests/Unit/OwnerGovernance/EnforcementBoundaryTest.php
git commit -m "feat(owner-governance): wire lint into CI with effective-date compatibility boundary (task 9)"
```

**Note on making this a required check:** this task creates the workflow but does **not** add `Owner Governance Lint` to branch protection's `required_status_checks.contexts` — that is a second live configuration change of the same class as Task 8 Step 3, and per this plan's own risk posture (Task 8) must be confirmed with the user separately, after this workflow has run green at least once on a real PR (Task 10's adoption runbook). The exact follow-up command, for the record: `gh api --method PUT repos/kha997/zenamanagephp/branches/main/protection -F 'required_status_checks[contexts][]=test-routes-guardrails' -F 'required_status_checks[contexts][]=Owner Governance Lint' ...` (full field set as in Task 8 Step 3, plus this second context).

**Reviewer acceptance criteria:** `owner-governance-lint.yml` actually triggers on a docs-only diff (unlike the existing `automated-testing.yml`, verified fact #9); GAP-031 is confirmed legacy-exempt without needing a `docs/owner-decisions/GAP-031/` packet to already satisfy gate-ordering (it satisfies it anyway, Task 4, but exemption doesn't depend on that); a new, non-exempt `OWN-YYYY-NNN` work item referencing a plan file with no Gate 2 packet fails `--enforce-gate-ordering`; existing `composer ssot:lint` and route-guardrail tests are unaffected.

---

### Task 10: Final verification and adoption runbook

**Files:**
- Create: `docs/owner-governance/ADOPTION_RUNBOOK.md`
- No new test file — this task *runs* the full verification matrix built by Tasks 1–9 and records the results.

**Interfaces:**
- Consumes: every artifact from Tasks 1–9.
- Produces: a runbook document, and a verification log (pasted into the final commit message / plan execution record — not a separate committed log file, since its content is just the command outputs already reproducible by re-running Steps 1–9 below).

- [ ] **Step 1: Full verification matrix — run every check, record pass/fail**

```bash
# 1. Valid packet lifecycle (Task 5's fixture suite + real GAP-031 packets)
php artisan test --filter OwnerGovernanceLintFixtureTest
php scripts/ssot/owner_governance_lint.php   # scans docs/owner-decisions/ by default

# 2. Invalid transition detection (already covered by the 6 invalid fixtures — re-run explicitly)
php scripts/ssot/owner_governance_lint.php tests/Unit/OwnerGovernance/fixtures/invalid-status-decision-contradiction.md; echo "exit: $?"
# Expected: exit 1

# 3. Blocked Gate 3 visibility (structural — no decision fields set on the blocked packet)
php scripts/ssot/owner_governance_lint.php docs/owner-decisions/GAP-031/03-release.md
# Expected: PASS, and manually confirm decision_requested: null / owner_decision.value: none in that file

# 4. Stale decision invalidation — simulated: no live regression exists to test against
# in this repo today (GAP-031's readiness has not regressed since Task 4's snapshot).
# This is a DESIGN-LEVEL guarantee (packet-schema.yml's gate_status_requires_owner_decision
# table + the design's §3.6 automatic-revert rule), not something this repo-native lint
# can execute as a live scenario without a real CI regression to react to. Recorded here
# as a VERIFIED LIMITATION, not silently assumed working: the automatic revert is a
# process this plan documents (Constitution §3a, OWNER_OPERATING_MODEL.md) and the schema
# supports (a blocked_technical packet with superseded_by pointing at a stale awaiting_owner
# packet is structurally expressible and lint-valid), but nothing in Tasks 1–9 builds an
# automated trigger that *creates* a new blocked_technical packet when CI goes red after
# awaiting_owner — that would require a CI webhook/bot, which is out of this plan's scope
# (no notification infrastructure, per Global Constraints). Flagged as Implementation Risk #4 below.

# 5. Technical readiness / owner decision separation (schema-level, Task 1)
php artisan test --filter OwnerGovernanceSchemaFixtureTest

# 6. Work-ID compatibility (Task 9)
php artisan test --filter EnforcementBoundaryTest

# 7. PR-template behavior (Task 7)
php artisan test --filter PrTemplateTest
head -n1 .github/PULL_REQUEST_TEMPLATE.md   # Expected: "## Owner Summary (read this first — no code required)"

# 8. Agent stop-report format (Task 6)
php artisan test --filter ConstitutionAmendmentTest

# 9. CI behavior (Task 9) — dry run the workflow's steps locally
php scripts/ssot/owner_governance_lint.php
php scripts/ssot/owner_governance_lint.php --enforce-gate-ordering

# 10. No production-code or DB-schema changes
git diff --stat HEAD~9..HEAD -- app/ database/migrations/
# Expected: empty output (no files listed) — confirms Tasks 1-9 touched only docs/, scripts/, tests/, .github/

# 11. GAP-031 example readability by a non-technical owner (manual review checklist, not automatable)
#     Re-read docs/owner-decisions/GAP-031/03-release-v2.md body and confirm:
#     - no class/method name, SQL, HTTP status code, or CI job name appears
#     - a person with zero engineering background could answer "phát hành hay không?"
#       using only this file's body
```

- [ ] **Step 2: Write `docs/owner-governance/ADOPTION_RUNBOOK.md`**

```markdown
# Adoption Runbook — How a New Work Item Moves Through the Owner Control Layer

Worked walkthrough for the next new work item (`OWN-2026-001`, hypothetical) moving through Gate 1 → Gate 2 → implementation → Gate 3.

## 1. Gate 1

1. Agent identifies an operational problem, assigns `work_id: OWN-2026-001` (owner-raised — see `OWNER_OPERATING_MODEL.md` on ID prefixes).
2. Agent copies `docs/owner-governance/templates/gate-1-business-request.md` to `docs/owner-decisions/OWN-2026-001/01-request.md`, fills in every field, sets `gate_status: preparing`.
3. Agent runs `php scripts/ssot/owner_governance_lint.php docs/owner-decisions/OWN-2026-001/01-request.md` — must PASS before presenting to the owner.
4. Agent presents the packet in the working conversation (no notification infrastructure in this phase — `OWNER_OPERATING_MODEL.md` §"Owner Decision Packets").
5. Owner responds. Agent updates the same file: `gate_status: awaiting_owner` → owner decides → agent records `owner_decision.value: approved`, `gate_status: approved`, and a real `decision_provenance` block (`trust_level: claimed_repo_record`, `recorded_by`, `recorded_at`, `owner_response_reference` pointing at this conversation).
6. Re-run the lint. Must PASS.

## 2. Gate 2

Same mechanics with `docs/owner-governance/templates/gate-2-business-design.md` → `02-design.md`. **Do not create any file under `docs/superpowers/plans/` until this file's `owner_decision.value` is `approved`** — `owner_governance_lint.php --enforce-gate-ordering` will fail the PR otherwise (Task 9), for any work_id not on the legacy-exempt list.

## 3. Implementation (Gate 2 approved → Gate 3 awaiting_owner)

1. Agent creates the Gate 3 packet immediately, `gate_status: preparing` — do not wait until the end.
2. Implementation, tests, review, CI proceed per the normal `superpowers:writing-plans` → `superpowers:subagent-driven-development` flow — nothing about this changes.
3. If a mandatory technical gate is red at any point, set `gate_status: blocked_technical`, `technical_readiness.value: blocked`, fill in `mandatory_technical_gate_summary` in plain language. This packet is visible to the owner (labeled `BLOCKED — OWNER ACTION NOT REQUIRED`) but requests nothing.
4. Once every mandatory gate is green: create a **new** file (`03-release-v2.md` if a blocked one preceded it, else `03-release.md` directly), `gate_status: awaiting_owner`, `technical_readiness.value: ready`, `decision_requested` set, `supersedes` pointing at the blocked file if one exists.
5. `bash scripts/ci/check-gate3-before-ready.sh` (with `PR_NUMBER` set) confirms the packet exists before the PR may be marked Ready for review, for non-exempt work IDs.

## 4. Gate 3 decision and release

1. Agent presents the `awaiting_owner` packet in conversation.
2. Owner decides. Agent records `owner_decision.value`, `gate_status`, and provenance, exactly as Gate 1/2.
3. If `approved`: PR may be merged once repository requirements (CODEOWNERS review, required CI) are *also* independently satisfied — the lint does not merge anything itself.
4. If `correction_requested` or `deferred`: `gate_status` moves to `changes_requested`/`deferred`, work returns to `preparing`.

## Rollback of governance enforcement (without deleting decision records)

If `owner-governance-lint` CI integration causes unintended blockage (a false positive blocking an unrelated PR, a schema bug):

1. **Immediate mitigation — remove the workflow from required status checks** (if it was ever added there, per Task 9's deferred follow-up), via `gh api --method PUT repos/kha997/zenamanagephp/branches/main/protection` with `Owner Governance Lint` omitted from `required_status_checks[contexts]`. This does not touch `.github/workflows/owner-governance-lint.yml` itself — the workflow keeps running and reporting, just non-blocking.
2. **If the workflow itself needs to stop running**, add `docs/owner-governance/**`, `docs/owner-decisions/**`, `docs/superpowers/plans/**` to a temporary `paths-ignore` in `.github/workflows/owner-governance-lint.yml`, or set the job to `if: false` — a normal, revertible file edit.
3. **Never delete `docs/owner-decisions/**` content to "fix" a blocked PR.** Decision records are historical evidence (design §6.5, immutable-by-convention) — a bad lint rule is a lint bug, not a reason to erase a real recorded decision. Fix the lint (`scripts/ssot/owner_governance_lint.php`) or its schema (`packet-schema.yml`), add a regression fixture, and re-enable.
```

- [ ] **Step 3: Run the full existing repo test suite once, scoped to files this plan touched, to confirm zero regression**

Run: `php artisan test --filter "OwnerGovernance"`
Expected: all ~35 new tests across Tasks 1–9 pass (`OwnerGovernanceSchemaFixtureTest` 5, `OwnerOperatingDocsTest` 4, `PacketTemplateTest` 5, `Gap031WorkedExampleTest` 3, `OwnerGovernanceLintFixtureTest` 10, `ConstitutionAmendmentTest` 5, `PrTemplateTest` 3, `CodeownersTest` 2, `EnforcementBoundaryTest` 5 — total 42).

- [ ] **Step 4: Commit**

```bash
git add docs/owner-governance/ADOPTION_RUNBOOK.md
git commit -m "docs(owner-governance): add adoption runbook and final verification record (task 10)"
```

**Reviewer acceptance criteria:** every command in Step 1 was actually run (not assumed) with output pasted into the task's SDD report; the runbook's 4 sections cover Gate 1 → Gate 2 → implementation → Gate 3 → rollback end-to-end for a hypothetical new work item without inventing any field not already defined in Task 1's schema; Implementation Risk #4 (stale-decision auto-revert has no automated trigger in this repo-native-only plan) is explicitly acknowledged, not silently glossed over.

---

## Plan Self-Review

**1. Spec-to-task coverage** (every approved-design section mapped to a task):

| Design section | Task(s) |
|---|---|
| §0 Pre-approved decisions | Global Constraints (verbatim) |
| §2 OCL/EEL layers | Task 3 (templates separate OCL body from EEL `references`), Task 7 (PR template layering) |
| §3.1–§3.3 Gate definitions | Task 1 (schema), Task 3 (templates) |
| §3.4 Canonical Gate 3 sequence | Task 6 (§3a constitution text) |
| §3.5 Blocked visibility | Task 1 (`blocked_technical` + `decision_requested: null` rule), Task 5 (lint rule `decision-requested-leaked`), Task 4 (real blocked packet) |
| §3.6 Status machine | Task 1 (`packet-schema.yml`'s `gate_status_values` + `gate_status_requires_owner_decision`), Task 5 (contradiction lint rule) |
| §3.7 Readiness/decision independence | Task 1 (two separate frontmatter blocks), Task 5 (`technical-readiness-provenance` rule) |
| §5.1/§5.2/§5.3 Escalation rules | Task 2 (`OWNER_DECISION_RULES.md`, four-question test) |
| §6.1 Target file structure | File Structure section, Tasks 1–4 |
| §6.2 Frontmatter | Task 1 (reconciled to `gate_status` naming, documented) |
| §6.4 Identity model | Task 1 `work_id_pattern`, Task 9 (`OWN-*` new-work handling) |
| §6.5 Supersession | Task 4 (real `03-release.md`/`03-release-v2.md` pair), Task 5 (`dangling-supersession-link` rule) |
| §6.6 Content vs decision authorship | Task 5 (`generated_by`/`technical-readiness-provenance` rules) |
| §6.7 Placeholder/contradiction checks | Task 5 (`placeholder-token`, `status-decision-contradiction` rules) |
| §6.8/§6.8a Honest provenance | Task 1 (`decision_provenance` fields), Task 5 (`dishonest-provenance` rule), Task 8 (identity-mitigation honesty section) |
| §6.9 Repo-native presentation | Task 10 (adoption runbook Step 1's "present in conversation") |
| §6.10 Language guide | Task 2 (`OWNER_LANGUAGE_GUIDE.md`) |
| §7 Lint contract | Task 5 (implementation), Task 9 (CI wiring + compatibility boundary) |
| §8 Agent behavior changes | Task 6 (Constitution §3a, SSOT Rule 9) |
| §9 GAP-031 worked example | Task 4 |
| §10 In-app Decision Center | Explicitly out of scope, per Global Constraints — no task implements it |
| §11 Scope constraints | Honored throughout — no controller/model/migration/DB change in any task |
| §12 Open questions | Task 8 (branch-protection/CODEOWNERS enforcement — the one item the design left open — resolved here as far as repo-native tooling can) |

No design section is without a task. No task implements something absent from the design (verified by re-reading each task against the Global Constraints list).

**2. No circular Gate 3 dependency:** Task 1's schema makes `awaiting_owner` reachable only via `technical_readiness.value: ready` (a fact Task 5's lint checks structurally exists, though it cannot *compute* readiness from CI itself — that remains a human/agent judgment call recorded in the file, consistent with the design's own admission that CI-state-to-readiness translation is not purely mechanical). Gate 3 preparation (Task 10's runbook §3) is authorized by Gate 2 approval alone, with zero reference to any Gate 3 decision field. Confirmed no task's "Consumes" line names a field a later step in the same task produces circularly.

**3. Blocked work stays visible:** Task 4 creates a real `03-release.md` with `gate_status: blocked_technical` that is never deleted (only superseded, `superseded_by` pointing forward) — confirmed by `Gap031WorkedExampleTest::test_release_v2_supersedes_release_v1_without_contradiction`.

**4. Readiness/decision independence:** confirmed structurally by `packet-schema.yml`'s two separate blocks and `OwnerGovernanceLintFixtureTest`'s dedicated `invalid-status-decision-contradiction.md`/`invalid-blocked-requests-decision.md` cases, which specifically target conflation.

**5. Stale decisions invalidated:** **partially covered, honestly flagged.** The schema/lint can *represent* a correctly-invalidated state (a superseded `awaiting_owner` packet + a fresh `blocked_technical` one). Nothing in this repo-native-only plan *automatically creates* that fresh packet when CI regresses after `awaiting_owner` — no CI webhook/bot exists to do so without notification infrastructure, which Global Constraints explicitly exclude. Recorded as Implementation Risk #4 (Task 10, Step 1) rather than silently claimed as solved.

**6. Lint does not claim authentication:** confirmed — `owner_governance_lint.php`'s docblock states this explicitly; Task 8's `OWNER_OPERATING_MODEL.md` addendum states, unhedged, what CODEOWNERS review does and does not prove; `decision_provenance.trust_level` is validated only for enum membership, never treated as proof.

**7. Historical work not unintentionally blocked:** Task 9's `enforcement-boundary.yml` + `legacy-work-ids.txt` (33 real `GAP-NNN` IDs, generated from the actual register, verified fact #3) exempt every pre-existing work ID from gate-ordering; `EnforcementBoundaryTest::test_legacy_work_ids_file_contains_gap_031` proves this concretely rather than asserting it abstractly.

**8. New work cannot evade enforcement:** the legacy exemption is a committed, one-time-generated, reviewable file — a new PR cannot silently add its own ID to it without that addition being visible in the diff (and, once Task 8 Step 3 lands, requiring a CODEOWNERS review from `@kha997` to merge, since `legacy-work-ids.txt` lives under `docs/owner-governance/`).

**9. Owner content stays understandable without technical evidence:** `Gap031WorkedExampleTest::test_awaiting_owner_packet_does_not_ask_owner_to_read_pr_or_ci` asserts this directly against the real packet body, not just the template.

**10. Implementation details not escalated to the owner:** Task 2's `OWNER_DECISION_RULES.md` four-question test is quoted verbatim into Task 6's Rule 9 amendment, so it reaches agents through the actual SSOT rules file they're required to follow, not left stranded in a document nobody is pointed to.

**11. Files and commands are exact:** every task names exact paths and exact runnable commands (`php artisan test --filter <Class>`, `php scripts/ssot/owner_governance_lint.php <path>`, `git add <files>` + `git commit -m "..."`); no task says "run the tests" without naming which.

**12. Placeholder/ambiguity scan:** searched this plan for "add relevant," "handle edge cases," "update documentation" (bare), "configure as appropriate," "TBD," "TODO" used as an instruction (as opposed to appearing inside a fixture *demonstrating* the placeholder-detection rule, which is intentional and expected) — none found as an actual instruction to the plan's executor. Every code block is complete, runnable content, not a description of code.

---

## Verified GitHub Identity/Control Facts (summary)

- No `.github/CODEOWNERS` existed before this plan (verified, not assumed).
- Branch protection on `main` requires exactly one status check (`test-routes-guardrails`) and **no PR review of any kind** — confirmed via the full JSON, not inferred from partial output.
- `kha997` is the sole repository collaborator with write access, and is simultaneously this session's own authenticated `gh` identity — a fact this plan states plainly rather than glossing over (Task 8).
- `ZMC-*`/`WP-*` work-ID prefixes named in the approved design/instructions do not currently exist anywhere in this repository (zero grep matches) — the schema accepts them generically for forward-compatibility, but no real fixture could be built against them, and this plan says so rather than fabricating an example.

## Selected Near-Term Provenance Mitigation

CODEOWNERS (`@kha997` on `docs/owner-decisions/`, `docs/owner-governance/`, `PROJECT_CONSTITUTION.md`) + branch-protection `required_pull_request_reviews.require_code_owner_reviews: true` (Task 8). Explicitly documented as mitigation, not proof — closed only by the future authenticated Decision Center, out of this plan's scope.

## Compatibility/Enforcement Boundary

Effective date `2026-08-04`. Legacy exemption = explicit enumerated list (`legacy-work-ids.txt`, 33 real GAP IDs) generated once from `OPERATIONAL_GAP_REGISTER.md`. Gate-ordering enforcement (`gate_2_before_plan`, `gate_3_before_ready`) applies only to non-exempt work IDs; structural packet validity is never exempt. New work cannot self-declare legacy status — the exemption list is a committed, reviewable artifact under CODEOWNERS protection.

## Implementation Risks

1. **`gh` token identity coincidence (Task 8).** In this environment, the agent's own `gh` credentials ARE the repository owner's. A CODEOWNERS-review requirement does not defend against an agent (or anyone else) already holding those credentials both drafting and "approving" a governance PR. This is stated, not hidden — see Task 8 Step 2's addendum text.
2. **Branch-protection PUT replaces the whole protection object (Task 8/9).** The exact command in Task 8 Step 3 re-states every currently-configured field to avoid accidentally clearing one; if branch protection is manually changed between plan-writing and plan-execution, the command must be re-derived from a fresh `GET`, not run stale.
3. **`automated-testing.yml`'s existing `paths-ignore` (verified fact #9) means the large CI suite still never runs `docs-lint.sh` on doc-only PRs, independent of this plan.** Not this plan's bug to fix (out of scope — it's an existing repo characteristic, not part of the Owner Control Layer), but worth naming so it isn't mistaken for something this plan claims to have fixed.
4. **Stale-decision auto-revert (design §3.6) has no automated trigger in a repo-native-only system (Task 10, Implementation Risk noted there).** The schema can *represent* the correctly-reverted state; nothing here *creates* it automatically on a CI regression. Only the future Decision Center (webhook-driven) can close this fully — repo-native mitigation is a documented manual step in the adoption runbook, not an automated one.
5. **Task 9's `--enforce-gate-ordering` regex (`\b(GAP-\d{3}|OWN-\d{4}-\d{3})\b`) matches on filename text, not a frontmatter field**, because `docs/superpowers/plans/*.md` files have no established frontmatter convention today (verified fact #17). A plan file that references its work ID only in prose, not in its filename, will silently bypass gate-ordering enforcement. Mitigated partially by the legacy list covering all pre-existing cases, but a *future* non-compliant plan filename is a real gap this plan does not fully close — flagged, not solved, since inventing a new frontmatter convention for `docs/superpowers/plans/*.md` generally is outside this plan's scope (it would affect every future plan in the repository, governance-related or not).
