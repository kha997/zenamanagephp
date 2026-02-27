<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliverableTemplate extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'deliverable_templates';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_template_id',
        'code',
        'name',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(DeliverableTemplateVersion::class);
    }

    public function workTemplate(): BelongsTo
    {
        return $this->belongsTo(WorkTemplate::class);
    }
}
