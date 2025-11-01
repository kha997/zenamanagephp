<?php

return [
    'validation_error' => 'Lỗi xác thực',
    'unauthorized' => 'Truy cập không được phép',
    'forbidden' => 'Truy cập bị cấm',
    'not_found' => 'Không tìm thấy tài nguyên',
    'conflict' => 'Xung đột tài nguyên',
    'unprocessable_entity' => 'Thực thể không thể xử lý',
    'rate_limited' => 'Quá nhiều yêu cầu',
    'internal_error' => 'Lỗi máy chủ nội bộ',
    'service_unavailable' => 'Dịch vụ tạm thời không khả dụng',
    'unknown_error' => 'Đã xảy ra lỗi không xác định',
    
    'validation' => [
        'required' => 'Trường :attribute là bắt buộc',
        'email' => 'Trường :attribute phải là địa chỉ email hợp lệ',
        'unique' => 'Trường :attribute đã được sử dụng',
        'min' => 'Trường :attribute phải có ít nhất :min ký tự',
        'max' => 'Trường :attribute không được vượt quá :max ký tự',
    ],
    
    'auth' => [
        'failed' => 'Thông tin đăng nhập không chính xác',
        'throttle' => 'Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau :seconds giây',
        'token_expired' => 'Token xác thực đã hết hạn',
        'token_invalid' => 'Token xác thực không hợp lệ',
    ],
    
    'tenant' => [
        'not_found' => 'Không tìm thấy tenant',
        'access_denied' => 'Truy cập bị từ chối cho tenant này',
        'quota_exceeded' => 'Đã vượt quá hạn mức tenant',
    ],
    
    'project' => [
        'not_found' => 'Không tìm thấy dự án',
        'access_denied' => 'Truy cập bị từ chối cho dự án này',
        'already_exists' => 'Dự án với tên này đã tồn tại',
    ],
    
    'task' => [
        'not_found' => 'Không tìm thấy nhiệm vụ',
        'access_denied' => 'Truy cập bị từ chối cho nhiệm vụ này',
        'invalid_status' => 'Trạng thái nhiệm vụ không hợp lệ',
    ],
];
