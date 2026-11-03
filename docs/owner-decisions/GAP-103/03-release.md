---
work_id: GAP-103
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
  spec: docs/audits/2026-11-03-gap-103-project-health-read-model-evidence.md
  plan: docs/superpowers/plans/2026-11-03-gap-103-project-health-read-model.md
  branch: docs/GAP-103-project-health-read-model
  pr: https://github.com/kha997/zenamanagephp/pull/393
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-03T18:56:59+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-11-03, verbatim: 'APPROVE GAP-103 Gate 3' (re-approval after the Codex review fixes). Given on the re-presented packet at PR head b4389ea021e7914771ccb5fc9dc6c8f0c8a4444b; bound to implementation subject 5fdf4a14b7165fa8eb7ca4b03c420d83cb1ceddc and implementation-tree digest dd5526141b7c0d97ec80b6185f04db7bae3105f9c6b82814d8b81efa746a59b2 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-03T16:39:01+07:00"
  updated_at: "2026-11-03T18:56:59+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-103 at subject 5fdf4a14b7165fa8eb7ca4b03c420d83cb1ceddc (Gate 2 P-A, C-1, X-1; exact allowlist; Codex review fixes on #393 applied). New read-only ProjectHealthReadService: progress from root components weighted by planned cost, else the average of non-cancelled tasks (limited reliability), else no_data; schedule reuses ProjectDelayStatus plus milestone counts; attention counts (blocked/overdue tasks, blocked design items, overdue milestones, schedule late, treasury alerts or not_applicable); separate money measures (budget, contract value, cash received, cost recorded, treasury held/expenses) each with availability, no profit or net figure. Every query is tenant- and project-scoped; the own aggregates are a fixed count independent of task/component count, while the Treasury alert count reuses TreasuryDashboardService (cost grows with posted advances, same as the Treasury page). forProject evaluates every section against one clock (no caller-supplied date, since ProjectDelayStatus reads the clock itself). The project page 'Tien do' field calls only the public progress() read and shows 'Chua co du lieu' instead of 0%; it never builds the Treasury dashboard. No migration, projects.progress unchanged, ProjectAnalyticsService untouched (X-1). ProjectHealthReadServiceTest: 10 tests green; the page-without-dashboard test is red against the previous controller. Unit + Feature + Integration 2876 tests with only the 2 known root-only failures and Feature/Api 824 green at b14f5957; after the fix 209 project/baseline/delay feature tests green. PHPStan, php-cs-fixer, SSOT test lint pass. Exact-head CI pending at packet time."
technical_evidence:
  base_sha: "aefd55fd2dca9750d2eba51a2390ddb4a163df7a"
  subject_sha: "5fdf4a14b7165fa8eb7ca4b03c420d83cb1ceddc"
  implementation_tree_digest: "dd5526141b7c0d97ec80b6185f04db7bae3105f9c6b82814d8b81efa746a59b2"
  verified_pr_head_sha: "5fdf4a14b7165fa8eb7ca4b03c420d83cb1ceddc"
  verified_at: "2026-11-03T16:45:17+07:00"
owner_decision_binding:
  implementation_tree_digest: "dd5526141b7c0d97ec80b6185f04db7bae3105f9c6b82814d8b81efa746a59b2"
  decision_recorded_at: "2026-11-03T18:56:59+07:00"
---

# GAP-103 — Gate 3 release decision (Shared Project Health Read Model)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 again in-session on 2026-11-03 ("APPROVE GAP-103 Gate 3"), bound to implementation subject
`5fdf4a14b7165fa8eb7ca4b03c420d83cb1ceddc` and implementation-tree digest `dd5526141b7c0d97ec80b6185f04db7bae3105f9c6b82814d8b81efa746a59b2`. No deployment or release is authorized.

## Phê duyệt trước đã bị thay thế

Owner đã duyệt Gate 3 ("APPROVE GAP-103 Gate 3", subject `b14f59577a16463148edd7b61fa653a42936817a`, digest
`310e995f5870c78bdeff9b37a644b71082c9bafa8df15743f1340518e11b2af2`). Sau đó Codex review PR #393 có 2 phát hiện đúng; mã đã sửa
nên phê duyệt đó không còn áp dụng và hồ sơ trình lại.

