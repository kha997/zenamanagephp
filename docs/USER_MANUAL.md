---
**DEPRECATED** (2025-09-24): This document is outdated.
Canonical reference: USER_MANUAL.md
Reason: Superseded by canonical user manual.
---
# Z.E.N.A Project Management - User Manual

## Mục lục

1. [Giới thiệu](#giới-thiệu)
2. [Đăng nhập và Bảo mật](#đăng-nhập-và-bảo-mật)
3. [Quản lý Dự án](#quản-lý-dự-án)
4. [Quản lý Nhiệm vụ](#quản-lý-nhiệm-vụ)
5. [Quản lý Thành phần](#quản-lý-thành-phần)
6. [Nhật ký Tương tác](#nhật-ký-tương-tác)
7. [Yêu cầu Thay đổi](#yêu-cầu-thay-đổi)
8. [Quản lý Người dùng](#quản-lý-người-dùng)
9. [Thông báo](#thông-báo)
10. [Báo cáo và Phân tích](#báo-cáo-và-phân-tích)

## Giới thiệu

Z.E.N.A Project Management là hệ thống quản lý dự án toàn diện, được thiết kế để hỗ trợ các doanh nghiệp trong việc quản lý dự án, nhiệm vụ, và tài nguyên một cách hiệu quả.

### Tính năng chính

- **Quản lý Dự án**: Tạo, theo dõi và quản lý dự án từ khởi tạo đến hoàn thành
- **Quản lý Nhiệm vụ**: Phân công, theo dõi tiến độ và quản lý dependencies
- **RBAC (Role-Based Access Control)**: Hệ thống phân quyền 3 lớp linh hoạt
- **Nhật ký Tương tác**: Ghi lại tất cả các tương tác trong dự án
- **Yêu cầu Thay đổi**: Quy trình approval cho các thay đổi dự án
- **Thông báo**: Hệ thống thông báo đa kênh
- **Báo cáo**: Dashboard và báo cáo chi tiết

## Đăng nhập và Bảo mật

### Đăng nhập

1. Truy cập vào URL của hệ thống
2. Nhập email và mật khẩu
3. Nhấn "Đăng nhập"

### Bảo mật

- Hệ thống sử dụng JWT (JSON Web Tokens) để xác thực
- Token có thời hạn 1 giờ và sẽ tự động refresh
- Mật khẩu được mã hóa bằng bcrypt

### Đổi mật khẩu

1. Vào **Hồ sơ cá nhân**
2. Chọn **Đổi mật khẩu**
3. Nhập mật khẩu cũ và mật khẩu mới
4. Xác nhận thay đổi

## Quản lý Dự án

### Tạo Dự án mới

1. Vào **Dự án** > **Tạo mới**
2. Điền thông tin:
   - **Tên dự án**: Tên mô tả dự án
   - **Mô tả**: Mô tả chi tiết về dự án
   - **Ngày bắt đầu**: Ngày khởi động dự án
   - **Ngày kết thúc**: Ngày dự kiến hoàn thành
   - **Template**: Chọn template nếu có
3. Nhấn **Tạo dự án**

### Quản lý Dự án

#### Dashboard Dự án
- **Tiến độ tổng thể**: Hiển thị % hoàn thành
- **Chi phí**: So sánh chi phí thực tế vs dự kiến
- **Timeline**: Gantt chart hiển thị timeline
- **Thành viên**: Danh sách thành viên và vai trò

#### Cập nhật Dự án
1. Vào chi tiết dự án
2. Nhấn **Chỉnh sửa**
3. Cập nhật thông tin cần thiết
4. **Lưu thay đổi**

### Baseline Management

#### Tạo Baseline
1. Vào **Dự án** > **Baseline**
2. Chọn loại baseline:
   - **Contract Baseline**: Baseline theo hợp đồng
   - **Execution Baseline**: Baseline thực thi
3. Điền thông tin và **Lưu**

## Quản lý Nhiệm vụ

### Tạo Nhiệm vụ

1. Vào **Dự án** > **Nhiệm vụ** > **Tạo mới**
2. Điền thông tin:
   - **Tên nhiệm vụ**
   - **Mô tả**
   - **Thành phần**: Chọn component liên quan
   - **Ngày bắt đầu/kết thúc**
   - **Dependencies**: Nhiệm vụ phụ thuộc
   - **Người thực hiện**: Phân công với % tham gia

### Quản lý Dependencies

- **Predecessor**: Nhiệm vụ phải hoàn thành trước
- **Successor**: Nhiệm vụ phụ thuộc vào nhiệm vụ này
- Hệ thống tự động cảnh báo circular dependencies

### Cập nhật Tiến độ

1. Vào chi tiết nhiệm vụ
2. Cập nhật **% hoàn thành**
3. Thêm **Ghi chú** nếu cần
4. **Lưu thay đổi**

### Conditional Tasks

- Nhiệm vụ có thể được ẩn dựa trên **conditional_tag**
- Chỉ hiển thị khi điều kiện được kích hoạt

## Quản lý Thành phần

### Tạo Component

1. Vào **Dự án** > **Thành phần** > **Tạo mới**
2. Điền thông tin:
   - **Tên thành phần**
   - **Component cha**: Nếu là sub-component
   - **Chi phí dự kiến**
   - **Mô tả**

### Cấu trúc Phân cấp

- Components có thể có cấu trúc phân cấp (parent-child)
- Tiến độ và chi phí được tính toán tự động từ sub-components

### Cập nhật Chi phí và Tiến độ

1. Vào chi tiết component
2. Cập nhật:
   - **Chi phí thực tế**
   - **% tiến độ**
3. Hệ thống tự động cập nhật project progress

## Nhật ký Tương tác

### Tạo Interaction Log

1. Vào **Dự án** > **Nhật ký** > **Tạo mới**
2. Chọn loại tương tác:
   - **Call**: Cuộc gọi
   - **Email**: Email
   - **Meeting**: Cuộc họp
   - **Note**: Ghi chú
   - **Feedback**: Phản hồi
3. Điền thông tin:
   - **Mô tả**: Nội dung chi tiết
   - **Tag Path**: Phân loại (VD: Material/Flooring/Granite)
   - **Visibility**: Internal hoặc Client
   - **Linked Task**: Liên kết với nhiệm vụ

### Quản lý Visibility

- **Internal**: Chỉ nội bộ team xem được
- **Client**: Client có thể xem (cần approval)

### Client Approval

1. Chọn log có visibility = "Client"
2. Nhấn **Approve for Client**
3. Log sẽ hiển thị cho client

## Yêu cầu Thay đổi

### Tạo Change Request

1. Vào **Dự án** > **Change Request** > **Tạo mới**
2. Điền thông tin:
   - **Tiêu đề**
   - **Mô tả chi tiết**
   - **Impact Days**: Ảnh hưởng đến timeline
   - **Impact Cost**: Ảnh hưởng đến chi phí
   - **Impact KPI**: Ảnh hưởng đến các KPI khác

### Quy trình Approval

1. **Draft**: Tạo mới, chưa submit
2. **Awaiting Approval**: Đã submit, chờ duyệt
3. **Approved**: Đã được duyệt
4. **Rejected**: Bị từ chối

### Xử lý Change Request

#### Approve CR
1. Vào chi tiết CR
2. Review thông tin impact
3. Nhấn **Approve**
4. Thêm **Decision Note**
5. Hệ thống tự động dispatch event để update các module liên quan

#### Reject CR
1. Vào chi tiết CR
2. Nhấn **Reject**
3. Thêm lý do từ chối

## Quản lý Người dùng

### Hệ thống RBAC 3 lớp

1. **System-Wide Roles**: Quyền toàn hệ thống
2. **Custom Roles**: Quyền tùy chỉnh
3. **Project-Specific Roles**: Quyền riêng cho từng dự án

### Tạo User

1. Vào **Quản lý** > **Người dùng** > **Tạo mới**
2. Điền thông tin cơ bản
3. Assign roles phù hợp

### Phân quyền

#### System Roles
- **Super Admin**: Full quyền hệ thống
- **Admin**: Quản lý trong tenant
- **Manager**: Quản lý dự án
- **User**: Người dùng cơ bản

#### Project Roles
- **Project Manager**: Quản lý dự án
- **Team Lead**: Dẫn dắt team
- **Developer**: Thực hiện nhiệm vụ
- **Viewer**: Chỉ xem

### Assign Roles

1. Vào chi tiết user
2. Tab **Roles**
3. Chọn **Add Role**
4. Chọn scope (System/Project) và role
5. **Lưu**

## Thông báo

### Cấu hình Notification Rules

1. Vào **Hồ sơ** > **Thông báo**
2. Tạo rule mới:
   - **Event**: Loại sự kiện (task.created, project.updated, etc.)
   - **Priority**: Mức độ ưu tiên tối thiểu
   - **Channels**: Kênh nhận thông báo
     - **In-app**: Trong ứng dụng
     - **Email**: Qua email
     - **Webhook**: Qua webhook

### Quản lý Thông báo

- **Mark as Read**: Đánh dấu đã đọc
- **Mark All as Read**: Đánh dấu tất cả đã đọc
- **Filter**: Lọc theo priority, channel

## Báo cáo và Phân tích

### Dashboard Tổng quan

- **Project Overview**: Tổng quan các dự án
- **Progress Charts**: Biểu đồ tiến độ
- **Cost Analysis**: Phân tích chi phí
- **Resource Utilization**: Sử dụng tài nguyên

### Báo cáo Chi tiết

#### Project Report
- Tiến độ từng component
- Chi phí thực tế vs dự kiến
- Timeline và milestones
- Resource allocation

#### Task Report
- Task completion rate
- Overdue tasks
- Task dependencies
- Performance metrics

#### User Report
- Workload distribution
- Performance tracking
- Time tracking

### Export Báo cáo

1. Chọn loại báo cáo
2. Thiết lập filters
3. Chọn format export (PDF, Excel, CSV)
4. **Download**

## Troubleshooting

### Các lỗi thường gặp

#### Không thể đăng nhập
- Kiểm tra email/password
- Xóa cache browser
- Liên hệ admin nếu account bị lock

#### Không thể tạo task
- Kiểm tra quyền truy cập
- Đảm bảo project đang active
- Kiểm tra dependencies hợp lệ

#### Thông báo không nhận được
- Kiểm tra notification rules
- Verify email settings
- Kiểm tra spam folder

### Liên hệ Hỗ trợ

- **Email**: support@zena-project.com
- **Phone**: +84 xxx xxx xxx
- **Help Desk**: Trong ứng dụng > Help
