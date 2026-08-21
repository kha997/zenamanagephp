---
work_id: GAP-040
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
  spec: docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md
  plan: docs/superpowers/plans/2026-08-20-gap-040-testcase-mysql-transaction-isolation.md
  branch: feature/GAP-040-mysql-transaction-isolation
  pr: "https://github.com/kha997/zenamanagephp/pull/272"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-20T17:00:00+07:00"
  owner_response_reference: "Owner chat message, 2026-08-20: 'GAP-040 — GATE 3 OWNER DECISION: APPROVED. Owner approves release of GAP-040 based on the corrected Gate 3 evidence. Reviewed PR: #272; reviewed final head: 42e2628f6a3151148f5c0ce6dc40a38b140203b8; implementation evidence subject: f8f4d1102d40188eb71024c8eab834a9efbae88f; approved implementation-tree digest: c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18; technical readiness: ready; residual risk: low. The Owner accepts the corrected evidence that the GAP-040 acceptance contract is satisfied: real-MySQL RefreshDatabase transaction isolation is preserved from the first test of a fresh process onward; RBAC compatibility tables remain available for test execution; bootstrap DDL uses a distinct non-transacted MySQL session; and no production schema, migration, RBAC/authorization, or tenant-semantic change is part of GAP-040. The corrected rollback proof is specifically part of this approval. The previously rejected false-green harness is not an approved basis.' Owner also directed: Gate-3-decision-record-only commit; verify digest remains exactly c9425c97... and diff is only 03-release.md; verify all mandatory CI green on the exact new head before release; if digest changes or a mandatory gate turns red, STOP; if clean, mark PR ready, squash merge via repository-standard method, no bypass of branch protection/required checks, no additional implementation commit before merge; after merge observe (not infer) the real Production Deployment workflow and report actual execution/skip status; verify post-merge CI and deployment on the exact squash-merge commit before considering GAP-040 closed; GAP-041/GAP-042 (PR #270) remain separate, not fixed/closed by this release; after successful merge, close PR #269 and PR #271 as historical/superseded (not merged), pointing to canonical PR #272, and remove their stale branches only after proving no unique required content remains, plus remove the PR #272 feature branch after successful merge if normal housekeeping permits; do not touch PR #270."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-20T13:20:00+07:00"
  updated_at: "2026-08-20T17:00:00+07:00"
generated_by: agent
residual_risk_rating: low
mandatory_technical_gate_summary: "v2 — evidence refreshed after Owner Gate 3 CORRECTION REQUIRED (rollback-proof false-green defect found and fixed; see body §0). On refreshed head f8f4d1102d40188eb71024c8eab834a9efbae88f: 9 distinct workflow runs triggered for this head (some workflows fire twice — once on `push` to a `feature/*` branch, once on `pull_request` to main — which is why the flat gh-pr-checks context count differs from distinct-workflow-run count; both are reported explicitly in body §8, not blended). Workflow-run level: 8 SUCCESS + 1 FAILURE (Owner Governance Lint, on the stale-digest head prior to this refresh — expected to flip to SUCCESS once this record's digest matches). gh-pr-checks context level (job granularity, includes the push+pull_request duplication): 51 SUCCESS + 1 FAILURE (same Owner Governance Lint) + 1 SKIPPING (`deploy`, by design, not merged). Cold-start transaction-isolation invariant re-proven with a CORRECTED harness (writer-only cold-start forcing, PHPUnit #[Depends] for ordering/value-passing, no shared migrate:fresh between write and independent verification, no skip-based false-green paths) — RED confirmed on pre-fix code (b61abc2f) on 2 surfaces, GREEN confirmed on post-fix code on all 5 approved surfaces, each with direct PDO::inTransaction() server-truth + distinct bootstrap CONNECTION_ID() + independent-PDO rollback verification. SQLite/RBAC regression reconfirmed on this exact head via CI (not local — local environment has broken PHP extensions unrelated to this branch)."
technical_evidence:
  subject_sha: "f8f4d1102d40188eb71024c8eab834a9efbae88f"
  implementation_tree_digest: "c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18"
  verified_pr_head_sha: "f8f4d1102d40188eb71024c8eab834a9efbae88f"
  verified_at: "2026-08-20T16:00:00+07:00"
