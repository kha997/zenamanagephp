---
work_id: GAP-041
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
  spec: docs/superpowers/specs/2026-08-21-gap-041-ci-test-selection-truthfulness-design.md
  plan: docs/superpowers/plans/2026-09-15-gap-041-ci-test-selection-truthfulness-implementation.md
  branch: fix/GAP-041-ci-test-selection-truthfulness
  pr: "https://github.com/kha997/zenamanagephp/pull/316"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-10-05T23:26:07+07:00"
  owner_response_reference: "Owner Gate-3 decision in-session on 2026-10-05: 'APPROVE GAP-041 Gate 3'. Given after the packet was presented at PR head cf712f89724924db8e40bf1074075c34532e2e2f with 33/33 exact-head checks green; bound to implementation subject a9e7fe7e8110aac8801030628a58ea1f6ca5b81a and implementation-tree digest 1859db39134df36e004710dbca1235228d3cd37b881a1e59b363cead3d6bd278 (recomputed at recording time, zero drift). Merge is covered by the Owner's standing in-session instruction of 2026-09-28; no deployment authorized."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-09-15T08:13:59+07:00"
  updated_at: "2026-10-05T23:26:07+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "Option D at subject a9e7fe7e (main 49c84e37 merged in): performance-tests runs --group=performance --fail-on-empty-test-suite after the GAP-039 MySQL preflight; exact-head run 37331118265: Monitoring 10 passed/45 assertions, Dashboard 19 passed/161 assertions, all 33 PR checks green; fresh disposable zero-selection proof v4 on this exact tree (run 37335745972) printed 'No tests found' and exited 1 on both legs; both former blockers resolved by separately governed releases (GAP-053 role 403, GAP-045 timing); canonical digest computed at subject."
technical_evidence:
  base_sha: "49c84e3705d5de03c481204e1917e9b1307ce10f"
  subject_sha: "a9e7fe7e8110aac8801030628a58ea1f6ca5b81a"
  implementation_tree_digest: "1859db39134df36e004710dbca1235228d3cd37b881a1e59b363cead3d6bd278"
  verified_pr_head_sha: "a9e7fe7e8110aac8801030628a58ea1f6ca5b81a"
  verified_at: "2026-10-05T22:52:15+07:00"
owner_decision_binding:
  implementation_tree_digest: "1859db39134df36e004710dbca1235228d3cd37b881a1e59b363cead3d6bd278"
  decision_recorded_at: "2026-10-05T23:26:07+07:00"
---

# GAP-041 — Gate 3 release decision packet

## OWNER GATE 3: APPROVED

Owner approved Gate 3 in-session on 2026-10-05, bound to implementation subject
`a9e7fe7e8110aac8801030628a58ea1f6ca5b81a` and implementation-tree digest `1859db39134df36e004710dbca1235228d3cd37b881a1e59b363cead3d6bd278`. No deployment is authorized.

## Gói quyết định phát hành

**1. Vấn đề là gì?** Job `performance-tests` của CI (MySQL thật) chạy
`php artisan test <file>` nhưng `phpunit.xml` loại nhóm `performance` mặc
định, nên **chạy 0 test mà vẫn báo xanh** (bằng chứng: lịch chạy main
`36834855284`, 2026-10-01: `INFO  No tests found.`). Gate 1 (PR #276) và
Gate 2 v3 Phương án D (PR #277) đã được duyệt.

**2. Sau thay đổi:**

- `.github/workflows/automated-testing.yml`: lệnh thành
  `php artisan test "${{ matrix.perf_file }}" --group=performance --fail-on-empty-test-suite`
  — chọn đúng test hiệu năng, và **đỏ nếu chọn được 0 test**. Giữ preflight
  MySQL GAP-039 và ma trận 2 file.
- `tests/bootstrap.php`: chỉ sửa chú thích danh sách nơi gọi.
- Sổ `OPERATIONAL_GAP_REGISTER.md` dòng GAP-041; tài liệu Gate 1/2, spec, plan.

**3. Khác biệt so với Gate 2**

- Phần "gỡ các tier ảo" của Phương án D trong `a11y-perf-testing.yml` đã lên
  main qua GAP-060 (PR #329); sau khi merge main, diff của GAP-041 không còn
  đụng workflow đó.
- Hai lỗi lộ ra khi test bắt đầu chạy thật được xử lý bằng việc riêng có quản
  trị, không nằm trong GAP-041: role-based 403 → GAP-053 (PR #317); thời gian
  phụ thuộc máy CI → GAP-045 (PR #332, `49c84e37`).

**4. Bằng chứng kỹ thuật**

- Base main `49c84e37`; subject `a9e7fe7e8110aac8801030628a58ea1f6ca5b81a`; digest `1859db39134df36e004710dbca1235228d3cd37b881a1e59b363cead3d6bd278`.
- **Chạy thật trên đúng head** (run `37331118265`): Monitoring
  (job `111834164313`) 10 passed / 45 assertions; Dashboard
  (job `111834164472`) 19 passed / 161 assertions (alerts median 327.77ms,
  mark-100 746.69ms, trong ngân sách). Cả hai sau preflight MySQL
  `127.0.0.1:3306/zenamanage_test` thành công. 33/33 check PR xanh.
- **Proof chọn 0 test, v4** (nhánh dùng một lần
  `proof/GAP-041-zero-selection-live-v4`, commit `7340ee79` = subject chỉ đổi
  group sang tên không tồn tại; `workflow_dispatch` run `37335745972`): job
  `111849964950` (Dashboard) và `111849964994` (Monitoring) — preflight MySQL
  OK, `INFO  No tests found.`, **exit 1**. Nhánh đã xoá, không phải tổ tiên
  của PR.
- Lịch sử: RED gốc (`No tests found`, exit 0); proof v2 (run `34928883638`)
  và v3 (run `36869114743`) cùng kết quả exit 1; cơ chế
  `--fail-on-empty-test-suite` của PHPUnit 11.5.56 được Laravel 12.63 /
  Collision 8.9.4 chuyển tiếp.

**5. Ngoài phạm vi** — Sửa test/ứng dụng; ngưỡng hiệu năng; a11y/Lighthouse/E2E
(đã xử lý ở GAP-060/061); deploy. Đóng PR #276/#277 (đã bị #316 thay thế) sau
khi merge.

**6. Rủi ro còn lại** — Thấp. CI giờ chạy thật 29 test hiệu năng trên MySQL;
nếu chúng hỏng, build sẽ đỏ (đúng mục đích). Thời gian vượt ngân sách ở hai
test GAP-045 chỉ là cảnh báo vàng.

**7. Hoàn tác** — Revert squash commit.

**8. Đề xuất** — Duyệt phát hành tại subject/digest trên.

## Decision Needed

Owner chọn: Approve / Request correction / Defer.

## What the owner is NOT being asked to decide

Không duyệt deploy, thay đổi ngưỡng hiệu năng, hay sửa test/ứng dụng.
