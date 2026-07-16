<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ULID
 * @property string $project_id Project ULID
 * @property string|null $contract_id Contract ULID
 * @property string $code BOQ code
 * @property string $name BOQ name
 * @property string|null $description BOQ description
 */
class Boq extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'boqs';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'project_id',
        'contract_id',
        'code',
        'name',
        'description',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'project_id' => 'string',
        'contract_id' => 'string',
        'code' => 'string',
        'name' => 'string',
        'description' => 'string',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Contract, $this> */
    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function lineItems(): HasMany
    {
        return $this->hasMany(BoqLineItem::class);
    }
}
