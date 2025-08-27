<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\Foundation;
use Illuminate\Database\Eloquent\Builder;
use Carbon\Carbon;

/**
 * Trait để triển khai soft delete theo chuẩn PHẦN 0
 * Sử dụng deleted_at (nullable) theo ISO 8601 UTC
 */
trait HasSoftDeletes {
    /**
     * Boot trait để tự động áp dụng global scope
     * 
     * @return void
     */
    protected static function bootHasSoftDeletes(): void {
        static::addGlobalScope('soft_delete', function (Builder $builder) {
            $builder->whereNull($builder->getModel()->getQualifiedDeletedAtColumn());
        });
    }
    
    /**
     * Lấy tên cột deleted_at
     * 
     * @return string
     */
    public function getDeletedAtColumn(): string {
        return 'deleted_at';
    }
    
    /**
     * Lấy tên cột deleted_at với table prefix
     * 
     * @return string
     */
    public function getQualifiedDeletedAtColumn(): string {
        return $this->qualifyColumn($this->getDeletedAtColumn());
    }
    
    /**
     * Kiểm tra xem model có bị soft delete không
     * 
     * @return bool
     */
    public function trashed(): bool {
        return !is_null($this->{$this->getDeletedAtColumn()});
    }
    
    /**
     * Soft delete model
     * 
     * @return bool|null
     */
    public function delete(): ?bool {
        if ($this->fireModelEvent('deleting') === false) {
            return false;
        }
        
        $this->{$this->getDeletedAtColumn()} = Foundation::getCurrentTime();
        $result = $this->save();
        
        if ($result) {
            $this->fireModelEvent('deleted', false);
        }
        
        return $result;
    }
    
    /**
     * Force delete model (xóa vĩnh viễn)
     * 
     * @return bool|null
     */
    public function forceDelete(): ?bool {
        if ($this->fireModelEvent('forceDeleting') === false) {
            return false;
        }
        
        $result = $this->newQueryWithoutScopes()->where($this->getKeyName(), $this->getKey())->forceDelete();
        
        if ($result) {
            $this->fireModelEvent('forceDeleted', false);
        }
        
        return $result;
    }
    
    /**
     * Restore model từ soft delete
     * 
     * @return bool
     */
    public function restore(): bool {
        if ($this->fireModelEvent('restoring') === false) {
            return false;
        }
        
        $this->{$this->getDeletedAtColumn()} = null;
        $result = $this->save();
        
        if ($result) {
            $this->fireModelEvent('restored', false);
        }
        
        return $result;
    }
    
    /**
     * Query scope để lấy cả records đã bị soft delete
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeWithTrashed(Builder $query): Builder {
        return $query->withoutGlobalScope('soft_delete');
    }
    
    /**
     * Query scope để chỉ lấy records đã bị soft delete
     * 
     * @param Builder $query
     * @return Builder
     */
    public function scopeOnlyTrashed(Builder $query): Builder {
        return $query->withoutGlobalScope('soft_delete')
                    ->whereNotNull($this->getQualifiedDeletedAtColumn());
    }
    
    /**
     * Accessor cho deleted_at để trả về Carbon instance
     * 
     * @param mixed $value
     * @return Carbon|null
     */
    public function getDeletedAtAttribute($value): ?Carbon {
        return $value ? Carbon::parse($value) : null;
    }
    
    /**
     * Mutator cho deleted_at để đảm bảo format ISO 8601 UTC
     * 
     * @param mixed $value
     * @return void
     */
    public function setDeletedAtAttribute($value): void {
        if ($value === null) {
            $this->attributes[$this->getDeletedAtColumn()] = null;
        } else {
            $this->attributes[$this->getDeletedAtColumn()] = Foundation::getCurrentTime();
        }
    }
}