owner_decision_binding:
  implementation_tree_digest: "c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18"
  decision_recorded_at: "2026-08-20T17:00:00+07:00"
---

## OWNER GATE 3: APPROVED

Owner phê duyệt GAP-040 Gate 3 / release lúc `2026-08-20T17:00:00+07:00`, đã review head `42e2628f6a3151148f5c0ce6dc40a38b140203b8` của PR #272 (implementation evidence subject `f8f4d1102d40188eb71024c8eab834a9efbae88f`; implementation-tree digest đã phê duyệt `c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18`; technical readiness `ready`; residual risk `low`). Owner xác nhận contract nghiệm thu GAP-040 đã thoả: transaction isolation của `RefreshDatabase` trên MySQL thật được giữ nguyên kể từ test đầu tiên của tiến trình mới; bảng tương thích RBAC vẫn khả dụng cho test; DDL bootstrap dùng session MySQL riêng không nằm trong transaction; không có thay đổi schema/migration/RBAC-authorization/ngữ nghĩa tenant production nào thuộc GAP-040. Bằng chứng rollback đã sửa (sau khi v1 bị từ chối vì lỗi false-green) là cơ sở chính thức của phê duyệt này — harness cũ đã bị từ chối KHÔNG phải cơ sở được duyệt.

**Được phép:** merge PR #272 vào `main` theo đúng phương thức merge chuẩn của repo (squash), không bỏ qua branch protection hay required checks, không force merge, không thêm implementation commit nào giữa lúc phê duyệt và lúc merge. Sau merge: quan sát (không suy đoán) kết quả thật của Production Deployment workflow, xác minh CI/deploy trên đúng commit squash-merge trước khi coi GAP-040 đã đóng.

**Điều kiện bắt buộc trước khi merge:** implementation-tree digest sau khi ghi nhận quyết định này phải giữ nguyên `c9425c973300ef31310221c89bb942f7b1f3f07d9e45aaa501a86818af1dde18` (chỉ được đổi nếu commit khác biệt duy nhất là chính file `03-release.md` này); nếu digest đổi vì bất kỳ lý do nào khác, phê duyệt này KHÔNG còn bao phủ implementation đã thay đổi và phải dừng lại.

