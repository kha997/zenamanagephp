<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Model Permission - Quản lý quyền hạn trong hệ thống
 * 
 * @property string $id
 * @property string $code Mã quyền (e.g., task.create)
 * @property string $module Module chứa quyền
 * @property string $action Hành động của quyền
 * @property string|null $description Mô tả quyền
 */
class Permission extends Model
{
    use HasUlids, HasFactory;

    protected $table = 'permissions';
    protected $primaryKey = 'id';
    
    /**
     * Kiểu dữ liệu của khóa chính
     */
    protected $keyType = 'string';

    /**
     * Tắt auto increment cho khóa chính
     */
    public $incrementing = false;
    
    protected $fillable = [
        'code',
        'name',
        'module',
        'action',
        'description',
    ];

    /**
     * Quan hệ với roles
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'role_permissions',
            'permission_id',
            'role_id',
            'id',
            'id'
        )->withPivot(['allow_override'])
          ->withTimestamps();
    }

    /**
     * Tạo permission code từ module và action
     */
    public static function generateCode(string $module, string $action): string
    {
        return strtolower($module) . '.' . strtolower($action);
    }

    /**
     * Scope: Lọc theo module
     */
    public function scopeByModule($query, string $module)
    {
        return $query->where('module', $module);
    }

    /**
     * Scope: Lọc theo action
     */
    public function scopeByAction($query, string $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Boot method để tự động tạo code
     */
    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($permission) {
            if (empty($permission->code)) {
                $permission->code = self::generateCode(
                    $permission->module, 
                    $permission->action
                );
            }
        });
    }
}
