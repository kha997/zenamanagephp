---
work_id: GAP-045
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
  spec: docs/audits/2026-10-01-gap-045-perf-timing-evidence.md
  plan: null
  branch: docs/GAP-045-perf-timing-gate1
  pr: https://github.com/kha997/zenamanagephp/pull/332
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-02T07:03:09+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-02: 'APPROVE GAP-045 Gate 3'. Given after the packet was presented at PR head e2e7bb9a46d5e71cb1cb4ad235c1b5c0d73357e6 with 33/33 exact-head checks green; bound to implementation subject 25df79395a83c5d9fe6d106d2c6e5f67184466b1 and implementation-tree digest 04ceb1e05fdd069034e6eb0c3a0d4f95693cc2721ba38c12a5987b20b6898fe5 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-02T00:31:37+07:00"
  updated_at: "2026-10-02T07:03:09+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option-A implementation at subject 25df7939: diff exactly the Gate-2 allowlist (one test file, two methods + private reportTimingBudget); 3 local mutation proofs (N+1 in markAlertAsRead -> 1002>800 RED; alerts list truncated to 50 -> count gate RED; budget forced to 1ms -> warning annotation + summary row, test GREEN); full DashboardPerformanceTest 19/19 locally; disposable 10x CI run 36897403420 on GAP-041 head + this change: 10/10 jobs 19/19 passed incl. 3 AMD EPYC 7763 runners, 5 jobs emitted over-budget warnings instead of failing; all 33 exact-head PR checks green; canonical digest computed at subject."
technical_evidence:
  base_sha: "2ca3def397b93a8aa4d632e5d477025b3f62f0bd"
  subject_sha: "25df79395a83c5d9fe6d106d2c6e5f67184466b1"
  implementation_tree_digest: "04ceb1e05fdd069034e6eb0c3a0d4f95693cc2721ba38c12a5987b20b6898fe5"
  verified_pr_head_sha: "25df79395a83c5d9fe6d106d2c6e5f67184466b1"
  verified_at: "2026-10-02T00:31:37+07:00"
owner_decision_binding:
  implementation_tree_digest: "04ceb1e05fdd069034e6eb0c3a0d4f95693cc2721ba38c12a5987b20b6898fe5"
  decision_recorded_at: "2026-10-02T07:03:09+07:00"
---

# GAP-045 — Gate 3 release decision

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-02, bound to implementation subject
`25df79395a83c5d9fe6d106d2c6e5f67184466b1` and implementation-tree digest `04ceb1e05fdd069034e6eb0c3a0d4f95693cc2721ba38c12a5987b20b6898fe5`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Hai kiểm tra thời gian trong
`tests/Performance/DashboardPerformanceTest.php` (tải cảnh báo ≤450ms, đánh
dấu đã đọc 100 cảnh báo ≤1000ms) đỏ/xanh theo loại máy CI — khoảng một nửa số
lần chạy đỏ trên cùng mã nguồn (Gate 1). Đang chặn GAP-041 (#316).

**2. Sau thay đổi (đúng allowlist Gate 2, Phương án A):**

- `it_can_load_alerts_with_large_dataset_quickly`: gate = số truy vấn ≤20 (giữ)
  + phản hồi trả đủ mọi cảnh báo của người dùng (mới). Thời gian trung vị được
  báo cáo so với 450ms (CI) / 300ms (local) — không đổi số.
- `it_can_mark_alerts_as_read_quickly`: gate = đúng 100 cảnh báo, ≤8 truy
  vấn/lần gọi (đo local ~5), cả 100 được lưu là đã đọc (mới). Thời gian báo cáo
  so với 1000ms.
- `reportTimingBudget()` (private): in thời gian; vượt ngân sách trên GitHub
  Actions → `::warning` + một dòng trong job summary; không bao giờ làm đỏ test.

**3. Khác biệt so với Gate 2** — Không có. Không sửa ngưỡng, workflow, mã ứng
dụng hay API.

**4. Bằng chứng kỹ thuật**

- Base `2ca3def3`; subject `25df79395a83c5d9fe6d106d2c6e5f67184466b1`; digest `04ceb1e05fdd069034e6eb0c3a0d4f95693cc2721ba38c12a5987b20b6898fe5`.
- Mutation proof (tạm, đã hoàn tác):
  - Thêm 5 truy vấn thừa vào `DashboardService::markAlertAsRead()` → test đỏ
    "1002 … less than 800".
  - Cắt danh sách cảnh báo còn 50 → test đỏ "actual size 50 matches expected
    size 1000".
  - Ép ngân sách mark-read = 1ms với `GITHUB_ACTIONS=true` → in
    `::warning title=Perf budget (GAP-045)::…`, ghi dòng summary, test xanh.
- Local: cả file 19/19 xanh.
- **CI thật, 10 job song song** (nhánh proof dùng một lần =
  GAP-041 head `33d6f76f` + thay đổi này, chạy cả file với
  `--group=performance --fail-on-empty-test-suite`, MySQL 8; run
  `36897403420`, nhánh đã xoá, không phải tổ tiên của PR): **10/10 job
  success, mỗi job 19 passed (161 assertions)**.

  | CPU | Job | Alerts median | Mark-100 |
  |---|---|---|---|
  | AMD EPYC 9V45 | 10 | 265.80 | 587.96 |
  | AMD EPYC 9V74 | 9 / 2 / 1 / 5 | 331.14 / 394.97 / 423.16 / 425.21 | 722.73 / 933.75 / **1117.33 ⚠** / 927.85 |
  | Intel Xeon 8573C | 7 | 366.26 | 764.07 |
  | Intel Xeon 8370C | 8 | **460.08 ⚠** | 863.04 |
  | AMD EPYC 7763 | 4 / 3 / 6 | **510.78 ⚠** / **522.53 ⚠** / **525.15 ⚠** | 975.65 / **1058.79 ⚠** / **1019.05 ⚠** |

  ⚠ = vượt ngân sách → cảnh báo vàng (trước đây là test đỏ): 5/10 job.
- CI exact head `25df7939`: 33/33 pass.

**5. Ngoài phạm vi** — Phương án C (phân trang cảnh báo); các kiểm tra thời gian
khác; phát hành GAP-041 (bước kế tiếp, cần Gate 3 riêng); cập nhật sổ
OPERATIONAL_GAP_REGISTER (đối soát sau phát hành); deploy.

**6. Rủi ro còn lại** — Thấp. Chậm thuần tuý (không tăng truy vấn) ở hai
endpoint này chỉ còn là cảnh báo vàng, không làm đỏ CI.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, Phương án C, hay phát hành GAP-041.