`GAP-041`/`GAP-042` (PR #270, đã ghi tại `OPERATIONAL_GAP_REGISTER.md`) tiếp tục là work item quản trị riêng, KHÔNG thuộc phạm vi đóng của GAP-040, không được sửa/đóng như hệ quả của việc merge này. 2 lỗi có sẵn không liên quan (E2E `CriticalUserFlowsE2ETest`, 1 Treasury FK test) cũng không trở thành phạm vi GAP-040 chỉ vì được phát hiện trong lúc xác minh.

## Owner Summary

`tests/TestCase.php::ensureSqliteZenaRbacTables()` từng chạy DDL vô điều kiện trên connection MySQL thật đã có transaction `RefreshDatabase` mở sẵn, và mỗi DDL đó âm thầm implicit-COMMIT transaction đang mở — đã **chứng minh trực tiếp bằng thực nghiệm** trên CI thật (không chỉ suy luận): `PDO::inTransaction()` đo được `true` trước bootstrap, `false` sau bootstrap, trên mã cũ chưa sửa. Bản vá đưa DDL này sang một session MySQL thứ hai, đăng ký lúc runtime trong `tests/TestCase.php` (không sửa `config/database.php` đã commit), không nằm trong danh sách connection bị `RefreshDatabase` transact — kết quả đo lại: `PDO::inTransaction()` giữ nguyên `true` xuyên suốt bootstrap, trên cả 5 bề mặt CI thật-MySQL đã duyệt ở Gate 1/Gate 2.

## §0 — Correction made in response to Owner Gate 3 review (v1 rejected)

Owner reviewed v1 (subject SHA `1082f3d3`) and found a real defect in the **rollback acceptance proof**, not in the Option C fix itself: every cold-start test class's `setUp()` unconditionally called `forceGenuineColdStartForNextSetUp()` (forcing `RefreshDatabaseState::$migrated = false`), including the verifier ("test B")'s own `setUp()`. That meant a fresh `migrate:fresh` ran between the writer test's teardown and the verifier's independent-connection read — wiping the marker row regardless of whether `RefreshDatabase`'s rollback actually worked. The proof could pass on fully broken code.

**Corrected** (all detail in `tests/Support/GAP040ColdStartTransactionIsolationAssertions.php` and the 5 consuming test classes, commits `d3c6637e` + `f8f4d110` on this branch):
- Only the writer test's `setUp()` forces cold start now, gated on `$this->name() === self::WRITER_TEST` (an explicit class constant, not a naming convention alone).
- Ordering and value-passing between writer and verifier now use PHPUnit's `#[Depends]` attribute — the verifier receives the written tenant id as a typed method parameter (PHPUnit guarantees the writer runs first and only calls the verifier if the writer didn't fail/error/skip), not a shared file-based marker.
- The file-based marker mechanism was removed entirely.
- Skip-based false-green paths removed from the mandatory proof: a cold-start test finding `zena_roles` already present is now a hard failure (`assertFalse`), not a skip — with deterministic forcing in place, that state means the forcing mechanism itself is broken, not a legitimate alternate outcome. The "not on a real MySQL connection" skip remains (legitimate: these files are also reachable from the default SQLite suite, no excluded `@group`).
- The verifier's row-count assertion requires its argument and is unconditional (no skip path at all).

**RED/GREEN re-established** (§3 below) on the corrected harness: FAILS on pre-fix code, PASSES on the Option C fix, on all 5 approved surfaces, with actual captured execution evidence per surface — not inferred from "shares a trait."

## Gói quyết định phát hành — GAP-040: TestCase MySQL transaction isolation

**1. Vấn đề đã xảy ra là gì?**
Xem `docs/audits/2026-08-20-gap-040-testcase-mysql-transaction-isolation-evidence.md` (Gate 1) và `docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md` (Gate 2). Tóm tắt: helper test dùng chung tạo lại 4 bảng tương thích RBAC (`zena_roles` v.v.) vô điều kiện mỗi `setUp()`, trên MySQL DDL đó implicit-commit transaction test đang dựa vào để cô lập dữ liệu.