**Sửa theo Codex (trong allowlist Gate 2):**
- Trang dự án trước đây dựng toàn bộ mô hình sức khoẻ, kể cả đếm cảnh báo Ngân quỹ qua `TreasuryDashboardService` (chi phí tăng
  theo số khoản tạm ứng, ~8 truy vấn mỗi khoản). Nay trang chỉ gọi phần tiến độ (`progress()`), không đụng Ngân quỹ.
- `forProject()` bỏ tham số ngày do người gọi truyền vào: trạng thái trễ (`ProjectDelayStatus` tự đọc đồng hồ) và các số quá hạn
  giờ cùng một mốc thời gian.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Có 4 cách tính "tiến độ" khác nhau; trang dự án hiện 0% khi thực ra chưa có dữ liệu (Gate 1).

**2. Ai bị ảnh hưởng?** Người xem trang chi tiết dự án; sau này trang OPPM (issue #248) dùng chung mô hình này.

**3. Sau thay đổi (Gate 2 P-A, C-1, X-1)** — subject `5fdf4a14`:
- Dịch vụ chỉ đọc `ProjectHealthReadService` trả về sức khoẻ dự án gồm 4 phần. Mỗi số đều kèm trạng thái "có dữ liệu" / "chưa có dữ liệu" / "không áp dụng":
  - **Tiến độ:** theo chi phí hạng mục; nếu không có hạng mục thì lấy trung bình các công việc, kèm ghi chú "độ tin cậy hạn chế".
  - **Lịch:** trễ / đúng hạn theo kế hoạch gốc, cùng số mốc hoàn thành và quá hạn.
  - **Cần chú ý:** công việc bị chặn hoặc quá hạn, hạng mục thiết kế bị chặn, cảnh báo ngân quỹ.
  - **Tiền:** ngân sách, giá trị hợp đồng, đã thu, chi phí đã ghi nhận và ngân quỹ, để riêng từng khoản. Không gộp thành "lãi".
- Trang dự án, ô **"Tiến độ"**: hiện "40% (theo công việc)" hoặc "Chưa có dữ liệu", không còn 0% giả.
- Không đổi: CSDL (không có migration), cột `projects.progress`, `ProjectAnalyticsService` (Work ID riêng, theo X-1).

**4. Rủi ro đã đóng** — Không còn hiển thị 0% / "đúng hạn" khi thiếu dữ liệu. Dữ liệu được lọc theo đúng tenant và dự án, có test chứng minh.

**5. Đã kiểm thử** — base `aefd55fd`; subject `5fdf4a14b7165fa8eb7ca4b03c420d83cb1ceddc`;
digest `dd5526141b7c0d97ec80b6185f04db7bae3105f9c6b82814d8b81efa746a59b2`.
- **Đỏ trước:** test mới hỏng (chưa có dịch vụ).
- **Xanh sau:** 10 test xanh (thêm: trang dự án không gọi bảng Ngân quỹ — đỏ với controller cũ): tiến độ theo hạng mục, theo công việc, chưa có dữ liệu; lịch và mốc; cần chú ý; tiền; cách ly tenant/dự án; số truy vấn không tăng khi có thêm công việc; hiển thị trên trang.
- Toàn bộ Unit + Feature + Integration (tại `b14f5957`): 2.876 test, chỉ 2 lỗi có sẵn do chạy bằng root. Feature/Api: 824 test xanh.
  Sau bản sửa: 209 test dự án / kế hoạch gốc / trễ tiến độ xanh.
- PHPStan, php-cs-fixer, SSOT test lint sạch.
- CI trên đúng head: đang chạy lúc lập hồ sơ.

**6. Ngoài phạm vi** — trang OPPM (làm tiếp theo); sửa `ProjectAnalyticsService`; đổi cách lưu `projects.progress`.

**7. Rủi ro còn lại** — Thấp: chỉ đọc, thêm vài truy vấn tổng hợp khi mở trang dự án.

**8. Hoàn tác** — revert PR; không có dữ liệu nào bị thay đổi.

**9. Đề xuất** — Phát hành (merge).

## Decision Needed

Owner chọn: `APPROVE GAP-103 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
