<?php
declare(strict_types=1);

namespace Src\Foundation\Helpers;

/**
 * Helper functions cho xử lý array
 */
class ArrayHelper {
    /**
     * Lấy giá trị từ array với dot notation
     * 
     * @param array $array
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public static function get(array $array, string $key, $default = null) {
        if (isset($array[$key])) {
            return $array[$key];
        }
        
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }
        
        return $array;
    }
    
    /**
     * Set giá trị vào array với dot notation
     * 
     * @param array $array
     * @param string $key
     * @param mixed $value
     * @return array
     */
    public static function set(array &$array, string $key, $value): array {
        $keys = explode('.', $key);
        $current = &$array;
        
        foreach ($keys as $key) {
            if (!isset($current[$key]) || !is_array($current[$key])) {
                $current[$key] = [];
            }
            $current = &$current[$key];
        }
        
        $current = $value;
        
        return $array;
    }
    
    /**
     * Kiểm tra array có chứa tất cả các key không
     * 
     * @param array $array
     * @param array $keys
     * @return bool
     */
    public static function hasKeys(array $array, array $keys): bool {
        foreach ($keys as $key) {
            if (!array_key_exists($key, $array)) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Lọc array chỉ giữ lại các key được chỉ định
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function only(array $array, array $keys): array {
        return array_intersect_key($array, array_flip($keys));
    }
    
    /**
     * Loại bỏ các key được chỉ định khỏi array
     * 
     * @param array $array
     * @param array $keys
     * @return array
     */
    public static function except(array $array, array $keys): array {
        return array_diff_key($array, array_flip($keys));
    }
    
    /**
     * Flatten array đa chiều thành array 1 chiều
     * 
     * @param array $array
     * @param string $prefix
     * @return array
     */
    public static function flatten(array $array, string $prefix = ''): array {
        $result = [];
        
        foreach ($array as $key => $value) {
            $newKey = $prefix === '' ? $key : $prefix . '.' . $key;
            
            if (is_array($value)) {
                $result = array_merge($result, self::flatten($value, $newKey));
            } else {
                $result[$newKey] = $value;
            }
        }
        
        return $result;
    }
}