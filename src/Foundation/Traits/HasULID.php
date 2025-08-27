<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\Foundation;

/**
 * Trait để tự động tạo ULID cho model
 */
trait HasULID {
    /**
     * Boot trait
     * 
     * @return void
     */
    protected static function bootHasULID(): void {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Foundation::generateULID();
            }
        });
    }
    
    /**
     * Chỉ định key type là string
     * 
     * @return string
     */
    public function getKeyType(): string {
        return 'string';
    }
    
    /**
     * Tắt auto increment
     * 
     * @return bool
     */
    public function getIncrementing(): bool {
        return false;
    }
}