---
work_id: GAP-040
gate: 2
gate_status: approved
owner_decision:
  value: approved
  authority: human_owner
decision_requested: null
references:
  spec: docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md
  plan: null
  branch: docs/GAP-040-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/271"
  release: null
decision_provenance:
  trust_level: claimed_repo_record
  recorded_by: agent
  recorded_at: "2026-08-20T16:00:00+07:00"
  owner_response_reference: "Owner chat message, 2026-08-20: 'GAP-040 — GATE 2 OWNER DECISION: APPROVE... PR: #271, reviewed head a98dee41b429650fdc084d97b4295a24681a9ddf... Gate 1 approved record: PR #269, head 78602d29ce66e63f782be49f98b493ba53c91fff... The approved acceptance contract is: GAP-040 is complete only when real-MySQL RefreshDatabase tests preserve transaction isolation from the first test of a fresh process onward, while retaining the required zena_* RBAC compatibility tables for test execution, without changing production schema, RBAC/authorization behavior, migrations, or tenant semantics. Option C, or a technically equivalent complete solution, is the required target. Option B may be used only as an implementation experiment/stepping stone. It is not an acceptable Gate-3-complete result. If no complete solution can be implemented safely within the approved boundary, STOP and return to Owner. Do not silently downgrade the acceptance contract.' Owner also directed: consolidate the unchanged-in-substance Gate 1 material from PR #269 into the implementation branch/PR chain before planning/code, rerun governance lint on the complete consolidated changed-file set; write the implementation plan under docs/superpowers/plans/2026-08-20-gap-040-testcase-mysql-transaction-isolation.md via the Superpowers writing-plans workflow, covering the cold-start harness, all 5 approved real-MySQL surfaces, SQLite preservation, RBAC compat preservation, preferred runtime-only secondary connection with a config fallback authorized only if shown infeasible; implementation surface excludes production migrations/RBACManager/Src\\RBAC\\Models/production authorization/tenant semantics/GAP-041/GAP-042 — any of those triggers STOP + Design Dependency Preflight; cold-start proof is a hard release condition through all 5 surfaces; GAP-041/GAP-042 (PR #270) remain independent, not fixed under GAP-040, not a dependency; proceed with subagent-driven-development + TDD after plan self-review, with defined STOP triggers; Gate 3 only after complete cold-start isolation is empirically demonstrated on all 5 surfaces, SQLite clean, RBAC compat intact, no GAP-041/042 absorbed, exact-head CI green, technical readiness ready — then set awaiting_owner and stop for release decision; no merge/release/deploy authorized by Gate 2."
  reconciliation_required: false
supersedes: null
superseded_by: null
timestamps:
  created_at: "2026-08-20T00:00:00+07:00"
  updated_at: "2026-08-20T16:00:00+07:00"
generated_by: agent
---

## OWNER GATE 2: APPROVED

Owner phê duyệt GAP-040 Gate 2 lúc `2026-08-20T16:00:00+07:00`, đã review head `a98dee41b429650fdc084d97b4295a24681a9ddf` của PR #271 (Gate 1 approved record: PR #269, head `78602d29ce66e63f782be49f98b493ba53c91fff`). Hợp đồng nghiệm thu được duyệt: GAP-040 chỉ hoàn tất khi test `RefreshDatabase` trên MySQL thật giữ được transaction isolation kể từ test ĐẦU TIÊN của tiến trình mới trở đi, đồng thời giữ nguyên các bảng tương thích RBAC `zena_*` cần thiết để test chạy được, không đổi schema/hành vi RBAC-authorization/migration/ngữ nghĩa tenant production. Option C (hoặc giải pháp hoàn chỉnh tương đương) là mục tiêu bắt buộc; Option B chỉ được dùng như bước đệm/thử nghiệm implementation, KHÔNG được là kết quả hoàn tất Gate 3. Nếu không thể triển khai an toàn một giải pháp hoàn chỉnh trong ranh giới đã duyệt, đội kỹ thuật phải DỪNG và quay lại Owner — không được âm thầm hạ hợp đồng nghiệm thu.

