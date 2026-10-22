---
work_id: GAP-091
gate: 3
gate_status: approved
technical_readiness:
  value: ready
  generated_by: engineering_evidence
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/audits/2026-10-22-gap-091-governance-lint-wait-evidence.md
  plan: docs/superpowers/plans/2026-10-22-gap-091-governance-lint-wait.md
  branch: docs/GAP-091-governance-lint-wait
  pr: https://github.com/kha997/zenamanagephp/pull/364
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-22T22:37:13+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-22, verbatim: 'APPROVE GAP-091 Gate 3'. Given after the packet at PR head 7b18c2d10e921912812996e5dba7a64bc18d23f0 (35/35 checks green) and after the disclosure that the live proof was not clean: Owner Governance Lint's first attempt on 7b18c2d1 waited past browser-tests and failed at its 60-minute limit because GitHub left the already-green RFI Escalation Concurrency check run reported as in_progress; after Automated Testing was re-run, the lint re-run passed. Bound to implementation subject 687477b74d981ef4a7137186f017d2aaa87c8943 and implementation-tree digest d74533fd36a28934cf86fdbf6571fea4560bdaf1c059d366a2251d70a34d58c8 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-22T20:07:17+07:00"
  updated_at: "2026-10-22T22:37:13+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-091 at subject 687477b74d981ef4a7137186f017d2aaa87c8943 (Gate 2 Option A). scripts/ssot/owner_governance_lint.php adds owner_governance_count_failed_checks (fail and cancel buckets); owner_governance_count_blocking_checks is unchanged. scripts/ci/check-evidence-freshness.sh live check (only while gate_status is awaiting_owner) polls gh pr checks up to 240 x 15 s = 60 min, exits 1 at once when another check has failed or been cancelled and lists the non-passing checks, passes when nothing is blocking, and exits 1 if checks are still pending after the last poll; poll count and interval can be overridden by OWNER_GOV_LIVE_POLL_ATTEMPTS and OWNER_GOV_LIVE_POLL_INTERVAL (tests only; CI never sets them). The digest checks and the self-exclusion of Owner Governance Lint are unchanged. owner-governance-lint.yml job gets timeout-minutes: 75. Tests: EvidenceBindingTest gains two unit tests for the new helper; new EvidenceFreshnessLiveCheckTest runs the real script in a throwaway repository with an awaiting_owner Gate 3 fixture and a fake gh (pending then pass exits 0 after 3 polls; a failed check exits 1 on the first poll; a cancelled check exits 1; always pending exits 1 after the last poll; its own check is ignored); EnforcementBoundaryTest asserts the script uses the new helper. Red proof: against the previous script, the failed-check test exits 0 instead of 1, because the old script kept waiting until the failed check turned green. Local: tests/Unit/OwnerGovernance passes except GrandfatherLoaderTest's unreadable-file case, which also fails on main because the local runner is root; it passes in CI. actionlint shows no findings; governance and docs lints pass. Exact-head CI at 687477b7: 35/35 green, and the Unit Tests job ran the new tests. Live green proof: the commit that adds this awaiting_owner packet must pass Owner Governance Lint on its first attempt; the result is reported with the presentation."
technical_evidence:
  base_sha: "5aae9f70c9289fb25d5e078117b8af5dba1af096"
  subject_sha: "687477b74d981ef4a7137186f017d2aaa87c8943"
  implementation_tree_digest: "d74533fd36a28934cf86fdbf6571fea4560bdaf1c059d366a2251d70a34d58c8"
  verified_pr_head_sha: "687477b74d981ef4a7137186f017d2aaa87c8943"
  verified_at: "2026-10-22T20:07:17+07:00"
owner_decision_binding:
  implementation_tree_digest: "d74533fd36a28934cf86fdbf6571fea4560bdaf1c059d366a2251d70a34d58c8"
  decision_recorded_at: "2026-10-22T22:37:13+07:00"
---

# GAP-091 — Gate 3 release decision (Owner Governance Lint false red)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-22 ("APPROVE GAP-091 Gate 3"), bound to implementation subject
`687477b74d981ef4a7137186f017d2aaa87c8943` and implementation-tree digest `d74533fd36a28934cf86fdbf6571fea4560bdaf1c059d366a2251d70a34d58c8`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Lint chỉ chờ các check khác 5 phút và coi check đang chạy giống check đã đỏ. Vì thế
8/8 lần trình Gate 3 gần đây đều đỏ giả (Gate 1).

**2. Sau thay đổi (Gate 2 Phương án A)** — commit `687477b7`:
- Lint chờ các check khác tối đa **60 phút**.
- Lint **đỏ ngay** khi có check khác đã đỏ hoặc bị hủy, và liệt kê các check đó.
- Hết 60 phút mà còn check đang chạy thì lint vẫn đỏ, như cũ.
- Job lint giới hạn 75 phút.
- Kiểm tra digest và các luật governance khác **không đổi**.

**3. Bằng chứng** — base `5aae9f70`; subject `687477b74d981ef4a7137186f017d2aaa87c8943`;
digest `d74533fd36a28934cf86fdbf6571fea4560bdaf1c059d366a2251d70a34d58c8`.
- Test mới chạy script thật với `gh` giả, gồm 5 tình huống:
  - check đang chạy rồi xanh: lint xanh;
  - có check đỏ: lint đỏ ngay lần kiểm tra đầu;
  - có check bị hủy: lint đỏ;
  - check chạy mãi: lint đỏ khi hết thời gian;
  - check của chính lint được bỏ qua.
- Thử đỏ: với script cũ, tình huống "có check đỏ" cho lint **xanh**, vì lint cũ chờ cho tới khi check đó xanh. Test mới bắt được.
- CI chạy đúng trên `687477b7`: **35/35 xanh**, job Unit Tests đã chạy các test mới.
- Phép thử thật: chính commit đưa gói này lên ở trạng thái "đang chờ Owner". Owner Governance Lint phải xanh
  **ngay lần đầu**. Kết quả sẽ báo cùng lúc trình gói.

**4. Công khai**
- Ở commit trình Gate 3, lint chiếm một runner khoảng 20 phút trong lúc chờ các check khác.
- `GrandfatherLoaderTest` có một test trượt trong máy này, kể cả trên main, vì máy chạy bằng root. Trên CI
  test vẫn xanh. Không liên quan GAP này.

**5. Ảnh hưởng** — Không còn phải chạy lại lint bằng tay mỗi lần trình Gate 3. Lint đỏ thật thì đỏ sớm hơn.

**6. Rủi ro còn lại** — Thấp. Nếu một check treo lâu hơn 60 phút, lint vẫn đỏ như trước.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-091 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy hay release; không đổi luật governance nào khác.
