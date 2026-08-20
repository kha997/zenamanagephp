---
work_id: GAP-040
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
  spec: docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md
  plan: docs/superpowers/plans/2026-08-20-gap-040-testcase-mysql-transaction-isolation.md
  branch: feature/GAP-040-mysql-transaction-isolation
  pr: "https://github.com/kha997/zenamanagephp/pull/272"
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
  created_at: "2026-08-20T13:20:00+07:00"
  updated_at: "2026-08-20T13:20:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "52 CI check bắt buộc SUCCESS + đúng 1 job deploy SKIPPED (đúng thiết kế, không merge) trên head bằng chứng 1082f3d3edb010100490b7517b917a6398c6809d. Cold-start transaction-isolation invariant được chứng minh thực nghiệm, trực tiếp qua PDO::inTransaction() (server-truth, không phải bộ đếm in-process của Laravel) trên cả 5 bề mặt CI thật-MySQL đã duyệt ở Gate 1/2: routes-guardrails.yml mysql-parity, zena-invariants-mysql, treasury-check-constraints-mysql, e2e-tests (bước cô lập riêng do 1 lỗi E2E có sẵn không liên quan chặn --stop-on-failure), và bước chứng minh MySQL GAP-032 trong ci-cd.yml. Bộ test SQLite hồi quy đầy đủ (2308 test, 0 lỗi) chạy cục bộ, không đổi so với trước. RBAC compat (ZenaAuthFlowInvariantTest) xác nhận còn nguyên vẹn cả 2 driver. 2 lỗi có sẵn không liên quan (E2E CriticalUserFlowsE2ETest, 1 Treasury FK test) xác minh giống hệt trên origin/main chưa sửa — không do GAP-040 gây ra, không sửa ở đây. Không hấp thụ GAP-041/GAP-042 (register không đổi, diff xác nhận bằng git diff)."
technical_evidence:
  subject_sha: "1082f3d3edb010100490b7517b917a6398c6809d"
  implementation_tree_digest: "4f8e3168983a4926f99f14d724b52101c8fe1af45217cefcfa841de439bf82f3"
  verified_pr_head_sha: "1082f3d3edb010100490b7517b917a6398c6809d"
  verified_at: "2026-08-20T13:10:00+07:00"
owner_decision_binding:
  implementation_tree_digest: null
  decision_recorded_at: null
---

## Owner Summary

`tests/TestCase.php::ensureSqliteZenaRbacTables()` từng chạy DDL vô điều kiện trên connection MySQL thật đã có transaction `RefreshDatabase` mở sẵn, và mỗi DDL đó âm thầm implicit-COMMIT transaction đang mở — đã **chứng minh trực tiếp bằng thực nghiệm** trên CI thật (không chỉ suy luận): `PDO::inTransaction()` đo được `true` trước bootstrap, `false` sau bootstrap, trên mã cũ chưa sửa. Bản vá đưa DDL này sang một session MySQL thứ hai, đăng ký lúc runtime trong `tests/TestCase.php` (không sửa `config/database.php` đã commit), không nằm trong danh sách connection bị `RefreshDatabase` transact — kết quả đo lại: `PDO::inTransaction()` giữ nguyên `true` xuyên suốt bootstrap, trên cả 5 bề mặt CI thật-MySQL đã duyệt ở Gate 1/Gate 2, mỗi bề mặt đều có bằng chứng riêng (connection ID của session bootstrap khác connection ID chính, viết dữ liệu ở test A biến mất khi test B kiểm tra qua connection độc lập).

## Gói quyết định phát hành — GAP-040: TestCase MySQL transaction isolation

**1. Vấn đề đã xảy ra là gì?**
Xem `docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md` (Gate 1) và `docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md` (Gate 2). Tóm tắt: helper test dùng chung tạo lại 4 bảng tương thích RBAC (`zena_roles` v.v.) vô điều kiện mỗi `setUp()`, trên MySQL DDL đó implicit-commit transaction test đang dựa vào để cô lập dữ liệu.

