---
work_id: GAP-062
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: approve_or_changes_or_decline
references:
  spec: docs/audits/2026-10-06-gap-062-ssot-test-lint-false-green-evidence.md
  plan: null
  branch: docs/GAP-062-ssot-test-lint-false-green
  pr: https://github.com/kha997/zenamanagephp/pull/334
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
  created_at: "2026-10-06T11:15:55+07:00"
  updated_at: "2026-10-06T11:15:55+07:00"
generated_by: agent
---

# GAP-062 — SSOT test lint false-green: Gate 2 design

## Owner Summary

Làm cho bước kiểm tra quy ước test chạy thật trên CI: cài `rg` cho CI, thiếu
`rg` thì báo đỏ; sửa danh sách "endpoint đã chết" (bỏ 3 endpoint còn chạy);
ghi các vi phạm hiện có vào baseline như nợ; so sánh baseline **không phụ
thuộc số dòng** để sửa file test không làm đỏ oan. Từ nay vi phạm mới bị chặn.
Không viết lại test, không sửa mã ứng dụng. Đề xuất **Phương án 1**.

## Comparison

| Option | Content | Verdict |
|---|---|---|
| **1. Truthful gate + freeze inventory** | rg in CI, fail-closed, denylist fix, rebaseline, line-insensitive compare | **Recommended** |
| 2. Same, annotate ~89 lines instead of baseline | Inline `ssot-allow-raw-model*` markers in 11 test files | Same end state, more test churn |
| 3. Same, refactor raw creates to factories | Large rewrite of RBAC fidelity tests | Risks weakening tests whose point is raw storage shape |
| 4. Retire rg-based checks | Remove gate | Loses the conventions |

## Design: Option 1 (exact allowlist)

1. **`scripts/ssot/lint_tests.sh`**
   - After `set -euo pipefail`: `export LC_ALL=C` (deterministic `sort`/`comm`
     on macOS and Linux) and a fail-closed guard: if `command -v rg` fails,
     print `[ssot] ripgrep (rg) is required — refusing to report a false pass`
     to stderr and exit 1.
   - `check_with_baseline`: compare on a normalized key that drops the line
     number (`path:NNN:content` → `path:content`), so editing a test file
     elsewhere does not turn baselined lines "new". Output still prints the
     full `path:line:content` of genuinely new lines. Baseline files keep the
     current `path:line:content` format (written by
     `SSOT_UPDATE_BASELINES=1`, unchanged). Known limitation: a second,
     byte-identical line in the same file as a baselined one is not reported.
   - Nothing else in the script changes (collectors, skip inventory, exact
     baseline check).
2. **`scripts/ssot/denylist_endpoints.txt`** — remove `/api/dashboards`,
   `/api/widgets`, `/api/support/tickets` (live routes: 5/3/4). Keep
   `/api/v1/users`, `/api/v1/templates` (0 routes).
3. **Baselines** (`scripts/ssot/baselines/`) — regenerate with
   `SSOT_UPDATE_BASELINES=1 bash scripts/ssot/lint_tests.sh` on the
   implementation tree after steps 1–2. Expected: `denylist_hits` 0 (hits
   disappear with step 2), `raw_model_create` 7→31, `raw_model_create_feature`
   0→64, `raw_model_create_zena` 0→1; other baselines may only shrink or stay
   identical (any growth elsewhere stops the work for disclosure).
4. **`.github/workflows/ci-cd.yml`** (code-quality job) and
   **`.github/workflows/ci-cd-code-quality-debug.yml`**: a step before
   `composer ssot:lint` — `sudo apt-get update && sudo apt-get install -y
   ripgrep && rg --version`.

## Verification required at Gate 3

- Local: `lint_tests.sh` passes with rg; with rg hidden from PATH it exits 1
  with the guard message; a deliberately added raw `Role::create(` in a
  Feature test (temporary) fails; inserting blank lines above baselined lines
  (temporary) still passes; both reverted.
- CI exact head: the Code Quality job log shows `rg --version` and `SSOT
  test lint passed` with **no** `rg: command not found`; all PR checks green.
- Disposable proof branch (deleted after capture, not an ancestor): one new
  raw `Permission::create(` in a Feature test makes the CI code-quality job
  fail on `raw_model_create_feature`.

## Risk

New violations now fail CI — intended. Existing debt stays recorded in
baselines (89 lines) for later, separately governed cleanup.

## Out of scope

Rewriting or annotating tests; application code; other `ssot:lint` steps.
