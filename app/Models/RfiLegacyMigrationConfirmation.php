<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $rfi_id
 * @property string $confirmed_by
 * @property \Carbon\Carbon $confirmed_at
 * @property string $confirmed_lifecycle_status
 * @property string $confirmed_escalation_state
 * @property string $reason
 * @property string $source_snapshot
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class RfiLegacyMigrationConfirmation extends Model
{
    use HasUlids;

    protected $table = 'rfi_legacy_migration_confirmations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'rfi_id',
        'confirmed_by',
        'confirmed_at',
        'confirmed_lifecycle_status',
        'confirmed_escalation_state',
        'reason',
        'source_snapshot',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'confirmed_at' => 'datetime',
    ];
}
