# DANH SÁCH CHI TIẾT CÁC NGHIỆP VỤ CẦN TEST
# ZenaManage Project - Business Logic Testing Plan

## 📋 TỔNG QUAN

Hệ thống ZenaManage có các nghiệp vụ chính cần được test để đảm bảo hoạt động hiệu quả:

### 🎯 **CÁC NGHIỆP VỤ CHÍNH**

## 1. **QUẢN LÝ NGƯỜI DÙNG (USER MANAGEMENT)**

### 1.1 Authentication & Authorization
- [ ] **Đăng nhập hệ thống**
  - [ ] Đăng nhập với email/password hợp lệ
  - [ ] Đăng nhập với email/password không hợp lệ
  - [ ] Đăng nhập với tài khoản bị khóa
  - [ ] Đăng nhập với tài khoản không tồn tại
  - [ ] Đăng nhập với mật khẩu sai
  - [ ] Đăng nhập với email không đúng định dạng
  - [ ] Đăng nhập với tài khoản hết hạn

- [ ] **Đăng xuất hệ thống**
  - [ ] Đăng xuất thành công
  - [ ] Đăng xuất với token không hợp lệ
  - [ ] Đăng xuất với session hết hạn

- [ ] **Quản lý phiên đăng nhập**
  - [ ] Refresh token
  - [ ] Kiểm tra token hợp lệ
  - [ ] Xử lý token hết hạn

### 1.2 User CRUD Operations
- [ ] **Tạo người dùng mới**
  - [ ] Tạo user với thông tin hợp lệ
  - [ ] Tạo user với email trùng lặp
  - [ ] Tạo user với mật khẩu yếu
  - [ ] Tạo user với thông tin không đầy đủ
  - [ ] Tạo user với email không hợp lệ

- [ ] **Xem danh sách người dùng**
  - [ ] Xem danh sách tất cả users
  - [ ] Xem danh sách users với phân trang
  - [ ] Xem danh sách users với bộ lọc
  - [ ] Xem danh sách users với tìm kiếm

- [ ] **Cập nhật thông tin người dùng**
  - [ ] Cập nhật thông tin cơ bản
  - [ ] Cập nhật mật khẩu
  - [ ] Cập nhật trạng thái hoạt động
  - [ ] Cập nhật với thông tin không hợp lệ

- [ ] **Xóa người dùng**
  - [ ] Xóa user thành công
  - [ ] Xóa user không tồn tại
  - [ ] Xóa user đang có dữ liệu liên quan

### 1.3 Password Management
- [ ] **Đổi mật khẩu**
  - [ ] Đổi mật khẩu với mật khẩu cũ đúng
  - [ ] Đổi mật khẩu với mật khẩu cũ sai
  - [ ] Đổi mật khẩu với mật khẩu mới yếu
  - [ ] Đổi mật khẩu với mật khẩu mới trùng mật khẩu cũ

- [ ] **Quên mật khẩu**
  - [ ] Gửi email reset password
  - [ ] Reset password với token hợp lệ
  - [ ] Reset password với token hết hạn
  - [ ] Reset password với token không tồn tại

## 2. **QUẢN LÝ DỰ ÁN (PROJECT MANAGEMENT)**

### 2.1 Project CRUD Operations
- [ ] **Tạo dự án mới**
  - [ ] Tạo project với thông tin đầy đủ
  - [ ] Tạo project với mã dự án trùng lặp
  - [ ] Tạo project với ngày bắt đầu sau ngày kết thúc
  - [ ] Tạo project với thông tin không hợp lệ

- [ ] **Xem danh sách dự án**
  - [ ] Xem danh sách tất cả projects
  - [ ] Xem danh sách projects với phân trang
  - [ ] Xem danh sách projects với bộ lọc trạng thái
  - [ ] Xem danh sách projects với tìm kiếm

- [ ] **Cập nhật dự án**
  - [ ] Cập nhật thông tin cơ bản
  - [ ] Cập nhật trạng thái dự án
  - [ ] Cập nhật tiến độ dự án
  - [ ] Cập nhật ngân sách dự án

- [ ] **Xóa dự án**
  - [ ] Xóa project thành công
  - [ ] Xóa project có tasks liên quan
  - [ ] Xóa project không tồn tại

### 2.2 Project Status Management
- [ ] **Quản lý trạng thái dự án**
  - [ ] Chuyển từ planning sang active
  - [ ] Chuyển từ active sang on_hold
  - [ ] Chuyển từ on_hold sang active
  - [ ] Chuyển sang completed
  - [ ] Chuyển sang cancelled

