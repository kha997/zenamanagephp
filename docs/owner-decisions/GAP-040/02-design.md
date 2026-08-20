---
work_id: GAP-040
gate: 2
gate_status: awaiting_owner
owner_decision:
  value: none
  authority: human_owner
decision_requested: "approve_or_changes_or_decline"
references:
  spec: docs/superpowers/specs/2026-08-20-gap-040-testcase-mysql-transaction-isolation-design.md
  plan: null
  branch: docs/GAP-040-gate2-design
  pr: "https://github.com/kha997/zenamanagephp/pull/271"
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
  created_at: "2026-08-20T00:00:00+07:00"
  updated_at: "2026-08-20T15:00:00+07:00"
generated_by: agent
---

> **v2 — correction/resubmission, not an Owner decision.** Owner requested changes on v1 (head `04cbfd13a56936cef96d655ec81d080882ae5b28`): Option B could not remain the shippable target while the approved invariant requires zero DDL on the transacted connection including the first test; the regression proof needed an explicit cold-start requirement, not just an ordered pair that could run warm; Option C's connection-registration mechanism needed to not silently imply a checked-in `config/database.php` change while also saying no config changes are approved; the RBAC production-fidelity finding needed its own separate Work ID. All four are corrected below and in the engineering spec. No Owner decision has been recorded yet; `gate_status` remains `awaiting_owner` and `owner_decision.value` remains `none`.

Gate 2 tiếp nối từ Gate 1 đã được Owner APPROVE tại PR #269 (lịch sử/bằng chứng Gate 1 vẫn giữ nguyên ở đó, head `78602d29ce66e63f782be49f98b493ba53c91fff`). Gói này được đặt trong một Draft PR riêng (PR #271), cắt từ `origin/main` sạch (`87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`), theo đúng tiền lệ GAP-039 — không trộn lịch sử/bằng chứng Gate 1 với gói Gate 2.

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