**2. Đã sửa gì, đúng ranh giới đã duyệt chưa?**
Đúng contract Gate 2 đã duyệt: loại bỏ hoàn toàn (không phải giảm) DDL trên connection đã có transaction, kể cả test đầu tiên của tiến trình mới (cold start) — dùng Option C (session bootstrap riêng), không dùng Option B (existence-guard đơn thuần, biết trước sẽ còn sót 1 ca cold-start). Giữ nguyên các bảng tương thích RBAC trên mọi driver — không ai bị mất quyền truy cập bảng `zena_*`. Không sửa `RBACManager`, `Src\RBAC\Models\*`, migration nào, hay `config/database.php` đã commit. Không đụng GAP-041/GAP-042 (PR #270, độc lập, không phải phụ thuộc của release này).

**3. Bằng chứng hồi quy — chứng minh hành vi thật, không chỉ hình dạng mã.**
Mỗi bề mặt trong 5 bề mặt đã duyệt có cùng chuỗi bằng chứng, đo trực tiếp qua `PDO::inTransaction()` (server-truth) + `CONNECTION_ID()` (xác nhận session bootstrap thật sự tách biệt) + ghi một dòng dữ liệu rồi xác minh độc lập qua connection PDO mới hoàn toàn rằng dòng đó đã biến mất sau rollback:
- `routes-guardrails.yml` (`--group=mysql-parity`): xác nhận.
- `automated-testing.yml`'s `zena-invariants-mysql`: xác nhận.
- `automated-testing.yml`'s `treasury-check-constraints-mysql`: xác nhận (1 lỗi FK có sẵn không liên quan trong `TreasuryWalletsSchemaTest`, đã xác minh giống hệt trên `origin/main` chưa sửa, không do GAP-040, không sửa ở đây — script tự thiết kế "informational, not gating" từ trước).
- `a11y-perf-testing.yml`'s `e2e-tests`: xác nhận, qua một step CI cô lập riêng (`if: always()`) — step gốc `--stop-on-failure` bị 1 lỗi có sẵn không liên quan (`CriticalUserFlowsE2ETest`) chặn, xác minh giống hệt trên `origin/main` chưa sửa qua workflow_dispatch thủ công.
- `ci-cd.yml`'s bước chứng minh MySQL GAP-032: xác nhận, qua 1 dòng CI invocation thêm file test vào lệnh `phpunit` sẵn có.

**4. Hồi quy SQLite và RBAC.**
Bộ test SQLite đầy đủ (`--testsuite=Unit,Feature,Integration`) chạy cục bộ: 2308 test, 0 lỗi, 42 skip (đúng như thiết kế — bao gồm các test cold-start mới tự skip khi không phải MySQL). `ZenaAuthFlowInvariantTest` (test RBAC thật duy nhất đã xác nhận phụ thuộc bảng compat) PASS trên cả đường SQLite lẫn MySQL-parity.

**5. Phạm vi loại trừ đã giữ đúng.**
`git diff origin/main...1082f3d3` xác nhận không đụng: `OPERATIONAL_GAP_REGISTER.md`, `config/database.php`, `app/Services/RBACManager.php`, `database/migrations/`, `src/RBAC/`. Diff đầy đủ: 15 file, toàn bộ nằm trong `tests/TestCase.php`, test mới, doc GAP-040, plan, và 1 dòng CI invocation ở `ci-cd.yml` + 1 step CI mới cô lập ở `a11y-perf-testing.yml`.

**6. Rủi ro tồn dư.**
`low`. Cơ chế hoàn chỉnh (Option C) đã được chọn và chứng minh trên cả 5 bề mặt, không phải Option B với ca cold-start còn sót đã biết trước. Rủi ro còn lại: (a) cơ chế session-runtime-registration là engineering mới trong repo — đã chứng minh hoạt động đúng bằng thực nghiệm trên toàn bộ 5 bề mặt CI thật, không chỉ 1; (b) 2 lỗi có sẵn không liên quan (E2E, Treasury FK) vẫn tồn tại — đã xác minh không phải do thay đổi này, ngoài phạm vi GAP-040.

**7. Đề xuất.**
Đội kỹ thuật đề xuất Owner phê duyệt Gate 3 / release cho GAP-040, cho phép merge PR #272 vào `main` theo đúng phương thức merge chuẩn của repo (không bỏ qua branch protection hay required checks).

## What the owner is NOT being asked to decide

Owner không được yêu cầu quyết định về GAP-041/GAP-042 (PR #270, work item riêng, vòng đời governance riêng) hay 2 lỗi có sẵn không liên quan đã ghi nhận (E2E, Treasury FK) — cả hai đã xác minh không phải do thay đổi này và không nằm trong phạm vi GAP-040.
