<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id ULID primary key
 * @property string $tenant_id Tenant ULID
 * @property string $contract_id Contract ULID
 * @property string $name Payment name
 * @property float $amount Payment amount
 * @property string $status Payment status (planned|paid|overdue)
 * @property \Carbon\Carbon|null $due_date Due date
 * @property \Carbon\Carbon|null $paid_at Paid timestamp
 * @property string|null $note Note
 */
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
