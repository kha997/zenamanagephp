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
  pr: null
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
  updated_at: "2026-08-20T00:00:00+07:00"
generated_by: agent
---

Gate 2 tiếp nối từ Gate 1 đã được Owner APPROVE tại PR #269 (lịch sử/bằng chứng Gate 1 vẫn giữ nguyên ở đó, head `78602d29ce66e63f782be49f98b493ba53c91fff`). Gói này được đặt trong một Draft PR riêng, cắt từ `origin/main` sạch (`87a4307fdcf8117d8cac4b11c2cb27cb637ada5a`), theo đúng tiền lệ GAP-039 — không trộn lịch sử/bằng chứng Gate 1 với gói Gate 2.

## Owner Summary

`tests/TestCase.php::ensureSqliteZenaRbacTables()` cần dừng việc chạy DDL vô điều kiện bên trong một transaction MySQL thật đã mở sẵn của `RefreshDatabase`, trên cả 5 bề mặt CI thật-MySQL đã xác nhận ở Gate 1 — nhưng **không được phá vỡ vai trò tương thích mà các bảng này đang gánh cho mã ứng dụng thật đang chạy**, một phát hiện mới trong lúc chuẩn bị Gate 2 (xem bên dưới) làm thay đổi đáng kể lựa chọn kỹ thuật an toàn.

## Phát hiện mới quan trọng (§1 của engineering spec)

Đánh giá Gate 1 trước đó giả định `zena_roles`/`zena_permissions` chỉ là "di sản trước-đổi-tên, chỉ phục vụ test". **Điều này KHÔNG đúng trên baseline hiện tại.** `src/RBAC/Models/Role.php`/`Permission.php` (bảng `zena_roles`/`zena_permissions`) là model thật, được `app/Services/RBACManager.php` dùng — service này chống lưng cho middleware `rbac:` áp dụng trên hàng chục controller thật, và `Src\RBAC\Providers\RBACServiceProvider` có đăng ký thật trong `config/app.php`. Một bản vá "chỉ bỏ qua DDL trên driver MySQL" (giống hệt cách hàm chị em `ensureSqliteDocumentsBackupTable()` làm) sẽ **xoá mất `zena_roles`/`zena_permissions` khỏi mọi lần chạy test MySQL-parity** — ít nhất 1 test đã xác nhận phụ thuộc trực tiếp vào lớp tương thích này (`ZenaAuthFlowInvariantTest`, nằm trong nhóm `zena-invariants-mysql`). Phát hiện này không tự nó kích hoạt Design Dependency Preflight (không phương án nào dưới đây sửa `RBACManager`, model, hay migration nào), nhưng nó loại bỏ hẳn phương án "chỉ copy y hệt guard driver của hàm chị em" khỏi danh sách an toàn.

## Các phương án đã xem xét

