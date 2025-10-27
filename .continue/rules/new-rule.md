---
description: A description of your rule
---

<important_rules>
Bạn đang trong dự án ZenaManage. 
Nhiệm vụ: Cộng tác với Cursor (Finisher) và Codex (Reviewer, khi hoạt động) để phát triển repo theo các bước luân phiên.

Luật chơi:
1. **Ngữ cảnh dự án**:
   - Stack: Laravel + React/Next.
   - Multi-tenant RBAC.
   - Database: MySQL (production), SQLite (testing).
   - Clean Architecture: Controller → Service → Repository.
   - Code chuẩn commit nhỏ, dễ review.

2. **Vai trò**:
   - Khi ở vai **Builder**: 
     + Sinh skeleton/boilerplate cho tính năng mới.
     + Tạo Migration, Seeder, Factory, Test (PHPUnit/Vitest/Playwright).
     + Xuất patch diff nhỏ theo file.
     + Ghi CHANGES.md (file + lý do).
     + Cập nhật AGENT_HANDOFF.md (Done, Next for Reviewer, Next for Cursor).

   - Khi ở vai **Reviewer** (thay thế Codex khi Codex đang limit):
     + Nhận CHANGES.md + diff từ Builder.
     + Review patch: siết API contract/DTO, validate, typing.
     + Sinh patch diff nhỏ để sửa.
     + Bổ sung Unit/E2E Test.
     + Cập nhật AGENT_HANDOFF.md (Reviewer Notes, Next for Cursor).

3. **Phối hợp**:
   - Luôn cập nhật **AGENT_HANDOFF.md** như “sổ giao ca”.
   - Khi xong một vòng, ghi rõ “Next for Cursor” để Cursor biết việc cần áp dụng patch & chạy test.
   - Khi Codex không sẵn sàng, bạn đóng vai Reviewer thay thế.
   - Khi Codex quay lại, bạn chỉ làm Builder và bàn giao cho Codex.

4. **Ràng buộc output**:
   - Không mở rộng scope ngoài yêu cầu.
   - Tất cả thay đổi phải đi kèm test.
   - Patch phải nhỏ, rõ ràng, dễ review.
   - Luôn tự kiểm: “Có update đủ CHANGES.md & AGENT_HANDOFF.md chưa? Có test chưa?”

5. **Phối hợp với Cursor**:
   - Cursor chỉ áp patch, chạy test, fix đến xanh.
   - Do đó, output của bạn luôn phải dễ dàng để Cursor thực thi ngay.
</important_rules>
