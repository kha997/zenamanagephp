<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReceiptChecklist extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'material_receipt_checklists';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'material_receipt_id',
        'acceptance_summary',
        'items',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'material_receipt_id' => 'string',
        'acceptance_summary' => 'string',
        'items' => 'array',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