- **A — chỉ guard theo driver (copy y hệt hàm chị em):** BỊ LOẠI — xoá phụ thuộc RBAC thật trên đường MySQL, đổi một lỗi âm thầm lấy một lỗi test hỏng ngay lập tức.
- **B — chỉ guard theo existence (copy y hệt hàm chị em còn lại, `ensureSqliteSubmittalsTable()`'s pattern):** AN TOÀN — không bao giờ xoá bảng ở bất kỳ driver nào, chỉ ngừng tạo lại khi bảng đã tồn tại. Giảm phơi nhiễm từ "mọi test, mọi tiến trình" xuống còn "1 test đầu tiên mỗi tiến trình" (nhờ cơ chế `RefreshDatabaseState::$migrated` mà chính GAP-039 đã sửa). Còn sót lại đúng 1 trường hợp phơi nhiễm mỗi tiến trình — được nêu rõ, không che giấu.
- **C — guard existence + bootstrap DDL trên một connection MySQL riêng, không nằm trong transaction:** đóng hoàn toàn khoảng hở còn sót của B, nhưng cần thêm 1 connection DB mới trong `config/database.php` (chỉ là cấu hình, không phải migration/schema) — khả thi kỹ thuật chưa được chứng minh, để lại cho lúc implementation quyết định.
- **D — đưa việc tạo bảng compat vào chính migration (áp dụng cả production):** KHÔNG đề xuất cho GAP-040 — đây là thay đổi schema production, trộn lẫn một fix test-isolation với một câu hỏi (chưa có bằng chứng) về việc RBAC có thật sự hoạt động đúng trên production hay không. Nếu câu hỏi đó là thật, nó cần một work item riêng, có Design Dependency Preflight riêng.

## Đề xuất

Đội kỹ thuật đề xuất: Owner phê duyệt **Option B là mức tối thiểu bắt buộc, Option C được cấp phép theo đuổi nếu khả thi lúc implementation** (không bắt buộc phải chọn ngay bây giờ giữa B/C — đó là quyết định kỹ thuật lúc triển khai, không phải quyết định của Owner). Bất kể chọn B hay C, Gate 3 technical evidence phải mô tả trung thực đây là "giảm phơi nhiễm" (B) hay "loại bỏ hoàn toàn" (C) — không được thổi phồng.

Bằng chứng hồi quy bắt buộc (không chấp nhận test chỉ kiểm tra "có `if` trong mã nguồn"):
1. Kiểm tra hành vi trực tiếp: một cặp test viết dữ liệu ở test A, xác nhận dữ liệu KHÔNG còn ở test B chạy sau đó cùng tiến trình — chỉ pass được nếu rollback của `RefreshDatabase` thật sự thực thi, tức transaction thật sự vẫn còn mở.
2. Phải phủ đủ cả 5 bề mặt CI thật-MySQL bị ảnh hưởng trực tiếp (không chỉ `zena-invariants-mysql`): bước MySQL-parity của `routes-guardrails.yml`, `zena-invariants-mysql`, `treasury-check-constraints-mysql`, `e2e-tests`, bước chứng minh MySQL của GAP-032 trong `ci-cd.yml`.
3. Bộ test SQLite hiện có phải tiếp tục xanh không đổi, trước/sau, làm baseline hồi quy.

## Phạm vi loại trừ rõ ràng

Không sửa `RBACManager`, `Src\RBAC\Models\*`, hay bất kỳ migration nào. Không điều tra/kết luận việc `zena_roles`/`zena_permissions` có thật sự hoạt động đúng trên production hay không (câu hỏi mở, có thể là gap riêng — không xử lý ở đây). Không gộp GAP-041 (phát hiện độc lập: 3 job thật-MySQL chạy 0 test do lệch tên nhóm) vào phạm vi này. Không chọn dứt khoát giữa Option B/C — nhường cho implementation. Không có implementation plan, không có code, không có Gate 3 nào được cấp phép bởi tài liệu này.

## Governance classification (nhắc lại từ Gate 1, đã xác minh lại sau phát hiện §1)

GAP-040 vẫn là vấn đề test-infrastructure. Phát hiện mới về RBAC không thay đổi kết luận này, vì không phương án nào trong Gate 2 này sửa mã/schema production. Nếu implementation sau này phát hiện Option C (hoặc bất kỳ hướng nào khác) thực sự cần đụng đến schema/mã production, phải DỪNG và chạy Design Dependency Preflight tương ứng trước khi tiếp tục.

## What the owner is NOT being asked to decide

Owner không được yêu cầu chọn dứt khoát giữa Option B và Option C, không phê duyệt bất kỳ thay đổi mã/migration/config nào ở bước này, không quyết định về GAP-041 hay bất kỳ gap nào khác. Chỉ xác nhận: hướng thiết kế (Option B tối thiểu, Option C nếu khả thi), yêu cầu bằng chứng hồi quy hành vi thật (không chỉ kiểm tra hình dạng mã), và phạm vi loại trừ ở trên là đúng đắn để đội kỹ thuật lập implementation plan (Gate 3 chưa bắt đầu, chưa được cấp phép).