### 2.3 Project Progress Tracking
- [ ] **Theo dõi tiến độ dự án**
  - [ ] Cập nhật tiến độ từ 0% đến 100%
  - [ ] Cập nhật tiến độ với giá trị không hợp lệ
  - [ ] Tính toán tiến độ tự động từ tasks

## 3. **QUẢN LÝ NHIỆM VỤ (TASK MANAGEMENT)**

### 3.1 Task CRUD Operations
- [ ] **Tạo nhiệm vụ mới**
  - [ ] Tạo task với thông tin đầy đủ
  - [ ] Tạo task với ngày bắt đầu sau ngày kết thúc
  - [ ] Tạo task với priority không hợp lệ
  - [ ] Tạo task với status không hợp lệ

- [ ] **Xem danh sách nhiệm vụ**
  - [ ] Xem danh sách tất cả tasks
  - [ ] Xem danh sách tasks theo project
  - [ ] Xem danh sách tasks theo assignee
  - [ ] Xem danh sách tasks với bộ lọc

- [ ] **Cập nhật nhiệm vụ**
  - [ ] Cập nhật thông tin cơ bản
  - [ ] Cập nhật trạng thái task
  - [ ] Cập nhật priority
  - [ ] Cập nhật assignee

- [ ] **Xóa nhiệm vụ**
  - [ ] Xóa task thành công
  - [ ] Xóa task có dependencies
  - [ ] Xóa task không tồn tại

### 3.2 Task Assignment
- [ ] **Phân công nhiệm vụ**
  - [ ] Phân công task cho user hợp lệ
  - [ ] Phân công task cho user không tồn tại
  - [ ] Phân công task cho user không có quyền
  - [ ] Hủy phân công task

### 3.3 Task Dependencies
- [ ] **Quản lý phụ thuộc nhiệm vụ**
  - [ ] Tạo dependency hợp lệ
  - [ ] Tạo circular dependency
  - [ ] Xóa dependency
  - [ ] Kiểm tra dependency chain

### 3.4 Task Status Management
- [ ] **Quản lý trạng thái nhiệm vụ**
  - [ ] Chuyển từ pending sang in_progress
  - [ ] Chuyển từ in_progress sang completed
  - [ ] Chuyển từ completed sang in_progress
  - [ ] Chuyển sang cancelled

## 4. **QUẢN LÝ TÀI LIỆU (DOCUMENT MANAGEMENT)**

### 4.1 Document CRUD Operations
- [ ] **Tạo tài liệu mới**
  - [ ] Upload file thành công
  - [ ] Upload file với định dạng không hỗ trợ
  - [ ] Upload file quá lớn
  - [ ] Upload file với tên không hợp lệ

- [ ] **Xem danh sách tài liệu**
  - [ ] Xem danh sách tất cả documents
  - [ ] Xem danh sách documents theo project
  - [ ] Xem danh sách documents theo loại
  - [ ] Xem danh sách documents với tìm kiếm

- [ ] **Cập nhật tài liệu**
  - [ ] Cập nhật thông tin metadata
  - [ ] Cập nhật visibility
  - [ ] Cập nhật tags
  - [ ] Cập nhật description

- [ ] **Xóa tài liệu**
  - [ ] Xóa document thành công
  - [ ] Xóa document không tồn tại
  - [ ] Xóa document có versions

### 4.2 Document Versioning
- [ ] **Quản lý phiên bản tài liệu**
  - [ ] Tạo version mới
  - [ ] Xem danh sách versions
  - [ ] Khôi phục version cũ
  - [ ] So sánh versions

### 4.3 Document Sharing
- [ ] **Chia sẻ tài liệu**
  - [ ] Chia sẻ với user khác
  - [ ] Chia sẻ với team
  - [ ] Chia sẻ với client
  - [ ] Hủy chia sẻ

## 5. **QUẢN LÝ YÊU CẦU THAY ĐỔI (CHANGE REQUEST MANAGEMENT)**

### 5.1 Change Request CRUD Operations
- [ ] **Tạo yêu cầu thay đổi**
  - [ ] Tạo CR với thông tin đầy đủ
  - [ ] Tạo CR với impact không hợp lệ
  - [ ] Tạo CR với priority không hợp lệ
  - [ ] Tạo CR với thông tin không đầy đủ