**Được phép:** hợp nhất bằng chứng/lịch sử Gate 1 (PR #269) vào chuỗi branch/PR implementation, lập implementation plan (`docs/superpowers/plans/2026-08-20-gap-040-testcase-mysql-transaction-isolation.md`), và tiến hành implementation/testing/technical review theo đúng ranh giới đã duyệt.

**KHÔNG được phép:** sửa migration production, `RBACManager`, `Src\RBAC\Models\*`, hành vi authorization production, ngữ nghĩa tenant chính tắc, hay bất kỳ phần nào của GAP-041/GAP-042. Nếu implementation cần bất kỳ thay đổi bị cấm nào ở trên, phải DỪNG và chạy Design Dependency Preflight tương ứng trước khi tiếp tục. Release/merge/deploy KHÔNG được phép. Gate 3 CHƯA ĐƯỢC PHÊ DUYỆT và chỉ chuyển về `awaiting_owner` sau khi cold-start isolation được chứng minh thực nghiệm trên cả 5 bề mặt, bộ test SQLite vẫn xanh, hành vi tương thích RBAC còn nguyên vẹn, không có phần nào của GAP-041/GAP-042 bị hấp thụ vào, và CI đúng head yêu cầu đã xanh.

Gate 2 tiếp nối từ Gate 1 đã được Owner APPROVE tại PR #269 (lịch sử/bằng chứng Gate 1 vẫn giữ nguyên ở đó, head `78602d29ce66e63f782be49f98b493ba53c91fff`). Gói này được đặt trong một Draft PR riêng (PR #271), cắt từ `origin/main` sạch (`87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`), theo đúng tiền lệ GAP-039 — không trộn lịch sử/bằng chứng Gate 1 với gói Gate 2. Theo chỉ đạo Owner, lịch sử/bằng chứng Gate 1 sẽ được hợp nhất vào chuỗi implementation trước khi lập plan/code (xem bước tiếp theo, không thuộc phạm vi của chính commit ghi nhận quyết định này).

## Owner Summary

`tests/TestCase.php::ensureSqliteZenaRbacTables()` cần **loại bỏ hoàn toàn** việc chạy DDL trên connection đã có transaction MySQL thật mở sẵn của `RefreshDatabase`, kể cả ở test đầu tiên của một tiến trình mới, trên cả 5 bề mặt CI thật-MySQL đã xác nhận ở Gate 1 — đồng thời **không được phá vỡ vai trò tương thích mà các bảng này đang gánh cho mã ứng dụng thật đang chạy** (phát hiện mới, xem bên dưới), và không đổi schema/hành vi RBAC/migration/ngữ nghĩa tenant production.

## Phát hiện mới quan trọng (§1 của engineering spec)

Đánh giá Gate 1 trước đó giả định `zena_roles`/`zena_permissions` chỉ là "di sản trước-đổi-tên, chỉ phục vụ test". **Điều này KHÔNG đúng trên baseline hiện tại.** `src/RBAC/Models/Role.php`/`Permission.php` (bảng `zena_roles`/`zena_permissions`) là model thật, được `app/Services/RBACManager.php` dùng — service này chống lưng cho middleware `rbac:` áp dụng trên hàng chục controller thật, và `Src\RBAC\Providers\RBACServiceProvider` có đăng ký thật trong `config/app.php`. Một bản vá "chỉ bỏ qua DDL trên driver MySQL" (giống hệt cách hàm chị em `ensureSqliteDocumentsBackupTable()` làm) sẽ **xoá mất `zena_roles`/`zena_permissions` khỏi mọi lần chạy test MySQL-parity** — ít nhất 1 test đã xác nhận phụ thuộc trực tiếp vào lớp tương thích này (`ZenaAuthFlowInvariantTest`, nằm trong nhóm `zena-invariants-mysql`). Phát hiện này không tự nó kích hoạt Design Dependency Preflight (không phương án nào dưới đây sửa `RBACManager`, model, hay migration nào), nhưng nó loại bỏ hẳn phương án "chỉ copy y hệt guard driver của hàm chị em" khỏi danh sách an toàn.

## Các phương án đã xem xét

- **A — chỉ guard theo driver (copy y hệt hàm chị em):** BỊ LOẠI — xoá phụ thuộc RBAC thật trên đường MySQL, đổi một lỗi âm thầm lấy một lỗi test hỏng ngay lập tức.
- **B — chỉ guard theo existence (copy y hệt hàm chị em còn lại):** an toàn về mặt RBAC, nhưng **KHÔNG còn là kết quả hoàn tất được chấp nhận cho GAP-040** — vẫn còn sót đúng 1 trường hợp phơi nhiễm (test đầu tiên mỗi tiến trình mới), trái với invariant đã duyệt (không DDL nào được chạy trên connection có transaction, kể cả lần đầu). Chỉ còn vai trò là bước đệm/thử nghiệm kỹ thuật trong lúc implementation — không được phép là kết quả release của GAP-040.
- **C — guard existence + bootstrap DDL trên một connection MySQL riêng, đăng ký lúc runtime trong `tests/TestCase.php` (KHÔNG sửa file `config/database.php` đã commit, trừ khi cách này bất khả thi):** đóng hoàn toàn khoảng hở còn sót của B. Đây là hướng mục tiêu bắt buộc.
- **D — đưa việc tạo bảng compat vào chính migration (áp dụng cả production):** KHÔNG đề xuất cho GAP-040 — đây là thay đổi schema production. Câu hỏi liên quan (RBAC có thật sự hoạt động đúng trên production hay không) đã tách thành **GAP-042** (xem bên dưới).

## Đề xuất — hợp đồng nghiệm thu đã sửa theo yêu cầu Owner

Đội kỹ thuật đề xuất Owner phê duyệt đúng hợp đồng sau:

> GAP-040 chỉ hoàn tất khi các test `RefreshDatabase` chạy trên MySQL thật giữ được transaction isolation kể từ test ĐẦU TIÊN của một tiến trình mới trở đi, đồng thời vẫn giữ nguyên các bảng tương thích RBAC `zena_*` cần thiết để test chạy được, không đổi schema hay hành vi authorization production. Đội kỹ thuật được chọn cơ chế hoàn chỉnh cụ thể lúc implementation, nhưng KHÔNG được release một trường hợp implicit-COMMIT còn sót đã biết trước mà không quay lại xin quyết định Owner.

Cụ thể: **Option C (hoặc một giải pháp hoàn chỉnh tương đương) là mục tiêu bắt buộc, KHÔNG phải Option B.** Nếu lúc implementation phát hiện Option C bất khả thi hoặc rủi ro không cân xứng, đội kỹ thuật phải DỪNG và quay lại Owner với bằng chứng cùng đề xuất thu hẹp phạm vi/rủi ro — không được âm thầm hạ xuống Option B rồi báo cáo GAP-040 đã hoàn tất.

Bằng chứng hồi quy bắt buộc (không chấp nhận test chỉ kiểm tra "có `if` trong mã nguồn", và không chấp nhận một cặp test đã chạy sau khi bảng compat được tạo sẵn — phải là bằng chứng cold-start):
1. **Bằng chứng cold-start/tiến trình mới (bắt buộc):** phải chứng minh được như một chuỗi sự kiện quan sát được từ server, không chỉ suy luận từ thứ tự chạy — (a) tiến trình mới, MySQL thật; (b) bảng compat ban đầu KHÔNG tồn tại (assert `Schema::hasTable('zena_roles') === false` ngay khi vào test, chứng minh đây đúng là ca cold-start); (c) transaction `RefreshDatabase` mở, việc bootstrap bảng compat diễn ra mà KHÔNG implicit-commit transaction đó; (d) test A ghi một dòng dữ liệu đặc trưng; (e) rollback lúc teardown thật sự xoá dòng đó; (f) một kiểm tra độc lập (không dùng lại connection/transaction của test A) xác nhận dòng đó không còn tồn tại.
2. Phải phủ đủ cả 5 bề mặt CI thật-MySQL bị ảnh hưởng trực tiếp (không chỉ `zena-invariants-mysql`): bước MySQL-parity của `routes-guardrails.yml`, `zena-invariants-mysql`, `treasury-check-constraints-mysql`, `e2e-tests`, bước chứng minh MySQL của GAP-032 trong `ci-cd.yml`.
3. Bộ test SQLite hiện có phải tiếp tục xanh không đổi, trước/sau, làm baseline hồi quy.

## Phạm vi loại trừ rõ ràng

Không sửa `RBACManager`, `Src\RBAC\Models\*`, hay bất kỳ migration nào. Không điều tra/kết luận việc `zena_roles`/`zena_permissions` có thật sự hoạt động đúng trên production hay không — đã đăng ký riêng thành **GAP-042** (PR #270, docs-only, chưa merge), không xử lý ở GAP-040. Không gộp GAP-041 (phát hiện độc lập: 3 job thật-MySQL chạy 0 test do lệch tên nhóm) vào phạm vi này. Không sửa file `config/database.php` đã commit trừ khi cơ chế đăng ký connection lúc runtime (mục tiêu ưu tiên) chứng minh bất khả thi lúc implementation — trong trường hợp đó, một entry cấu hình tối thiểu, chỉ áp dụng môi trường test, được cấp phép sẵn như một minimal implementation surface (không phải giấy phép đổi cấu hình production nói chung). Không có implementation plan, không có code, không có Gate 3 nào được cấp phép bởi tài liệu này.

## Governance classification (nhắc lại từ Gate 1, đã xác minh lại sau phát hiện §1)

GAP-040 vẫn là vấn đề test-infrastructure. Phát hiện mới về RBAC không thay đổi kết luận này, vì không phương án nào trong Gate 2 này sửa mã/schema/authorization production. Nếu implementation sau này phát hiện Option C (hoặc bất kỳ hướng nào khác) thực sự cần đụng đến schema/mã/authorization production, phải DỪNG và chạy Design Dependency Preflight tương ứng trước khi tiếp tục.

## What the owner is NOT being asked to decide

Owner không được yêu cầu chọn cơ chế kỹ thuật cụ thể để hiện thực Option C (đăng ký connection runtime vs. fallback config tối thiểu) — đó là quyết định implementation, trong ranh giới đã nêu ở trên. Owner không phê duyệt bất kỳ thay đổi mã/migration/config nào ở bước này, không quyết định về GAP-041/GAP-042 hay bất kỳ gap nào khác (đã đăng ký riêng, tự có vòng đời governance riêng). Owner chỉ xác nhận: hợp đồng nghiệm thu ở trên (loại bỏ hoàn toàn implicit-COMMIT kể cả cold-start, giữ nguyên bảng compat RBAC, không đổi production), yêu cầu bằng chứng hồi quy cold-start thật (không chỉ kiểm tra hình dạng mã), và phạm vi loại trừ ở trên — để đội kỹ thuật lập implementation plan (Gate 3 chưa bắt đầu, chưa được cấp phép).
