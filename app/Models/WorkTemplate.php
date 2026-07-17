<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkTemplate extends Model
{
    use HasUlids, HasFactory, TenantScope, SoftDeletes;

    protected $table = 'work_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(WorkTemplateVersion::class)->orderByDesc('created_at');
    }

    public function publishedVersions(): HasMany
    {
        return $this->versions()->whereNotNull('published_at');
    }

    public function draftVersions(): HasMany
    {
        return $this->versions()->whereNull('published_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
