<?php declare(strict_types=1);

namespace Src\RBAC\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasSoftDeletes;
use Src\Foundation\Traits\HasAuditLog;

/**
 * Model UserRoleProject - Quản lý vai trò của user trong project cụ thể
 * 
 * @property string $id
 * @property string $project_id ID dự án (ULID)
 * @property string $user_id ID người dùng (ULID)
 * @property string $role_id ID vai trò (ULID)
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at Soft delete timestamp
 */
class UserRoleProject extends Model
{
    use HasUlids, HasTimestamps, HasSoftDeletes, HasAuditLog;

    protected $table = 'project_user_roles';
    
    /**
     * Kiểu dữ liệu của khóa chính
     */
    protected $keyType = 'string';

    /**
     * Tắt auto increment cho khóa chính
     */
    public $incrementing = false;
    
    protected $fillable = [
        'project_id',
        'user_id',
        'role_id'
    ];

    protected $casts = [
        'created_at' => 'datetime:c',
        'updated_at' => 'datetime:c',
        'deleted_at' => 'datetime:c'
    ];

    /**
     * Relationship: Thuộc về project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(\Src\CoreProject\Models\Project::class);
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
     * Scope: Lọc theo project
     */
    public function scopeForProject($query, string $projectId)
    {
        return $query->where('project_id', $projectId);
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

    /**
     * Scope: Chỉ lấy các assignment đang hoạt động (chưa bị soft delete)
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
    }

    /**
     * Scope: Lọc theo project và user
     */
    public function scopeForProjectAndUser($query, string $projectId, string $userId)
    {
        return $query->where('project_id', $projectId)
                    ->where('user_id', $userId);
    }
}