- [ ] **Xem danh sách yêu cầu thay đổi**
  - [ ] Xem danh sách tất cả CRs
  - [ ] Xem danh sách CRs theo project
  - [ ] Xem danh sách CRs theo status
  - [ ] Xem danh sách CRs theo priority

- [ ] **Cập nhật yêu cầu thay đổi**
  - [ ] Cập nhật thông tin cơ bản
  - [ ] Cập nhật status
  - [ ] Cập nhật priority
  - [ ] Cập nhật impact

- [ ] **Xóa yêu cầu thay đổi**
  - [ ] Xóa CR thành công
  - [ ] Xóa CR không tồn tại
  - [ ] Xóa CR đã được approve

### 5.2 Change Request Approval Workflow
- [ ] **Quy trình phê duyệt**
  - [ ] Submit CR để review
  - [ ] Approve CR
  - [ ] Reject CR
  - [ ] Request more information
  - [ ] Escalate CR

### 5.3 Change Request Status Management
- [ ] **Quản lý trạng thái CR**
  - [ ] Chuyển từ draft sang submitted
  - [ ] Chuyển từ submitted sang under_review
  - [ ] Chuyển từ under_review sang approved
  - [ ] Chuyển từ under_review sang rejected
  - [ ] Chuyển sang implemented

## 6. **QUẢN LÝ VAI TRÒ VÀ QUYỀN HẠN (RBAC)**

### 6.1 Role Management
- [ ] **Quản lý vai trò**
  - [ ] Tạo role mới
  - [ ] Cập nhật role
  - [ ] Xóa role
  - [ ] Xem danh sách roles

### 6.2 Permission Management
- [ ] **Quản lý quyền hạn**
  - [ ] Tạo permission mới
  - [ ] Cập nhật permission
  - [ ] Xóa permission
  - [ ] Xem danh sách permissions

### 6.3 User Role Assignment
- [ ] **Phân quyền người dùng**
  - [ ] Gán role cho user
  - [ ] Hủy role của user
  - [ ] Gán multiple roles
  - [ ] Kiểm tra effective permissions

### 6.4 Permission Checking
- [ ] **Kiểm tra quyền hạn**
  - [ ] Kiểm tra quyền view
  - [ ] Kiểm tra quyền create
  - [ ] Kiểm tra quyền update
  - [ ] Kiểm tra quyền delete
  - [ ] Kiểm tra quyền với context

## 7. **QUẢN LÝ TENANT (MULTI-TENANCY)**

### 7.1 Tenant Management
- [ ] **Quản lý tenant**
  - [ ] Tạo tenant mới
  - [ ] Cập nhật tenant
  - [ ] Xóa tenant
  - [ ] Xem danh sách tenants

### 7.2 Tenant Isolation
- [ ] **Cô lập dữ liệu tenant**
  - [ ] User chỉ thấy dữ liệu của tenant
  - [ ] Project chỉ thuộc về tenant
  - [ ] Task chỉ thuộc về tenant
  - [ ] Document chỉ thuộc về tenant

### 7.3 Tenant Switching
- [ ] **Chuyển đổi tenant**
  - [ ] Chuyển đổi tenant thành công
  - [ ] Chuyển đổi tenant không có quyền
  - [ ] Chuyển đổi tenant không tồn tại

## 8. **DASHBOARD VÀ BÁO CÁO (DASHBOARD & ANALYTICS)**

### 8.1 Dashboard Overview
- [ ] **Tổng quan dashboard**
  - [ ] Hiển thị metrics cơ bản
  - [ ] Hiển thị charts
  - [ ] Hiển thị alerts
  - [ ] Hiển thị recent activities

### 8.2 Project Analytics
- [ ] **Phân tích dự án**
  - [ ] Project progress charts
  - [ ] Task completion rates
  - [ ] Budget vs actual
  - [ ] Timeline analysis

### 8.3 User Analytics
- [ ] **Phân tích người dùng**
  - [ ] User activity logs
  - [ ] Login statistics
  - [ ] Performance metrics
  - [ ] Usage patterns

### 8.4 Reporting
- [ ] **Báo cáo**
  - [ ] Export reports
  - [ ] Generate PDF reports
  - [ ] Schedule reports
  - [ ] Custom reports

## 9. **THÔNG BÁO (NOTIFICATIONS)**

### 9.1 Notification Management
- [ ] **Quản lý thông báo**
  - [ ] Tạo notification
  - [ ] Gửi notification
  - [ ] Đánh dấu đã đọc
  - [ ] Xóa notification

