---
work_id: GAP-119
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
  spec: docs/audits/2026-11-10-gap-119-project-analytics-cleanup-evidence.md
  plan: docs/superpowers/plans/2026-11-10-gap-119-project-analytics-cleanup.md
  branch: docs/GAP-119-project-analytics-cleanup
  pr: https://github.com/kha997/zenamanagephp/pull/409
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-11-10T13:28:01+07:00"
  owner_response_reference: "Owner decision in-session on 2026-11-10, verbatim: 'APPROVE GAP-119 Gate 3'. Given on the packet at PR head 6a362d23314c14ba1368c3fdfa2d3265828ab277; bound to implementation subject 118ba9a55dbcc7710e0754c4d348e39f5b537602 and implementation-tree digest 260a5057df8a40abb1eceaa1248d36ff8b1c0440e3cb390b31b0c150089916f7 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment or release authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T13:12:05+07:00"
  updated_at: "2026-11-10T13:28:01+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-119 at subject 118ba9a55dbcc7710e0754c4d348e39f5b537602 (Gate 2 A-1, E-1, P-1; exact allowlist, no amendment; one PR, one implementation commit; removal only, no migration). A-1: routes/api.php drops the api/analytics block of Api\\ProjectAnalyticsController (four live routes whose methods never existed, always HTTP 500, plus a dashboard route already shadowed by AnalyticsController); ProjectAnalyticsController and ProjectAnalyticsService deleted; api/analytics/dashboard still served by AnalyticsController. E-1: ProjectMilestoneCompleted, ProjectTaskUpdated, ProjectTeamMemberJoined, Listeners\\LogProjectActivity and RealTimeNotificationService deleted; ProjectActivity::logMilestoneCompleted / logTaskUpdated / logTeamMemberJoined removed, other ProjectActivity methods kept. P-1: Project::calculateProgress and ProjectModuleTest::test_project_progress_calculation removed; recalculateProgress kept. 153 phpstan-baseline entries of deleted files removed; inventory doc noted; GAP-117 test comment updated. Guard test DeadProjectAnalyticsAndEventCodeRemovedTest (3) red before, green after; route:list runs; 93 related tests green. Full PHPStan, php-cs-fixer, ssot:lint, deptrac, governance lint pass. Local Unit + Feature + Integration 3015 tests: only the 2 known root-only failures; Feature/Api 825 green. Net 2,662 lines removed. Exact-head CI recorded in the body."
technical_evidence:
  base_sha: "7352fc46f3dd3f7c55484767d399f3c60b7fc8ab"
  subject_sha: "118ba9a55dbcc7710e0754c4d348e39f5b537602"
  implementation_tree_digest: "260a5057df8a40abb1eceaa1248d36ff8b1c0440e3cb390b31b0c150089916f7"
  verified_pr_head_sha: "118ba9a55dbcc7710e0754c4d348e39f5b537602"
  verified_at: "2026-11-10T13:12:05+07:00"
owner_decision_binding:
  implementation_tree_digest: "260a5057df8a40abb1eceaa1248d36ff8b1c0440e3cb390b31b0c150089916f7"
  decision_recorded_at: "2026-11-10T13:28:01+07:00"
---

# GAP-119 — Gate 3 release decision (broken project analytics API and dead event code)

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-11-10 ("APPROVE GAP-119 Gate 3"), bound to implementation subject
`118ba9a55dbcc7710e0754c4d348e39f5b537602` and implementation-tree digest `260a5057df8a40abb1eceaa1248d36ff8b1c0440e3cb390b31b0c150089916f7`. No deployment or release is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?**
- 4 địa chỉ API thống kê dự án luôn báo lỗi 500.
- Dịch vụ thống kê và cả nhóm sự kiện / thông báo cũ là mã chết: không ai gọi, và chính các đoạn mã đó cũng viết sai.

**2. Sau thay đổi (Gate 2 A-1, E-1, P-1)** — subject `118ba9a5`.

**Đã gỡ**
- **A-1:**
  - các route thống kê dự án hỏng;
  - bộ điều khiển và dịch vụ thống kê cũ.
- **E-1:**
  - 3 sự kiện (có "hoàn thành mốc");
  - bộ ghi nhật ký cho các sự kiện đó;
  - dịch vụ thông báo thời gian thực;
  - 3 hàm ghi nhật ký chỉ nhóm này dùng.
- **P-1:** hàm tính tiến độ theo số mốc, cùng test riêng của nó.
- **Tổng cộng:** bớt khoảng **2.660 dòng** mã chết.

**Giữ nguyên**
- `api/analytics/dashboard` và các API thống kê khác đang chạy.
- "Sức khoẻ dự án", OPPM.
- Bảng nhật ký dự án và các nơi đang dùng nó.
- Cách tính tiến độ thật (theo hạng mục).

**Test chặn mới:** không route nào được trỏ lại bộ điều khiển cũ, và các class / hàm đã gỡ không được quay lại.

**3. Đã kiểm thử** — base `7352fc46`; subject `118ba9a55dbcc7710e0754c4d348e39f5b537602`; digest
`260a5057df8a40abb1eceaa1248d36ff8b1c0440e3cb390b31b0c150089916f7`.
- Test chặn **đỏ** trước khi gỡ, **xanh** sau.
- Danh sách route chạy bình thường.
- 93 test liên quan xanh.
- PHPStan (toàn bộ), php-cs-fixer, SSOT lint, deptrac, governance lint sạch.

**4. Lưu ý vận hành**
- Không migration, không đổi dữ liệu, không đổi màn hình.
- Các API đã gỡ vốn luôn lỗi 500. Sau khi gỡ, gọi vào sẽ nhận 404 thay vì 500.

**5. Ngoài phạm vi**
- `AnalyticsController`.
- "Sức khoẻ dự án".
- `RealTimeController`.
- Cấu hình broadcasting.
- Tài liệu lịch sử.
- Deploy.

**6. Rủi ro còn lại** — Thấp.
- Chỉ gỡ mã đã chứng minh là không ai gọi hoặc luôn lỗi.
- Nếu có hệ thống bên ngoài từng gọi các API đó, trước đây nó đã luôn nhận lỗi 500.

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ**
- **Unit + Feature + Integration:** 3.015 test, chỉ còn 2 lỗi có sẵn do chạy bằng root (`GrandfatherLoaderTest`,
  `BackupStorageLocationTest`).
- **Feature/Api:** 825 test, tất cả xanh.
- **CI trên đúng head:** ghi nhận trước khi merge.

## Decision Needed

Owner chọn: `APPROVE GAP-119 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
