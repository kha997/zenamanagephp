<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContractPayment extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'contract_payments';
    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PLANNED = 'planned';
    public const STATUS_PAID = 'paid';
    public const STATUS_OVERDUE = 'overdue';

    public const VALID_STATUSES = [
        self::STATUS_PLANNED,
        self::STATUS_PAID,
        self::STATUS_OVERDUE,
    ];

    protected $fillable = [
        'tenant_id',
        'contract_id',
        'name',
        'amount',
        'due_date',
        'status',
        'paid_at',
        'note',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'tenant_id' => 'string',
        'contract_id' => 'string',
        'amount' => 'float',
        'due_date' => 'date',
        'paid_at' => 'date',
    ];

    /** @var array<string, string> */
    protected $attributes = [
        'status' => self::STATUS_PLANNED,
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }
}