### 9.2 Notification Types
- [ ] **Các loại thông báo**
  - [ ] Task assignment notifications
  - [ ] Project status change notifications
  - [ ] Document upload notifications
  - [ ] Change request notifications
  - [ ] System notifications

### 9.3 Notification Delivery
- [ ] **Gửi thông báo**
  - [ ] Email notifications
  - [ ] In-app notifications
  - [ ] Push notifications
  - [ ] SMS notifications

## 10. **BULK OPERATIONS**

### 10.1 Bulk User Operations
- [ ] **Thao tác hàng loạt với users**
  - [ ] Bulk create users
  - [ ] Bulk update users
  - [ ] Bulk delete users
  - [ ] Bulk assign roles

### 10.2 Bulk Project Operations
- [ ] **Thao tác hàng loạt với projects**
  - [ ] Bulk create projects
  - [ ] Bulk update projects
  - [ ] Bulk delete projects
  - [ ] Bulk assign teams

### 10.3 Bulk Task Operations
- [ ] **Thao tác hàng loạt với tasks**
  - [ ] Bulk create tasks
  - [ ] Bulk update tasks
  - [ ] Bulk delete tasks
  - [ ] Bulk assign tasks

## 11. **SECURITY FEATURES**

### 11.1 CSRF Protection
- [ ] **Bảo vệ CSRF**
  - [ ] Forms có CSRF token
  - [ ] API calls có CSRF protection
  - [ ] CSRF token validation
  - [ ] CSRF token expiration

### 11.2 Input Sanitization
- [ ] **Làm sạch input**
  - [ ] Sanitize text input
  - [ ] Sanitize HTML input
  - [ ] Detect suspicious patterns
  - [ ] Block malicious input

### 11.3 Password Security
- [ ] **Bảo mật mật khẩu**
  - [ ] Password strength validation
  - [ ] Password breach detection
  - [ ] Password hashing
  - [ ] Password expiration

## 12. **PERFORMANCE FEATURES**

### 12.1 Caching
- [ ] **Hệ thống cache**
  - [ ] Redis caching
  - [ ] Database query caching
  - [ ] API response caching
  - [ ] Cache invalidation

### 12.2 Database Optimization
- [ ] **Tối ưu database**
  - [ ] Query optimization
  - [ ] Index usage
  - [ ] Connection pooling
  - [ ] Query logging

### 12.3 Performance Monitoring
- [ ] **Giám sát hiệu suất**
  - [ ] Response time monitoring
  - [ ] Memory usage monitoring
  - [ ] CPU usage monitoring
  - [ ] Database performance monitoring

## 13. **DISASTER RECOVERY**

### 13.1 Backup Operations
- [ ] **Thao tác backup**
  - [ ] Full backup
  - [ ] Incremental backup
  - [ ] Database backup
  - [ ] Application backup

### 13.2 Recovery Operations
- [ ] **Thao tác recovery**
  - [ ] Full recovery
  - [ ] Database recovery
  - [ ] Application recovery
  - [ ] Configuration recovery

### 13.3 Monitoring
- [ ] **Giám sát hệ thống**
  - [ ] System health monitoring
  - [ ] Service status monitoring
  - [ ] Backup status monitoring
  - [ ] Alert notifications

## 📊 **THỐNG KÊ TỔNG QUAN**

- **Tổng số nghiệp vụ**: 13 nhóm chính
- **Tổng số test cases**: 200+ test cases
- **Mức độ ưu tiên**: 
  - **HIGH**: Authentication, Project Management, Task Management
  - **MEDIUM**: Document Management, Change Request, RBAC
  - **LOW**: Notifications, Bulk Operations, Analytics

## 🎯 **KẾ HOẠCH TEST**

1. **Phase 1**: Test các nghiệp vụ cốt lõi (Authentication, Project, Task)
2. **Phase 2**: Test các nghiệp vụ hỗ trợ (Document, Change Request, RBAC)
3. **Phase 3**: Test các tính năng nâng cao (Notifications, Bulk Operations, Analytics)
4. **Phase 4**: Test các tính năng bảo mật và hiệu suất

## ✅ **TIÊU CHÍ THÀNH CÔNG**

- Tất cả test cases phải PASS
- Response time < 2 giây
- Không có lỗi critical
- Tất cả business logic hoạt động đúng
- Security features hoạt động hiệu quả
- Performance metrics đạt mục tiêu
