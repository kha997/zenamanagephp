<?php

return [
    // Brand
    'brand_name' => 'ZenaManage',
    
    // Greeting
    'greeting' => 'Xin chào',
    
    // Navigation
    'nav' => [
        'dashboard' => 'Bảng điều khiển',
        'projects' => 'Dự án',
        'tasks' => 'Nhiệm vụ',
        'calendar' => 'Lịch',
        'team' => 'Nhóm',
        'documents' => 'Tài liệu',
        'templates' => 'Mẫu',
        'settings' => 'Cài đặt',
    ],
    
    // Notifications
    'notifications' => [
        'title' => 'Thông báo',
        'empty' => 'Không có thông báo',
        'view_all' => 'Xem tất cả thông báo',
        'new_project' => 'Dự án mới',
        'project_created' => 'Một dự án mới đã được tạo',
        'task_assigned' => 'Nhiệm vụ được giao',
        'task_assigned_message' => 'Bạn đã được giao một nhiệm vụ mới',
    ],
    
    // User Menu
    'user_menu' => [
        'profile' => 'Hồ sơ',
        'settings' => 'Cài đặt',
        'logout' => 'Đăng xuất',
    ],
    
    // Toolbar
    'search_placeholder' => 'Tìm kiếm...',
    'filters' => 'Bộ lọc',
    'clear_filters' => 'Xóa bộ lọc',
    'apply_filters' => 'Áp dụng bộ lọc',
    'export' => 'Xuất',
    'export_csv' => 'Xuất CSV',
    'export_excel' => 'Xuất Excel',
    'export_pdf' => 'Xuất PDF',
    'all' => 'Tất cả',
    
    // Focus Mode
    'focus_mode' => 'Chế độ tập trung',
    'focus' => 'Tập trung',
    'focus_mode_active' => 'Chế độ tập trung đang hoạt động',
    'focus_mode_description' => 'Giao diện tối giản để tập trung tốt hơn',
    'exit_focus_mode' => 'Thoát chế độ tập trung',
    'enter_focus_mode' => 'Vào chế độ tập trung',
    'minimal_ui' => 'Giao diện tối giản',
    
    // Rewards
    'rewards' => 'Phần thưởng',
    'rewards_active' => 'Phần thưởng đang hoạt động',
    'rewards_description' => 'Hiệu ứng chúc mừng cho nhiệm vụ hoàn thành',
    'disable_rewards' => 'Tắt phần thưởng',
    'enable_rewards' => 'Bật phần thưởng',
    'celebrations' => 'Chúc mừng',
    'congratulations' => 'Chúc mừng!',
    'great_job_task_completed' => 'Xuất sắc! Bạn đã hoàn thành công việc 🎉',
    'keep_up_great_work' => 'Tiếp tục phát huy!',
    'task_completed_successfully' => 'Hoàn thành nhiệm vụ thành công',
    
    // Feature Flags
    'feature_disabled' => 'Tính năng bị tắt',
    'feature_enabled' => 'Tính năng được bật',
    'on' => 'Bật',
    'off' => 'Tắt',
    
    // Projects
    'projects' => [
        'title' => 'Dự án',
        'search_placeholder' => 'Tìm kiếm dự án...',
        'all_status' => 'Tất cả trạng thái',
        'all_priorities' => 'Tất cả mức độ',
        'view_mode' => 'Chế độ xem',
        'table_view' => 'Bảng',
        'card_view' => 'Thẻ',
        'filters_applied' => 'Bộ lọc (:count)',
        'clear_filters' => 'Xóa bộ lọc',
        
        'status' => [
            'planning' => 'Lập kế hoạch',
            'active' => 'Đang hoạt động',
            'on_hold' => 'Tạm dừng',
            'completed' => 'Hoàn thành',
            'cancelled' => 'Đã hủy',
        ],
        
        'priority' => [
            'high' => 'Cao',
            'medium' => 'Trung bình',
            'low' => 'Thấp',
        ],
        
        'sort' => [
            'name' => 'Sắp xếp theo tên',
            'date' => 'Sắp xếp theo ngày',
            'priority' => 'Sắp xếp theo mức độ',
            'progress' => 'Sắp xếp theo tiến độ',
        ],
        
        'table' => [
            'name' => 'Tên',
            'status' => 'Trạng thái',
            'priority' => 'Mức độ',
            'progress' => 'Tiến độ',
            'team' => 'Nhóm',
            'created' => 'Ngày tạo',
            'actions' => 'Hành động',
        ],
        
        'empty' => [
            'title' => 'Không tìm thấy dự án',
            'description' => 'Bắt đầu bằng cách tạo dự án mới.',
            'action' => 'Dự án mới',
        ],
        
        'no_description' => 'Không có mô tả',
        'members' => 'thành viên',
        'unknown' => 'Không xác định',
        'progress' => 'Tiến độ',
        'view_details' => 'Xem chi tiết',
        'edit_project' => 'Chỉnh sửa dự án',
        'subtitle' => 'Quản lý và theo dõi danh mục dự án của bạn',
        'create_new' => 'Dự án mới',
        'recent_activity' => 'Hoạt động dự án gần đây',
        'activity' => [
            'created' => 'Dự án ":name" đã được tạo.',
            'empty' => 'Không có hoạt động dự án gần đây.',
        ],
        'displayed' => 'hiển thị',
        'avg_progress' => 'tiến độ trung bình',
        'success' => 'Thành công!',
        'none_yet' => 'Chưa có',
        'in_archive' => 'Trong kho lưu trữ',
        'none_archived' => 'Chưa lưu trữ',
        'view_all' => 'Xem tất cả',
        'manage_active' => 'Quản lý đang hoạt động',
        'view_completed' => 'Xem đã hoàn thành',
        'view_archived' => 'Xem đã lưu trữ',
    ],
    
    // Tasks
    'tasks' => [
        'title' => 'Nhiệm vụ',
        'search_placeholder' => 'Tìm kiếm nhiệm vụ...',
        'all_status' => 'Tất cả trạng thái',
        'all_projects' => 'Tất cả dự án',
        'all_assignees' => 'Tất cả người được giao',
        
        'status' => [
            'pending' => 'Chờ xử lý',
            'in_progress' => 'Đang thực hiện',
            'completed' => 'Hoàn thành',
            'on_hold' => 'Tạm dừng',
            'cancelled' => 'Đã hủy',
        ],
        
        'empty' => [
            'title' => 'Không tìm thấy nhiệm vụ',
            'description' => 'Bắt đầu bằng cách tạo nhiệm vụ mới.',
            'action' => 'Nhiệm vụ mới',
        ],
    ],
    
    // Common
    'common' => [
        'dismiss' => 'Đóng',
        'loading' => 'Đang tải...',
        'save' => 'Lưu',
        'cancel' => 'Hủy',
        'delete' => 'Xóa',
        'edit' => 'Chỉnh sửa',
        'view' => 'Xem',
        'create' => 'Tạo',
        'update' => 'Cập nhật',
        'search' => 'Tìm kiếm',
        'filter' => 'Lọc',
        'sort' => 'Sắp xếp',
        'actions' => 'Hành động',
    ],
    
    // Pagination
    'pagination' => [
        'previous' => 'Trước',
        'next' => 'Tiếp',
        'showing' => 'Hiển thị',
        'to' => 'đến',
        'of' => 'trong tổng số',
        'results' => 'kết quả',
    ],
    
    // KPI Labels
    'kpi' => [
        'total_projects' => 'Tổng dự án',
        'active_projects' => 'Dự án đang hoạt động',
        'completed_projects' => 'Đã hoàn thành',
        'archived_projects' => 'Đã lưu trữ',
        'total_tasks' => 'Tổng nhiệm vụ',
        'pending_tasks' => 'Nhiệm vụ chờ xử lý',
        'completed_tasks' => 'Nhiệm vụ đã hoàn thành',
        'overdue_tasks' => 'Nhiệm vụ quá hạn',
    ],
];
