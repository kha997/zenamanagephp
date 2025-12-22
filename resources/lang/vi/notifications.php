<?php

return [
    // Email Templates
    'task_completed_title' => 'Nhiệm vụ đã hoàn thành',
    'task_completed_greeting' => 'Xin chào :name,',
    'task_details' => 'Chi tiết nhiệm vụ',
    'task_title' => 'Tiêu đề nhiệm vụ',
    'project' => 'Dự án',
    'completed_by' => 'Hoàn thành bởi',
    'completed_at' => 'Hoàn thành lúc',
    'view_task' => 'Xem nhiệm vụ',
    'view_project' => 'Xem dự án',
    
    'quote_sent_title' => 'Báo giá đã được gửi',
    'quote_sent_greeting' => 'Kính gửi :name,',
    'quote_details' => 'Chi tiết báo giá',
    'quote_number' => 'Số báo giá',
    'project_type' => 'Loại dự án',
    'total_amount' => 'Tổng số tiền',
    'valid_until' => 'Có hiệu lực đến',
    'view_quote' => 'Xem báo giá',
    'download_pdf' => 'Tải PDF',
    
    'client_created_title' => 'Khách hàng mới được tạo',
    'client_created_greeting' => 'Xin chào :name,',
    'client_details' => 'Chi tiết khách hàng',
    'client_name' => 'Tên khách hàng',
    'client_email' => 'Email',
    'client_phone' => 'Số điện thoại',
    'client_type' => 'Loại khách hàng',
    'potential_client' => 'Khách hàng tiềm năng',
    'signed_client' => 'Khách hàng đã ký hợp đồng',
    'created_by' => 'Tạo bởi',
    'view_client' => 'Xem khách hàng',
    'view_all_clients' => 'Xem tất cả khách hàng',
    
    'email_footer' => 'Cảm ơn bạn đã sử dụng ZenaManage.',
    
    // UI Labels
    'notifications' => 'Thông báo',
    'mark_all_read' => 'Đánh dấu tất cả đã đọc',
    'no_notifications' => 'Không có thông báo',
    'view_all' => 'Xem tất cả thông báo',
    'email_subject' => 'Thông báo: :type',
    
    // Message Templates
    'task_completed_message' => 'Nhiệm vụ ":task_title" trong dự án ":project_name" đã được hoàn thành.',
    'quote_sent_message' => 'Báo giá ":quote_number" đã được gửi đến ":client_name".',
    'client_created_message' => 'Khách hàng mới ":client_name" đã được tạo bởi ":created_by".',
    
    // Notification Types
    'notification_types' => [
        'task_completed' => 'Nhiệm vụ hoàn thành',
        'quote_sent' => 'Báo giá đã gửi',
        'client_created' => 'Khách hàng mới',
        'project_updated' => 'Dự án cập nhật',
        'deadline_approaching' => 'Sắp đến hạn',
    ],
    
    // Notification Channels
    'channels' => [
        'email' => 'Email',
        'in_app' => 'Trong ứng dụng',
        'sms' => 'SMS',
    ],
    
    // Notification Priorities
    'priorities' => [
        'low' => 'Thấp',
        'medium' => 'Trung bình',
        'high' => 'Cao',
        'urgent' => 'Khẩn cấp',
    ],
    
    // Notification Status
    'status' => [
        'unread' => 'Chưa đọc',
        'read' => 'Đã đọc',
        'archived' => 'Đã lưu trữ',
    ],
];
