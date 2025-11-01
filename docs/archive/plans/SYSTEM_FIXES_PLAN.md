# KẾ HOẠCH XỬ LÝ CÁC TỒN TẠI HỆ THỐNG ZENAMANAGE

## 🚨 PHASE 1: XỬ LÝ CÁC VẤN ĐỀ BLOCKING (Ưu tiên cao nhất)

### 1.1 API Dashboard Trả Dữ Liệu Mock
**Vấn đề**: `app/Http/Controllers/Api/DashboardController.php:24-154` trả dữ liệu hardcode thay vì đọc từ DB
**Tác động**: UI không phản ánh trạng thái thực của hệ thống
**Giải pháp**:
- [ ] Tạo DashboardService để xử lý logic nghiệp vụ
- [ ] Implement các method thực tế: getStats(), getRecentProjects(), getRecentTasks()
- [ ] Sử dụng Eloquent queries với tenant isolation
- [ ] Thêm caching cho performance
- [ ] Viết tests cho từng method

### 1.2 ProjectService/TaskService Thiếu Kiểm Tra Quyền
**Vấn đề**: 
- `canUserCreateProjects()` luôn return true
- `canUserAccessProject()` luôn return true  
- `validateTaskAccess()` method trống hoàn toàn
**Tác động**: Vi phạm multi-tenant isolation, rủi ro bảo mật cao
**Giải pháp**:
- [ ] Implement RBAC checks thực tế trong ProjectService
- [ ] Implement permission validation trong TaskService
- [ ] Thêm tenant isolation checks
- [ ] Tích hợp với Permission system
- [ ] Viết security tests

### 1.3 AppApiGateway Sinh Token Mới Mỗi Lần Gọi
**Vấn đề**: `app/Services/AppApiGateway.php:27-52` tạo token mới mỗi request
**Tác động**: Token spam, thiếu ràng buộc ability
**Giải pháp**:
- [ ] Implement token reuse mechanism
- [ ] Thêm session-based token management
- [ ] Implement proper ability constraints
- [ ] Thêm token cleanup job
- [ ] Implement circuit breaker pattern

### 1.4 API Documents Ghi Sai Trường
**Vấn đề**: 
- Lưu `file_name` nhưng model chỉ nhận `file_path/mime_type`
- Dùng `$request->validated()` khi không có FormRequest
**Tác động**: Lỗi runtime, data inconsistency
**Giải pháp**:
- [ ] Tạo DocumentUploadRequest FormRequest
- [ ] Sửa mapping fields trong DocumentsController
- [ ] Update Document model để match API contract
- [ ] Implement proper file validation
- [ ] Viết integration tests

### 1.5 Tests Không Khớp Code Thực Tế
**Vấn đề**: `tests/Unit/TaskServiceTest.php:118-199` gọi sai method signatures
**Tác động**: Tests không chạy được, không bảo vệ nghiệp vụ
**Giải pháp**:
- [ ] Sửa method calls trong TaskServiceTest
- [ ] Implement proper test data setup
- [ ] Thêm assertions cho business logic
- [ ] Viết integration tests cho API endpoints
- [ ] Implement tenant isolation tests

### 1.6 RBAC System Chưa Có Dữ Liệu
**Vấn đề**: `src/Foundation/Permission.php:96-123` TODO và trả mảng rỗng
**Tác động**: Hệ thống phân quyền không hoạt động
**Giải pháp**:
- [ ] Implement Permission::getRolePermissions()
- [ ] Tạo seeders cho roles và permissions
- [ ] Implement role-based access checks
- [ ] Tích hợp với middleware
- [ ] Viết RBAC tests

### 1.7 Document Event Listeners Trống
**Vấn đề**: `src/DocumentManagement/Listeners/DocumentEventListener.php:67-150` bỏ trống nhiều actions
**Tác động**: Domain document-sharing không hoàn chỉnh
**Giải pháp**:
- [ ] Implement notification sending
- [ ] Implement search index updates
- [ ] Implement audit logging
- [ ] Implement cleanup jobs
- [ ] Viết event tests

## 🔧 PHASE 2: CẢI TIẾN ƯU TIÊN TIẾP THEO

