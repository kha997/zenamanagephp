<?php

return [
    // Page Titles
    'title' => 'Báo giá',
    'subtitle' => 'Quản lý báo giá dự án và theo dõi trạng thái',
    
    // Actions
    'create_quote' => 'Tạo báo giá',
    'create_first_quote' => 'Tạo báo giá đầu tiên',
    'edit' => 'Sửa',
    'view' => 'Xem',
    'delete' => 'Xóa',
    'send_quote' => 'Gửi báo giá',
    'duplicate_quote' => 'Sao chép báo giá',
    'convert_to_project' => 'Chuyển thành dự án',
    
    // Quote Information
    'quote' => 'Báo giá',
    'quote_list' => 'Danh sách báo giá',
    'client' => 'Khách hàng',
    'project' => 'Dự án',
    'type' => 'Loại',
    'status' => 'Trạng thái',
    'amount' => 'Số tiền',
    'valid_until' => 'Có hiệu lực đến',
    'created' => 'Ngày tạo',
    'actions' => 'Hành động',
    'created_on' => 'Tạo vào',
    
    // Quote Types
    'design' => 'Thiết kế',
    'construction' => 'Xây dựng',
    'consultation' => 'Tư vấn',
    'maintenance' => 'Bảo trì',
    
    // Quote Status
    'draft' => 'Bản nháp',
    'sent' => 'Đã gửi',
    'viewed' => 'Đã xem',
    'accepted' => 'Đã chấp nhận',
    'rejected' => 'Đã từ chối',
    'expired' => 'Đã hết hạn',
    
    // Empty States
    'no_quotes' => 'Không tìm thấy báo giá',
    'no_quotes_description' => 'Bắt đầu tạo báo giá cho khách hàng của bạn.',
    
    // Form Fields
    'title' => 'Tiêu đề báo giá',
    'description' => 'Mô tả',
    'client_id' => 'Khách hàng',
    'project_id' => 'Dự án',
    'type' => 'Loại báo giá',
    'amount' => 'Số tiền báo giá',
    'valid_until' => 'Có hiệu lực đến',
    'terms' => 'Điều khoản & Điều kiện',
    'notes' => 'Ghi chú',
    
    // Statistics
    'total_quotes' => 'Tổng báo giá',
    'draft_quotes' => 'Báo giá nháp',
    'sent_quotes' => 'Báo giá đã gửi',
    'accepted_quotes' => 'Báo giá đã chấp nhận',
    'total_value' => 'Tổng giá trị',
    'conversion_rate' => 'Tỷ lệ chuyển đổi',
    'avg_response_time' => 'Thời gian phản hồi trung bình',
    
    // Messages
    'quote_created' => 'Báo giá đã được tạo thành công',
    'quote_updated' => 'Báo giá đã được cập nhật thành công',
    'quote_deleted' => 'Báo giá đã được xóa thành công',
    'quote_sent' => 'Báo giá đã được gửi thành công',
    'quote_not_found' => 'Không tìm thấy báo giá',
    'quote_expired' => 'Báo giá đã hết hạn',
    
    // Validation
    'title_required' => 'Tiêu đề báo giá là bắt buộc',
    'client_required' => 'Khách hàng là bắt buộc',
    'amount_required' => 'Số tiền báo giá là bắt buộc',
    'amount_numeric' => 'Số tiền báo giá phải là số',
    'valid_until_required' => 'Ngày hết hạn là bắt buộc',
    'valid_until_future' => 'Ngày hết hạn phải trong tương lai',
    
    // Filters
    'filter_by_status' => 'Lọc theo trạng thái',
    'filter_by_type' => 'Lọc theo loại',
    'filter_by_client' => 'Lọc theo khách hàng',
    'all_statuses' => 'Tất cả trạng thái',
    'all_types' => 'Tất cả loại',
    'all_clients' => 'Tất cả khách hàng',
    'expiring_soon' => 'Sắp hết hạn',
    
    // Export
    'export_quotes' => 'Xuất báo giá',
    'export_csv' => 'Xuất CSV',
    'export_excel' => 'Xuất Excel',
    'export_pdf' => 'Xuất PDF',
    
    // Email Templates
    'email_subject' => 'Báo giá từ :company',
    'email_greeting' => 'Kính gửi :client_name',
    'email_body' => 'Vui lòng xem báo giá đính kèm cho :project_title.',
    'email_footer' => 'Cảm ơn bạn đã hợp tác!',
];
