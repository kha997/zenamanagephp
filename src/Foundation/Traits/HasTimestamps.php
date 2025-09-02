<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\Foundation;

/**
 * Trait để tự động quản lý timestamps theo chuẩn ISO 8601 UTC
 */
trait HasTimestamps {
    /**
     * Boot trait
     * 
     * @return void
     */
    protected static function bootHasTimestamps(): void {
        static::creating(function ($model) {
            $now = Foundation::getCurrentTime();
            $model->created_at = $now;
            $model->updated_at = $now;
        });
        
        static::updating(function ($model) {
            $model->updated_at = Foundation::getCurrentTime();
        });
    }
    
    /**
     * Lấy thời gian tạo dạng Carbon
     * 
     * @return \Carbon\Carbon|null
     */
    public function getCreatedAtAttribute($value) {
        return $value ? \Carbon\Carbon::parse($value) : null;
    }
    
    /**
     * Lấy thời gian cập nhật dạng Carbon
     * 
     * @return \Carbon\Carbon|null
     */
    public function getUpdatedAtAttribute($value) {
        return $value ? \Carbon\Carbon::parse($value) : null;
    }
}