### 2.1 Chuẩn Hóa Request Validation
- [ ] Tạo FormRequest classes cho tất cả API endpoints
- [ ] Implement proper validation rules
- [ ] Sử dụng `$validator->validated()` thay vì `$request->all()`
- [ ] Thêm custom validation rules

### 2.2 Đồng Bộ Tên Trường API ↔ Model ↔ Frontend
- [ ] Audit tất cả field mappings
- [ ] Standardize naming conventions
- [ ] Update API documentation
- [ ] Update frontend contracts

### 2.3 Hoàn Thiện Performance Monitoring
- [ ] Implement real metrics collection
- [ ] Thêm rate limiting
- [ ] Implement performance SLO monitoring
- [ ] Thêm alerting system

### 2.4 Tối Ưu AppApiGateway
- [ ] Implement token reuse
- [ ] Thêm retry mechanism
- [ ] Implement circuit breaker
- [ ] Thêm proper error handling

## 📊 PHASE 3: KHOẢNG TRỐNG KIỂM THỬ & TÀI LIỆU

### 3.1 Viết Tests Tích Hợp Thật
- [ ] Projects API integration tests
- [ ] Tasks API integration tests  
- [ ] Clients API integration tests
- [ ] Documents API integration tests
- [ ] RBAC/multi-tenant tests với dữ liệu thực
- [ ] Performance tests

### 3.2 Cập Nhật Tài Liệu
- [ ] Dọn dẹp DETAILED_TODO_LIST.md
- [ ] Cập nhật COMPLETE_SYSTEM_DOCUMENTATION.md
- [ ] Thêm hướng dẫn quản lý tokens
- [ ] Cập nhật API documentation

## 🎯 PHASE 4: ĐỀ XUẤT TIẾP THEO

### 4.1 Khóa Lại Các Service Cốt Lõi
- [ ] Implement permission checks thật
- [ ] Đồng bộ validated data
- [ ] Sửa Document storage
- [ ] Implement proper error handling

### 4.2 Viết Lại Bộ Feature Tests
- [ ] Projects feature tests
- [ ] Tasks feature tests
- [ ] Clients feature tests
- [ ] Documents feature tests
- [ ] Response contract validation
- [ ] Tenant isolation validation

### 4.3 Hoàn Thiện Domain Phụ
- [ ] Document events
- [ ] Metrics collection
- [ ] Rate limiting
- [ ] Monitoring & alerting

## 📅 TIMELINE THỰC HIỆN

### Tuần 1: Phase 1.1-1.3 (Dashboard API, Services Permissions, API Gateway)
### Tuần 2: Phase 1.4-1.7 (Documents API, Tests, RBAC, Events)
### Tuần 3: Phase 2.1-2.4 (Validation, Field Sync, Monitoring, Gateway Optimization)
### Tuần 4: Phase 3-4 (Tests, Documentation, Core Services, Feature Tests)

## 🔍 CRITERIA FOR SUCCESS

### Technical Criteria:
- [ ] Tất cả API endpoints trả dữ liệu thực từ DB
- [ ] Multi-tenant isolation được enforce ở mọi layer
- [ ] RBAC system hoạt động đầy đủ
- [ ] Tests coverage > 80% cho core functionality
- [ ] Performance SLO được đáp ứng

### Business Criteria:
- [ ] UI phản ánh trạng thái thực của hệ thống
- [ ] Không có rủi ro bảo mật từ tenant isolation
- [ ] Hệ thống phân quyền hoạt động đúng
- [ ] Document management workflow hoàn chỉnh
- [ ] Monitoring và alerting hoạt động

## 🚨 RISK MITIGATION

### High Risk Items:
1. **Multi-tenant isolation**: Có thể gây data leak
2. **RBAC system**: Có thể gây unauthorized access
3. **API Gateway**: Có thể gây performance issues
4. **Document storage**: Có thể gây data corruption

### Mitigation Strategies:
1. Implement comprehensive tests trước khi deploy
2. Code review bắt buộc cho security-related changes
3. Staged deployment với monitoring
4. Rollback plan cho mỗi phase

---

**Lưu ý**: Kế hoạch này được thiết kế để xử lý triệt để các vấn đề blocking và đảm bảo hệ thống hoạt động ổn định, bảo mật và hiệu quả.
