# 🧪 MUST HAVE FEATURES TEST SUITE

Bộ test suite kiểm tra 6 tính năng Must Have của hệ thống ZENA Manage theo các kịch bản nghiệp vụ thực tế.

## 📋 Danh sách Test Cases

### 1. 🔐 RBAC Roles Test (`test_rbac_roles.php`)
Kiểm tra hệ thống phân quyền với 7 vai trò nghiệp vụ:

- **System Admin**: Quyền cao nhất, quản lý toàn hệ thống
- **Project Manager**: Quản lý dự án, baseline, CR, phê duyệt
- **Design Lead**: Phát hành bản vẽ, RFI/Submittal
- **Site Engineer**: Nhật ký, nghiệm thu, ảnh hiện trường
- **QC/QA Inspector**: Kiểm định, checklist, NCR/Observation
- **Client Rep**: Duyệt CR, duyệt hồ sơ, giới hạn quyền xem
- **Subcontractor Lead**: Cập nhật tiến độ, submit vật tư/biện pháp

**Test Cases:**
- Kiểm tra permissions theo từng role
- Test role switching và context switching
- Test permission override (PM có thể override task dependency)
- Test audit trail cho mọi thay đổi quyền

### 2. 📝 RFI Workflow Test (`test_rfi_workflow.php`)
Kiểm tra quy trình Request for Information:

**Kịch bản:** Site Engineer gửi RFI → Design Lead trả lời → PM đóng

**Test Cases:**
- Tạo RFI với thông tin đầy đủ
- Gán RFI cho người xử lý
- SLA tracking (3 ngày)
- Trả lời RFI với attachments
- Escalation khi quá hạn
- Đóng RFI (chỉ khi đã trả lời)
- Visibility control (internal/client)
- File attachments security

### 3. 🔄 Change Request Test (`test_change_request.php`)
Kiểm tra quy trình thay đổi dự án:

**Kịch bản:** PM tạo CR → Client Rep phê duyệt → Apply impact

**Test Cases:**
- Tạo CR với impact analysis
- Submit CR để phê duyệt
- Multi-level approval (CR > 5% budget)
- Approval workflow với audit trail
- Apply CR vào project/baseline
- Baseline update và snapshot
- CR conflict detection
- Audit trail đầy đủ

### 4. 🔗 Task Dependencies Test (`test_task_dependencies.php`)
Kiểm tra quy trình phụ thuộc task:

**Kịch bản:** Task B phụ thuộc Task A → tự động khóa Start

**Test Cases:**
- Tạo tasks và dependencies
- Dependency validation (không circular)
- Task blocking khi dependency chưa hoàn thành
- Task unblocking khi dependency hoàn thành
- PM override với lý do bắt buộc
- Circular dependency prevention
- Dependency chain visualization
- Audit trail cho dependencies

### 5. 🏢 Multi-tenant Test (`test_multi_tenant.php`)
Kiểm tra tenant isolation và security:

**Test Cases:**
- Tenant isolation (user chỉ thấy dữ liệu tenant mình)
- Cross-tenant access prevention (403 response)
- ULID security (không lộ sequence)
- Tenant context từ JWT
- Data segregation theo tenant
- Tenant switching (nếu user có nhiều tenant)
- Tenant audit trail
- Tenant limits (users, projects, storage, API calls)

### 6. 🔒 Secure Upload Test (`test_secure_upload.php`)
Kiểm tra bảo mật file upload:

**Test Cases:**
- File validation (tên, size, empty)
- MIME validation (kiểm tra thực tế, chặn fake MIME)
- File security (chặn PHP, JS, executable, shell)
- Storage security (ngoài public, tên ngẫu nhiên)
- Signed URLs với TTL
- File size limits theo role/type/tenant
- File type restrictions theo user role
- Virus scanning
- Metadata stripping (EXIF, PDF, Office)

## 🚀 Cách chạy Tests

### Chạy tất cả tests:
```bash
php run_all_must_have_tests.php
```

### Chạy từng test riêng lẻ:
```bash
php test_rbac_roles.php
php test_rfi_workflow.php
php test_change_request.php
php test_task_dependencies.php
php test_multi_tenant.php
php test_secure_upload.php
```

### Chạy test tổng hợp:
```bash
php test_must_have_features.php
```

## 📊 Đánh giá kết quả

### Pass Rate:
- **90%+**: 🎉 Xuất sắc - Hệ thống sẵn sàng production
- **80-89%**: ✅ Tốt - Hệ thống hoạt động ổn định
- **60-79%**: ⚠️ Cần cải thiện - Một số tính năng cần sửa
- **<60%**: ❌ Nghiêm trọng - Cần sửa chữa nhiều

### Các chỉ số quan trọng:
- **Total Tests**: Tổng số test cases
- **Passed**: Số test cases passed
- **Failed**: Số test cases failed
- **Error**: Số test cases bị lỗi
- **Duration**: Thời gian thực hiện
- **Pass Rate**: Tỷ lệ thành công

## 🔧 Cấu hình Test

### Environment Requirements:
- PHP 8.1+
- Laravel Framework
- MySQL Database
- Redis Cache
- File Storage

### Test Data:
- Tự động tạo test tenants, users, projects
- Tự động cleanup sau khi test
- Mock data cho các tính năng chưa implement

## 📝 Ghi chú

### Mock Implementation:
Các test scripts sử dụng mock implementation cho:
- Database operations
- File upload/storage
- Authentication/Authorization
- External services

### Real Implementation:
Để test với implementation thực tế, cần:
1. Cập nhật các helper methods trong test scripts
2. Kết nối với database thực
3. Cấu hình file storage thực
4. Setup authentication thực

### Customization:
Có thể tùy chỉnh test cases bằng cách:
- Sửa đổi test data trong `setupTestData()`
- Thêm test cases mới trong các method test
- Điều chỉnh pass/fail criteria
- Thêm validation rules

## 🎯 Mục tiêu

Bộ test suite này giúp:
- ✅ Đảm bảo các tính năng Must Have hoạt động đúng
- ✅ Kiểm tra các kịch bản nghiệp vụ thực tế
- ✅ Phát hiện lỗi sớm trong quá trình phát triển
- ✅ Đánh giá chất lượng hệ thống
- ✅ Tạo confidence cho production deployment

## 📞 Support

Nếu gặp vấn đề với test suite:
1. Kiểm tra Laravel environment
2. Đảm bảo database connection
3. Kiểm tra file permissions
4. Review error logs
5. Contact development team

---

**ZENA Manage Development Team**  
*Last updated: $(date)*
