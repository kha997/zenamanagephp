<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaterialReceiptLine extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'material_receipt_lines';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'material_receipt_id',
        'material_id',
        'quantity_received',
        'unit_cost',
        'notes',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'material_receipt_id' => 'string',
        'material_id' => 'string',
        'quantity_received' => 'float',
        'unit_cost' => 'float',
        'notes' => 'string',
    ];

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(MaterialReceipt::class, 'material_receipt_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
