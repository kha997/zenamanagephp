<?php declare(strict_types=1);

namespace Src\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasAuditLog;

/**
 * Model UserRoleSystem - Quản lý vai trò hệ thống của user
 * 
 * @property string $user_id ID người dùng (ULID)
 * @property string $role_id ID vai trò (ULID)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserRoleSystem extends Model
{
    use HasTimestamps, HasAuditLog;

    protected $table = 'system_user_roles';
    
    /**
     * Composite primary key
     */
    protected $primaryKey = ['user_id', 'role_id'];
    
    /**
     * Tắt auto increment vì sử dụng composite key
     */
    public $incrementing = false;
    
    /**
     * Kiểu dữ liệu của khóa chính
     */
    protected $keyType = 'string';
    
    protected $fillable = [
        'user_id',
        'role_id'
    ];

    protected $casts = [
        'created_at' => 'datetime:c',
        'updated_at' => 'datetime:c'
    ];

    /**
     * Override getKeyName để hỗ trợ composite key
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Override getKey để hỗ trợ composite key
     */
    public function getKey()
    {
        $keys = [];
        foreach ($this->getKeyName() as $key) {
            $keys[$key] = $this->getAttribute($key);
        }
        return $keys;
    }

    /**
     * Override setKeysForSaveQuery để hỗ trợ composite key
     */
    protected function setKeysForSaveQuery($query)
    {
        foreach ($this->getKeyName() as $key) {
            $query->where($key, '=', $this->getAttribute($key));
        }
        return $query;
    }

    /**
     * Relationship: Thuộc về user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    /**
     * Relationship: Thuộc về role
     */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    /**
     * Scope: Lọc theo user
     */
    public function scopeForUser($query, string $userId)
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Scope: Lọc theo role
     */
    public function scopeForRole($query, string $roleId)
    {
        return $query->where('role_id', $roleId);
    }
}