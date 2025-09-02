<?php
declare(strict_types=1);

namespace Src\Foundation\Helpers;

/**
 * Helper functions cho validation
 */
class ValidationHelper {
    /**
     * Kiểm tra email hợp lệ
     * 
     * @param string $email
     * @return bool
     */
    public static function isValidEmail(string $email): bool {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Kiểm tra số điện thoại Việt Nam
     * 
     * @param string $phone
     * @return bool
     */
    public static function isValidVietnamesePhone(string $phone): bool {
        // Loại bỏ khoảng trắng và dấu gạch ngang
        $phone = preg_replace('/[\s-]/', '', $phone);
        
        // Kiểm tra format số điện thoại VN
        return preg_match('/^(\+84|84|0)(3|5|7|8|9)[0-9]{8}$/', $phone) === 1;
    }
    
    /**
     * Kiểm tra mật khẩu mạnh
     * 
     * @param string $password
     * @param int $minLength
     * @return array
     */
    public static function validatePasswordStrength(string $password, int $minLength = 8): array {
        $errors = [];
        
        if (strlen($password) < $minLength) {
            $errors[] = "Mật khẩu phải có ít nhất {$minLength} ký tự";
        }
        
        if (!preg_match('/[a-z]/', $password)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất 1 chữ thường';
        }
        
        if (!preg_match('/[A-Z]/', $password)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất 1 chữ hoa';
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất 1 số';
        }
        
        if (!preg_match('/[^a-zA-Z0-9]/', $password)) {
            $errors[] = 'Mật khẩu phải chứa ít nhất 1 ký tự đặc biệt';
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'strength' => self::calculatePasswordStrength($password)
        ];
    }
    
    /**
     * Tính độ mạnh của mật khẩu (0-100)
     * 
     * @param string $password
     * @return int
     */
    private static function calculatePasswordStrength(string $password): int {
        $score = 0;
        
        // Độ dài
        $score += min(strlen($password) * 4, 25);
        
        // Chữ thường
        if (preg_match('/[a-z]/', $password)) $score += 5;
        
        // Chữ hoa
        if (preg_match('/[A-Z]/', $password)) $score += 5;
        
        // Số
        if (preg_match('/[0-9]/', $password)) $score += 5;
        
        // Ký tự đặc biệt
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $score += 10;
        
        // Đa dạng ký tự
        $uniqueChars = count(array_unique(str_split($password)));
        $score += min($uniqueChars * 2, 20);
        
        return min($score, 100);
    }
    
    /**
     * Kiểm tra ULID hợp lệ
     * 
     * @param string $ulid
     * @return bool
     */
    public static function isValidULID(string $ulid): bool {
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $ulid) === 1;
    }
    
    /**
     * Kiểm tra định dạng thời gian ISO 8601
     * 
     * @param string $datetime
     * @return bool
     */
    public static function isValidISO8601(string $datetime): bool {
        $pattern = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d{3})?Z$/';
        return preg_match($pattern, $datetime) === 1;
    }
}