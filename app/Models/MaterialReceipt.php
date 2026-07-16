<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MaterialReceipt extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'material_receipts';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'vendor_id',
        'contract_id',
        'material_request_id',
        'receipt_number',
        'receipt_date',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'vendor_id' => 'string',
        'contract_id' => 'string',
        'material_request_id' => 'string',
        'receipt_number' => 'string',
        'receipt_date' => 'date',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function materialRequest(): BelongsTo
    {
        return $this->belongsTo(MaterialRequest::class, 'material_request_id');
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(MaterialReceiptChecklist::class, 'material_receipt_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(MaterialReceiptLine::class, 'material_receipt_id');
    }
}
