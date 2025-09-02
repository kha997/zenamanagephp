<?php
declare(strict_types=1);

namespace Src\Foundation\Traits;

use Src\Foundation\Foundation;

/**
 * Trait để quản lý visibility
 */
trait HasVisibility {
    /**
     * Boot trait
     * 
     * @return void
     */
    protected static function bootHasVisibility(): void {
        static::creating(function ($model) {
            if (empty($model->visibility)) {
                $model->visibility = 'internal';
            }
        });
    }
    
    /**
     * Kiểm tra có thể hiển thị cho client không
     * 
     * @return bool
     */
    public function isVisibleToClient(): bool {
        return $this->visibility === 'client' && 
               ($this->client_approved ?? false) === true;
    }
    
    /**
     * Đánh dấu được phê duyệt cho client
     * 
     * @return void
     */
    public function approveForClient(): void {
        $this->visibility = 'client';
        $this->client_approved = true;
    }
    
    /**
     * Hủy phê duyệt cho client
     * 
     * @return void
     */
    public function revokeClientApproval(): void {
        $this->client_approved = false;
    }
    
    /**
     * Scope để lọc nội dung hiển thị cho client
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeVisibleToClient($query) {
        return $query->where('visibility', 'client')
                    ->where('client_approved', true);
    }
    
    /**
     * Scope để lọc nội dung internal
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeInternal($query) {
        return $query->where('visibility', 'internal');
    }
    
    /**
     * Scope để lọc theo visibility
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $visibility
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWithVisibility($query, string $visibility) {
        if (!Foundation::isValidVisibility($visibility)) {
            throw new \InvalidArgumentException("Invalid visibility: {$visibility}");
        }
        
        return $query->where('visibility', $visibility);
    }
}