<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Src\Foundation\Traits\HasTimestamps;
use Src\Foundation\Traits\HasSoftDeletes;
use Src\Foundation\Traits\HasAuditLog;

/**
 * Model Tenant - Quản lý công ty/tổ chức trong hệ thống multi-tenant
 * 
 * @property string $id ULID primary key
 * @property string $name Tên công ty
 * @property string|null $domain Domain của công ty
 * @property string|null $database_name Tên database riêng (nếu có)
 * @property array|null $settings Cài đặt riêng của tenant
 * @property bool $is_active Trạng thái hoạt động
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property \Carbon\Carbon|null $deleted_at
 */
class Tenant extends Model
{
    use HasFactory, HasUlids, HasTimestamps, HasSoftDeletes, HasAuditLog;

    /**
     * Cấu hình ULID cho primary key
     */
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'domain',
        'database_name',
        'settings',
        'is_active'
    ];

    protected $casts = [
        'settings' => 'array',
        'is_active' => 'boolean'
    ];

    protected $attributes = [
        'is_active' => true
    ];

    /**
     * Relationship: Tenant có nhiều users
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Relationship: Tenant có nhiều projects
     */
    public function projects(): HasMany
    {
        return $this->hasMany(\Src\CoreProject\Models\Project::class);
    }

    /**
     * Kiểm tra tenant có active không
     */
    public function isActive(): bool
    {
        return $this->is_active;
    }

    /**
     * Lấy setting theo key
     */
    public function getSetting(string $key, $default = null)
    {
        return data_get($this->settings, $key, $default);
    }

    /**
     * Cập nhật setting
     */
    public function updateSetting(string $key, $value): void
    {
        $settings = $this->settings ?? [];
        data_set($settings, $key, $value);
        $this->update(['settings' => $settings]);
    }

    /**
     * Scope: Chỉ lấy tenants active
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}