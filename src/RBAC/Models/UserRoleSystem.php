<?php declare(strict_types=1);

namespace Src\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasSoftDeletes;

/**
 * Model UserRoleSystem - Model cho bảng system_user_roles
 * 
 * @property string $user_id ID người dùng (ULID)
 * @property string $role_id ID vai trò (ULID)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class UserRoleSystem extends Model
{
    use HasTimestamps, HasSoftDeletes;

    protected $table = 'system_user_roles'; // Thay đổi từ 'user_roles_system'
    
    // Không sử dụng ULID primary key vì bảng này sử dụng composite primary key
    protected $primaryKey = ['user_id', 'role_id'];
    public $incrementing = false;
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
     * Scope: Chỉ lấy records active (không bị soft delete)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Override getKeyName để hỗ trợ composite primary key
     */
    public function getKeyName()
    {
        return $this->primaryKey;
    }

    /**
     * Override setKeysForSaveQuery để hỗ trợ composite primary key
     */
    protected function setKeysForSaveQuery($query)
    {
        $keys = $this->getKeyName();
        if (!is_array($keys)) {
            return parent::setKeysForSaveQuery($query);
        }

        foreach ($keys as $keyName) {
            $query->where($keyName, '=', $this->getKeyForSaveQuery($keyName));
        }

        return $query;
    }

    /**
     * Override getKeyForSaveQuery để hỗ trợ composite primary key
     */
    protected function getKeyForSaveQuery($keyName = null)
    {
        if (is_null($keyName)) {
            $keyName = $this->getKeyName();
        }

        if (isset($this->original[$keyName])) {
            return $this->original[$keyName];
        }

        return $this->getAttribute($keyName);
    }
}