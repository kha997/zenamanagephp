<?php

return [
    'errors' => [
        'E400.BAD_REQUEST' => 'Yêu cầu không hợp lệ - định dạng hoặc tham số không đúng',
        'E401.AUTHENTICATION' => 'Yêu cầu xác thực hoặc xác thực thất bại',
        'E403.AUTHORIZATION' => 'Không đủ quyền truy cập tài nguyên',
        'E404.NOT_FOUND' => 'Không tìm thấy tài nguyên được yêu cầu',
        'E409.CONFLICT' => 'Xung đột tài nguyên - tài nguyên đã tồn tại hoặc đang được sử dụng',
        'E422.VALIDATION' => 'Xác thực thất bại - dữ liệu đầu vào không hợp lệ',
        'E429.RATE_LIMIT' => 'Vượt quá giới hạn tốc độ - quá nhiều yêu cầu',
        'E500.SERVER_ERROR' => 'Lỗi máy chủ nội bộ',
        'E503.SERVICE_UNAVAILABLE' => 'Dịch vụ tạm thời không khả dụng',
    ],
    
    'messages' => [
        'validation_failed' => 'Xác thực thất bại',
        'authentication_required' => 'Yêu cầu xác thực',
        'insufficient_permissions' => 'Không đủ quyền',
        'resource_not_found' => 'Không tìm thấy tài nguyên',
        'resource_conflict' => 'Xung đột tài nguyên',
        'rate_limit_exceeded' => 'Vượt quá giới hạn tốc độ',
        'internal_server_error' => 'Lỗi máy chủ nội bộ',
        'service_unavailable' => 'Dịch vụ tạm thời không khả dụng',
    ],
    
    'project_manager' => [
        'role_required' => 'Yêu cầu vai trò quản lý dự án',
        'stats_retrieval_failed' => 'Không thể lấy thống kê bảng điều khiển quản lý dự án',
        'timeline_retrieval_failed' => 'Không thể lấy thời gian dự án',
    ],
    
    'validation' => [
        'required' => 'Trường :attribute là bắt buộc.',
        'email' => 'Trường :attribute phải là địa chỉ email hợp lệ.',
        'min' => 'Trường :attribute phải có ít nhất :min ký tự.',
        'max' => 'Trường :attribute không được vượt quá :max ký tự.',
        'unique' => 'Trường :attribute đã được sử dụng.',
        'exists' => 'Trường :attribute được chọn không hợp lệ.',
    ],
];
