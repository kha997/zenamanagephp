---
work_id: GAP-116
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
  spec: docs/audits/2026-11-09-gap-116-project-factory-dates-evidence.md
  plan: docs/superpowers/plans/2026-11-09-gap-116-project-factory-dates.md
  branch: docs/GAP-116-project-factory-dates
  pr: https://github.com/kha997/zenamanagephp/pull/406
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: null
  owner_response_reference: null
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-11-10T00:30:00+07:00"
  updated_at: "2026-11-10T00:30:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "GAP-116 at subject f1e43f9007fac1b30e3bc472d83d1012d0b303f8 (Gate 2 F-1, G-1; exact allowlist; one PR, one implementation commit; test-support code only, no runtime change, no migration). F-1: database/factories/ProjectFactory.php default start_date -1 month..+3 weeks and end_date +5 weeks..+6 months (two weeks apart, previously both met at +1 month); completed() and overdue() now also set start_date -6..-2 months so their past end ranges stay after the start. G-1: tests/Unit/Factories/ProjectFactoryDatesTest.php — a recording Faker bound in the container captures the dateTimeBetween ranges per state (default, completed, overdue) and asserts the latest possible start is on an earlier calendar day than the earliest possible end, overdue end before today (deterministic); plus 200 make()-built projects per state with end day after start day. Red before (default: latest start 2026-12-09 = earliest end 2026-12-09; completed / overdue samples with end before start), green after, and red again with the factory change stashed. ProjectApiTest test_can_update_project passed 50 consecutive runs. php-cs-fixer, SSOT test lint, ssot:lint pass. Full-suite and exact-head CI results are recorded in the body."
technical_evidence:
  base_sha: "9a23ac6abea7a27f8890bc3fbcfa6407b4117813"
  subject_sha: "f1e43f9007fac1b30e3bc472d83d1012d0b303f8"
  implementation_tree_digest: "068fda261fe7d63984375b0f95e941d04490cf9aa66d8f56c4120a9b8d8fd462"
  verified_pr_head_sha: "f1e43f9007fac1b30e3bc472d83d1012d0b303f8"
  verified_at: "2026-11-10T00:30:00+07:00"
owner_decision_binding: null
---

# GAP-116 — Gate 3 release decision (flaky ProjectApiTest, project factory dates)

## Gói quyết định phát hành

**1. Vấn đề là gì?** Test `ProjectApiTest` "sửa dự án" thỉnh thoảng đỏ trên CI, vì dữ liệu mẫu có ngày bắt đầu trùng ngày
kết thúc.

**2. Sau thay đổi (Gate 2 F-1, G-1)** — subject `f1e43f90`:
- Dữ liệu mẫu dự án:
  - **mặc định:** bắt đầu từ 1 tháng trước đến 3 tuần sau; kết thúc từ 5 tuần đến 6 tháng sau. Hai khoảng cách nhau 2 tuần
    nên không bao giờ trùng;
  - **"đã xong" / "quá hạn":** bắt đầu từ 6 đến 2 tháng trước, nên luôn trước ngày kết thúc trong quá khứ.
- Test canh mới:
  - kiểm tra theo cấu trúc: ngày bắt đầu muộn nhất luôn trước ngày kết thúc sớm nhất, cho từng kiểu. Kết quả cố định,
    không phụ thuộc may rủi;
  - thêm 200 dự án mẫu cho mỗi kiểu.

Chỉ đổi dữ liệu mẫu cho test, **không đổi phần mềm chạy thật**.

**3. Đã kiểm thử** — base `9a23ac6a`; subject `f1e43f9007fac1b30e3bc472d83d1012d0b303f8`; digest
`068fda261fe7d63984375b0f95e941d04490cf9aa66d8f56c4120a9b8d8fd462`.
- Test canh **đỏ** trên dữ liệu mẫu cũ:
  - mặc định: ngày bắt đầu muộn nhất 09/12 trùng ngày kết thúc sớm nhất 09/12;
  - kiểu "đã xong" / "quá hạn" có dự án kết thúc trước khi bắt đầu.
- Test canh **xanh** sau khi sửa. Cất phần sửa đi thì đỏ lại.
- `ProjectApiTest` "sửa dự án" chạy **50 lần liên tiếp** đều xanh.
- php-cs-fixer, SSOT lint sạch.
- Unit + Feature + Integration, Feature/Api, CI trên đúng head: xem mục 9.

**4. Lưu ý vận hành** — Không có. Chỉ ảnh hưởng test.

**5. Ngoài phạm vi** — quy tắc kiểm tra ngày, controller, test API, factory khác.

**6. Rủi ro còn lại** — Rất thấp. Test nào lỡ dựa vào ngày bắt đầu nằm ở tương lai xa hơn 3 tuần sẽ lộ ra khi chạy toàn bộ
(xem mục 9).

**7. Hoàn tác** — Revert PR.

**8. Đề xuất** — Phát hành (merge).

**9. Kết quả chạy toàn bộ** — RESULTS_PENDING

## Decision Needed

Owner chọn: `APPROVE GAP-116 Gate 3` / Correction / Defer.

## What the owner is NOT being asked to decide

Deploy, phát hành production.