**2. Đã sửa gì, đúng ranh giới đã duyệt chưa?**
Đúng contract Gate 2 đã duyệt: loại bỏ hoàn toàn (không phải giảm) DDL trên connection đã có transaction, kể cả test đầu tiên của tiến trình mới (cold start) — dùng Option C (session bootstrap riêng), không dùng Option B (existence-guard đơn thuần, biết trước sẽ còn sót 1 ca cold-start). Giữ nguyên các bảng tương thích RBAC trên mọi driver — không ai bị mất quyền truy cập bảng `zena_*`. Không sửa `RBACManager`, `Src\RBAC\Models\*`, migration nào, hay `config/database.php` đã commit. Không đụng GAP-041/GAP-042 (PR #270, độc lập, không phải phụ thuộc của release này).

**3. Bằng chứng hồi quy — RED/GREEN, hành vi thật, không chỉ hình dạng mã.**

RED (harness đã sửa, chạy trên mã CHƯA sửa — `tests/TestCase.php` revert về commit `b61abc2f`, có probe `PDO::inTransaction()` nhưng CHƯA có cơ chế session cô lập): xác nhận FAIL đúng lý do trên 2 bề mặt kiểm tra trực tiếp (PR throwaway #275, đã đóng sau khi ghi nhận bằng chứng, không merge):
- `routes-guardrails.yml`: `[GAP-040 probe] {"pdo_in_transaction_before_bootstrap":true,...,"pdo_in_transaction_after_bootstrap":false}` → `Failed asserting that false is true.` — test B tự động SKIPPED (hệ quả `#[Depends]` khi test A fail, không phải false-green).
- `ci-cd.yml`'s bước GAP-032: cùng pattern chính xác, `pdo_in_transaction_after_bootstrap: false`, `Failed asserting that false is true.`

GREEN (harness đã sửa, chạy trên mã ĐÃ sửa — head `f8f4d110`), bằng chứng thực thi riêng cho cả 5 bề mặt, không suy diễn từ "dùng chung trait":
- `routes-guardrails.yml` (`--group=mysql-parity`): `main_connection_id:17, bootstrap_connection_id:26, pdo_in_transaction_after_bootstrap:true` → `PASS ZenaTransactionIsolationColdStartTest`.
- `automated-testing.yml`'s `zena-invariants-mysql`: `main_connection_id:19, bootstrap_connection_id:24, pdo_in_transaction_after_bootstrap:true` → `PASS ZenaInvariantsTransactionIsolationColdStartTest`.
- `automated-testing.yml`'s `treasury-check-constraints-mysql`: `main_connection_id:20, bootstrap_connection_id:25, pdo_in_transaction_after_bootstrap:true` → PASS (1 lỗi FK có sẵn không liên quan trong `TreasuryWalletsSchemaTest`, xác minh giống hệt trên `origin/main` chưa sửa, script tự thiết kế "informational, not gating" từ trước GAP-040).
- `a11y-perf-testing.yml`'s `e2e-tests` (step cô lập riêng, `if: always()`, vì step gốc `--stop-on-failure` bị `CriticalUserFlowsE2ETest` — lỗi có sẵn không liên quan, xác minh giống hệt trên `origin/main` chưa sửa qua `workflow_dispatch` thủ công — chặn): `main_connection_id:18, bootstrap_connection_id:22, pdo_in_transaction_after_bootstrap:true` → 2 test PASS (10 assertions).
- `ci-cd.yml`'s bước chứng minh MySQL GAP-032: `main_connection_id:15, bootstrap_connection_id:70, pdo_in_transaction_after_bootstrap:true` → PASS, job "Tests: 19, Assertions: 103, ... Skipped: 1" (0 failures).

Mỗi bề mặt: connection ID session bootstrap khác connection ID chính (session thật sự tách biệt) + transaction chính vẫn mở xuyên suốt bootstrap + dòng dữ liệu ghi ở test A biến mất khi test B kiểm tra qua PDO độc lập hoàn toàn mới, KHÔNG có migrate:fresh/truncate/reset nào chạy giữa lúc A teardown và B đọc.

**4. Hồi quy SQLite và RBAC — xác nhận lại trên CI (không dùng lại số đo cục bộ).**
Môi trường local của agent có extension PHP hỏng không liên quan (Redis thiếu `publish()`) gây 7-9 lỗi cục bộ không tái lập được trên CI thật — không dùng số đo cục bộ làm bằng chứng chính. Trên CI thật, head `f8f4d110`: `Unit Tests`, `Feature Tests`, `Integration Tests`, `API Tests (Fast)`, `API Tests (Slow)` đều SUCCESS (0 lỗi) — đây mới là bằng chứng hồi quy SQLite chính thức. `ZenaAuthFlowInvariantTest` PASS trên cả `Feature Tests` (SQLite) và `Zena RBAC/Tenant Invariants (MySQL parity)` (MySQL thật), trên head này.

**5. Phạm vi loại trừ đã giữ đúng.**
`git diff origin/main...f8f4d110` xác nhận không đụng: `OPERATIONAL_GAP_REGISTER.md`, `config/database.php`, `app/Services/RBACManager.php`, `database/migrations/`, `src/RBAC/`.

**6. Rủi ro tồn dư.**
`low`. Cơ chế hoàn chỉnh (Option C) đã chọn và chứng minh trên cả 5 bề mặt bằng harness đã sửa đúng (không còn lỗ hổng false-green). Rủi ro còn lại: (a) cơ chế session-runtime-registration + `#[Depends]` là engineering mới trong repo — đã chứng minh hoạt động đúng bằng RED/GREEN thực nghiệm, không chỉ suy luận; (b) 2 lỗi có sẵn không liên quan (E2E, Treasury FK) vẫn tồn tại — đã xác minh không phải do thay đổi này, ngoài phạm vi GAP-040.

**7. Final whole-branch review (yêu cầu tại điểm 7 của Owner) — tự review đối kháng.**
Tự đặt câu hỏi: bằng cách nào proof đã sửa có thể pass mà rollback thật không xảy ra?
- `#[Depends]` không tôn trọng thứ tự? — Không: PHPUnit luôn build lại thứ tự thực thi theo đồ thị phụ thuộc, không phụ thuộc thứ tự khai báo hay `--order-by`; đã xác nhận không có `--order-by=random`/`resolveDependencies` ở bất kỳ 5 lệnh CI nào.
- Test B chạy dù A không thật sự chạy? — Không: cùng class, cùng `@group`; nếu A fail/error, PHPUnit tự SKIP B (đã quan sát trực tiếp trong RED demo).
- `$this->name()` check sai tên method, khiến A không force cold-start mà không ai biết? — Nếu sai, A sẽ tìm thấy `zena_roles` đã tồn tại và FAIL cứng (không skip) — lỗi lộ ra ngay, không có false-green.
- PDO độc lập đọc snapshot cũ (stale read) thay vì trạng thái thật? — Không: connection hoàn toàn mới, autocommit, không có transaction nào đang mở trước đó nên không có snapshot cũ để đọc.
- Race condition giữa A teardown và B đọc? — Không: `rollBack()` của `RefreshDatabase` chạy đồng bộ, chặn cho tới khi MySQL xác nhận, trước khi A's tearDown() trả về; PHPUnit thực thi B's setUp() tuần tự sau đó trong cùng tiến trình.
Không tìm được đường nào proof pass mà rollback không thật xảy ra, trong phạm vi review này.

**8. CI — phân loại chính xác theo yêu cầu Owner (không trộn nhiều "vũ trụ" kiểm tra).**
Trên head `f8f4d110`, 9 workflow run riêng biệt được kích hoạt (2 workflow tự kích hoạt 2 lần — 1 lần từ `push` lên nhánh `feature/*`, 1 lần từ `pull_request` lên `main` — đây là nguyên nhân số lượng context ở mức job khác số lượng workflow run):
- **Mức workflow run** (9 run): 8 SUCCESS, 1 FAILURE (`Owner Governance Lint` — do digest cũ trong bản v1 của chính file này, dự kiến chuyển SUCCESS ngay khi commit ghi nhận digest mới này được đẩy lên).
- **Mức context `gh pr checks` (job)**: 53 context — 51 SUCCESS, 1 FAILURE (cùng `Owner Governance Lint`), 1 SKIPPING (`deploy`, đúng thiết kế, không merge).
- Không trộn: không tính lại các lần rerun trùng, không tính run `workflow_dispatch` thủ công (RED demo PR throwaway, a11y-perf-testing.yml E2E xác minh) vào tổng này — các run đó được trích dẫn riêng, có link riêng, ở mục 3.

**9. Đề xuất.**
Đội kỹ thuật đề xuất Owner phê duyệt Gate 3 / release cho GAP-040, cho phép merge PR #272 vào `main` theo đúng phương thức merge chuẩn của repo (không bỏ qua branch protection hay required checks), SAU KHI xác nhận commit ghi nhận digest mới này đã làm `Owner Governance Lint` chuyển SUCCESS.

## What the owner is NOT being asked to decide

Owner không được yêu cầu quyết định về GAP-041/GAP-042 (PR #270, work item riêng, vòng đời governance riêng) hay 2 lỗi có sẵn không liên quan đã ghi nhận (E2E, Treasury FK) — cả hai đã xác minh không phải do thay đổi này và không nằm trong phạm vi GAP-040.
