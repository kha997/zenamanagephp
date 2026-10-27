---
work_id: GAP-068
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
  spec: docs/audits/2026-10-08-gap-068-reconciliation-mutation-response-evidence.md
  plan: docs/superpowers/plans/2026-10-08-gap-068-reconciliation-mutation-response.md
  branch: docs/GAP-068-reconciliation-mutation-response
  pr: https://github.com/kha997/zenamanagephp/pull/341
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-08T11:19:53+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-08, verbatim: 'APPROVE GAP-068 Gate 3'. Given after the packet was presented at PR head ccc1733c6145d98067d3fe3c8c2c188a37278914; implementation subject b48c42a4c9b6866e0875b29515506522ba069aa7 had 34/34 exact-head checks green; bound to implementation-tree digest d42999e610c5553ca8a56c4b52b553f64a48e88283a26d6181f3a35bb6becce6 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-10-08T11:15:55+07:00"
  updated_at: "2026-10-08T11:19:53+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-068 at subject b48c42a4: reconcile / undo / undo-line API responses are built from a by-id lookup (TreasuryReconciliationService::historyItem) instead of the 100-newest history, so they never return data: null after a successful write; history is paged (API page/per_page with 422 on invalid values, default unchanged at 100; web 50 per page with previous/next links) and ordered with an id tiebreak. 4 new tests red on the unfixed code (2 reproduce data: null), 112 Treasury tests green after the fix; full PHPStan clean; SSOT, governance, docs lints and baseline guard pass; exact-head PR checks 34/34 green. No migration, route, permission or workflow change."
technical_evidence:
  base_sha: "a534de0982f33c85e936ecb4d2aca67d4eee1aa9"
  subject_sha: "b48c42a4c9b6866e0875b29515506522ba069aa7"
  implementation_tree_digest: "d42999e610c5553ca8a56c4b52b553f64a48e88283a26d6181f3a35bb6becce6"
  verified_pr_head_sha: "b48c42a4c9b6866e0875b29515506522ba069aa7"
  verified_at: "2026-10-08T11:15:55+07:00"
owner_decision_binding:
  implementation_tree_digest: "d42999e610c5553ca8a56c4b52b553f64a48e88283a26d6181f3a35bb6becce6"
  decision_recorded_at: "2026-10-08T11:19:53+07:00"
---

# GAP-068 — Gate 3 release decision (API đối soát trả rỗng + phân trang lịch sử)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-08 ("APPROVE GAP-068 Gate 3"), bound to implementation subject
`b48c42a4c9b6866e0875b29515506522ba069aa7` and implementation-tree digest `d42999e610c5553ca8a56c4b52b553f64a48e88283a26d6181f3a35bb6becce6`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** API đối soát (GAP-067) có thể trả thành công nhưng
`data: null` khi lần đối soát bị tác động nằm ngoài 100 lần mới nhất; trang
web không gỡ được lần đối soát cũ hơn 100 lần gần nhất (Gate 1).

**2. Sau thay đổi (Gate 2, Phương án B):**

- Đối soát / gỡ cả lần / gỡ một dòng qua API luôn trả đúng lần đối soát đó
  (tra theo id).
- API lịch sử nhận `page` (≥ 1) và `per_page` (1–100); sai → 422. Mặc định vẫn
  100 lần mới nhất như trước.
- Trang đối soát hiện 50 lần/trang với "Trang trước / Trang sau"; lần cũ vẫn
  xem và gỡ được.
- Thứ tự lịch sử thêm tiêu chí id để phân trang không lặp/sót khi trùng ngày
  và giờ tạo.

**3. Khác biệt so với Gate 2** — Không có. Ghi chú nhỏ: trang web kiểm tra có
trang sau bằng một truy vấn lấy đúng 1 lần đối soát ngay sau trang hiện tại.

**4. Bằng chứng kỹ thuật**

- Base `a534de09`; subject `b48c42a4c9b6866e0875b29515506522ba069aa7`; digest
  `d42999e610c5553ca8a56c4b52b553f64a48e88283a26d6181f3a35bb6becce6`; 9 file
  (4 code, 1 test mới, tài liệu).
- **Test đỏ trước:** `TreasuryReconciliationHistoryPagingTest` (4 test) chạy
  trên code chưa sửa: cả 4 đỏ — 2 test tái hiện đúng `data: null` (đối soát lùi
  ngày ngoài 100 lần mới nhất; gỡ lần cũ), 1 test phân trang API, 1 test
  phân trang web.
- Sau khi sửa: 112 test Treasury / 579 assertion xanh.
- PHPStan toàn repo sạch; SSOT lint, governance lint, docs-lint, baseline
  guard đạt.
- CI exact head `b48c42a4`: 34/34 pass (gồm cả job tranh chấp Treasury trên
  MySQL thật).

**5. Ngoài phạm vi** — Quy tắc đối soát, khoá, quyền, S4b–S6, deploy.

**6. Rủi ro còn lại** — Thấp. Chỉ đổi cách đọc/trả dữ liệu; không đổi ghi.

**7. Hoàn tác** — Revert squash commit (không có migration).

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: `APPROVE GAP-068 Gate 3` / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy.
