<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

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

    protected $casts = [
        'confirmed_at' => 'datetime',
    ];
}
