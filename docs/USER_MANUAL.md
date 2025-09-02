# Z.E.N.A Project Management - Hướng dẫn sử dụng

## Mục lục
1. [Giới thiệu](#giới-thiệu)
2. [Đăng nhập và Bắt đầu](#đăng-nhập-và-bắt-đầu)
3. [Quản lý Dự án](#quản-lý-dự-án)
4. [Quản lý Nhiệm vụ](#quản-lý-nhiệm-vụ)
5. [Quản lý Tài liệu](#quản-lý-tài-liệu)
6. [Change Requests](#change-requests)
7. [Thông báo](#thông-báo)
8. [Phân quyền](#phân-quyền)
9. [Báo cáo và Thống kê](#báo-cáo-và-thống-kê)

## Giới thiệu

Z.E.N.A Project Management là hệ thống quản lý dự án toàn diện, giúp bạn:
- Quản lý dự án từ khởi tạo đến hoàn thành
- Theo dõi tiến độ và chi phí real-time
- Quản lý tài liệu và phiên bản
- Xử lý change requests
- Giao tiếp và thông báo real-time
- Phân quyền chi tiết theo vai trò

## Đăng nhập và Bắt đầu

### Đăng nhập
1. Truy cập trang chủ của hệ thống
2. Nhập email và mật khẩu
3. Nhấn "Đăng nhập"

### Dashboard
Sau khi đăng nhập, bạn sẽ thấy Dashboard với:
- Tổng quan dự án đang thực hiện
- Nhiệm vụ cần hoàn thành
- Thông báo mới
- Biểu đồ tiến độ và chi phí

## Quản lý Dự án

### Tạo Dự án Mới
1. Vào menu "Dự án" → "Tạo mới"
2. Điền thông tin:
   - **Tên dự án**: Tên mô tả dự án
   - **Mô tả**: Chi tiết về dự án
   - **Ngày bắt đầu/kết thúc**: Timeline dự án
   - **Template**: Chọn template có sẵn (nếu có)
3. Nhấn "Tạo dự án"

### Xem Danh sách Dự án
- Vào menu "Dự án" → "Danh sách"
- Sử dụng bộ lọc để tìm kiếm:
  - Theo trạng thái
  - Theo tên dự án
  - Theo ngày tạo

### Chi tiết Dự án
Nhấn vào tên dự án để xem:
- **Thông tin tổng quan**: Tiến độ, chi phí, timeline
- **Components**: Các thành phần của dự án
- **Tasks**: Danh sách nhiệm vụ
- **Documents**: Tài liệu liên quan
- **Change Requests**: Yêu cầu thay đổi
- **Team**: Thành viên tham gia

### Cập nhật Tiến độ
1. Vào chi tiết dự án
2. Chọn tab "Components"
3. Nhấn "Cập nhật" bên cạnh component
4. Nhập:
   - **Tiến độ (%)**: Phần trăm hoàn thành
   - **Chi phí thực tế**: Chi phí đã phát sinh
   - **Ghi chú**: Mô tả về tiến độ

## Quản lý Nhiệm vụ

### Tạo Nhiệm vụ
1. Vào dự án → Tab "Tasks" → "Tạo mới"
2. Điền thông tin:
   - **Tên nhiệm vụ**
   - **Mô tả chi tiết**
   - **Ngày bắt đầu/kết thúc**
   - **Component**: Thuộc thành phần nào
   - **Dependencies**: Nhiệm vụ phụ thuộc
   - **Phân công**: Giao cho ai, tỷ lệ phần trăm

### Cập nhật Trạng thái
1. Vào danh sách nhiệm vụ
2. Nhấn vào nhiệm vụ cần cập nhật
3. Thay đổi trạng thái:
   - **Pending**: Chờ thực hiện
   - **In Progress**: Đang thực hiện
   - **Completed**: Hoàn thành
   - **On Hold**: Tạm dừng

### Theo dõi Dependencies
- Hệ thống tự động hiển thị các nhiệm vụ phụ thuộc
- Không thể bắt đầu nhiệm vụ khi dependencies chưa hoàn thành
- Biểu đồ Gantt hiển thị mối quan hệ phụ thuộc

## Quản lý Tài liệu

### Upload Tài liệu
1. Vào dự án → Tab "Documents" → "Upload"
2. Chọn file từ máy tính
3. Điền thông tin:
   - **Tiêu đề**: Tên tài liệu
   - **Liên kết với**: Task, Diary, hoặc Change Request
   - **Ghi chú**: Mô tả về tài liệu

### Quản lý Phiên bản
- Mỗi lần upload file mới sẽ tạo phiên bản mới
- Có thể xem lịch sử các phiên bản
- Có thể revert về phiên bản cũ
- Download bất kỳ phiên bản nào

### Phân loại Tài liệu
Tài liệu được phân loại theo:
- **Loại**: Contract, Design, Report, etc.
- **Trạng thái**: Draft, Review, Approved
- **Quyền truy cập**: Internal, Client

## Change Requests

### Tạo Change Request
1. Vào dự án → Tab "Change Requests" → "Tạo mới"
2. Điền thông tin:
   - **Tiêu đề**: Tóm tắt thay đổi
   - **Mô tả**: Chi tiết về thay đổi
   - **Tác động**:
     - Thời gian (ngày)
     - Chi phí (VND)
     - KPI (chất lượng, timeline, ngân sách)

### Quy trình Phê duyệt
1. **Draft**: Tạo và chỉnh sửa
2. **Awaiting Approval**: Gửi để phê duyệt
3. **Approved/Rejected**: Quyết định cuối cùng

### Xử lý sau Phê duyệt
Khi CR được phê duyệt:
- Hệ thống tự động cập nhật timeline
- Điều chỉnh ngân sách dự án
- Thông báo cho team members
- Tạo tasks mới nếu cần

## Thông báo

### Loại Thông báo
- **Critical**: Cần xử lý ngay
- **Normal**: Thông tin quan trọng
- **Low**: Thông tin tham khảo

### Kênh Thông báo
- **In-app**: Hiển thị trong hệ thống
- **Email**: Gửi qua email
- **WebSocket**: Real-time notifications

### Cài đặt Thông báo
1. Vào "Cài đặt" → "Thông báo"
2. Chọn loại sự kiện muốn nhận thông báo
3. Chọn kênh thông báo
4. Đặt mức độ ưu tiên tối thiểu

## Phân quyền

### Các Loại Role
- **System Admin**: Quản trị toàn hệ thống
- **Project Manager**: Quản lý dự án
- **Team Lead**: Dẫn dắt nhóm
- **Developer**: Thực hiện nhiệm vụ
- **Client**: Khách hàng (chỉ xem)

### Phân quyền theo Dự án
- Mỗi user có thể có role khác nhau ở các dự án khác nhau
- Project-specific roles override system roles
- Có thể tạo custom roles cho từng dự án

### Quản lý Permissions
Admin có thể:
- Tạo/sửa/xóa roles
- Gán/gỡ permissions cho roles
- Assign roles cho users
- Xem audit log của permissions

## Báo cáo và Thống kê

### Dashboard Analytics
- **Project Progress**: Tiến độ tổng thể
- **Budget Tracking**: Theo dõi ngân sách
- **Resource Utilization**: Sử dụng nguồn lực
- **Timeline Analysis**: Phân tích timeline

### Báo cáo Chi tiết
1. Vào "Báo cáo" từ menu chính
2. Chọn loại báo cáo:
   - Project Summary
   - Task Performance
   - Budget Analysis
   - Team Productivity
3. Chọn khoảng thời gian
4. Export PDF/Excel nếu cần

### Metrics và KPIs
Hệ thống theo dõi:
- **On-time Delivery Rate**: Tỷ lệ giao đúng hạn
- **Budget Variance**: Chênh lệch ngân sách
- **Quality Score**: Điểm chất lượng
- **Team Efficiency**: Hiệu suất team

## Troubleshooting

### Các Vấn đề Thường gặp

**1. Không thể đăng nhập**
- Kiểm tra email/password
- Xóa cache browser
- Liên hệ admin để reset password

**2. Không nhận được thông báo**
- Kiểm tra cài đặt notification
- Kiểm tra spam folder (email)
- Đảm bảo browser cho phép notifications

**3. Upload file thất bại**
- Kiểm tra kích thước file (max 10MB)
- Kiểm tra định dạng file được hỗ trợ
- Kiểm tra kết nối internet

**4. Dữ liệu không cập nhật real-time**
- Refresh trang
- Kiểm tra kết nối WebSocket
- Liên hệ admin nếu vấn đề tiếp tục

### Liên hệ Hỗ trợ
- **Email**: support@zenamanage.com
- **Phone**: +84 123 456 789
- **Help Desk**: Trong hệ thống, menu "Hỗ trợ"