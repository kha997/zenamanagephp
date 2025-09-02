<?php
declare(strict_types=1);

namespace Src\Foundation\Helpers;

/**
 * Helper functions cho xử lý string
 */
class StringHelper {
    /**
     * Chuyển string thành slug (URL-friendly)
     * 
     * @param string $text
     * @param string $separator
     * @return string
     */
    public static function slug(string $text, string $separator = '-'): string {
        // Chuyển về lowercase và loại bỏ dấu tiếng Việt
        $text = self::removeVietnameseAccents(strtolower($text));
        
        // Thay thế các ký tự không phải chữ cái, số bằng separator
        $text = preg_replace('/[^a-z0-9]+/', $separator, $text);
        
        // Loại bỏ separator ở đầu và cuối
        return trim($text, $separator);
    }
    
    /**
     * Loại bỏ dấu tiếng Việt
     * 
     * @param string $text
     * @return string
     */
    public static function removeVietnameseAccents(string $text): string {
        $accents = [
            'à', 'á', 'ạ', 'ả', 'ã', 'â', 'ầ', 'ấ', 'ậ', 'ẩ', 'ẫ', 'ă', 'ằ', 'ắ', 'ặ', 'ẳ', 'ẵ',
            'è', 'é', 'ẹ', 'ẻ', 'ẽ', 'ê', 'ề', 'ế', 'ệ', 'ể', 'ễ',
            'ì', 'í', 'ị', 'ỉ', 'ĩ',
            'ò', 'ó', 'ọ', 'ỏ', 'õ', 'ô', 'ồ', 'ố', 'ộ', 'ổ', 'ỗ', 'ơ', 'ờ', 'ớ', 'ợ', 'ở', 'ỡ',
            'ù', 'ú', 'ụ', 'ủ', 'ũ', 'ư', 'ừ', 'ứ', 'ự', 'ử', 'ữ',
            'ỳ', 'ý', 'ỵ', 'ỷ', 'ỹ',
            'đ'
        ];
        
        $replacements = [
            'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a', 'a',
            'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e', 'e',
            'i', 'i', 'i', 'i', 'i',
            'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o', 'o',
            'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u', 'u',
            'y', 'y', 'y', 'y', 'y',
            'd'
        ];
        
        return str_replace($accents, $replacements, $text);
    }
    
    /**
     * Cắt chuỗi và thêm dấu ...
     * 
     * @param string $text
     * @param int $length
     * @param string $suffix
     * @return string
     */
    public static function truncate(string $text, int $length, string $suffix = '...'): string {
        if (mb_strlen($text) <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length) . $suffix;
    }
    
    /**
     * Chuyển string thành camelCase
     * 
     * @param string $text
     * @return string
     */
    public static function camelCase(string $text): string {
        $text = str_replace(['-', '_'], ' ', $text);
        $text = ucwords($text);
        $text = str_replace(' ', '', $text);
        return lcfirst($text);
    }
    
    /**
     * Chuyển string thành snake_case
     * 
     * @param string $text
     * @return string
     */
    public static function snakeCase(string $text): string {
        $text = preg_replace('/([a-z])([A-Z])/', '$1_$2', $text);
        return strtolower($text);
    }
    
    /**
     * Tạo mật khẩu ngẫu nhiên
     * 
     * @param int $length
     * @param bool $includeSymbols
     * @return string
     */
    public static function randomPassword(int $length = 12, bool $includeSymbols = true): string {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        
        if ($includeSymbols) {
            $chars .= '!@#$%^&*()_+-=[]{}|;:,.<>?';
        }
        
        return substr(str_shuffle(str_repeat($chars, $length)), 0, $length);
    }
}