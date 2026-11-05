<?php

declare(strict_types=1);

namespace App\Models\ProjectOppm;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-110 A-1 — management overlay of one OPPM work group (a root component of the project). The weight is shown only
 * (W-1) and never feeds the progress figure.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $component_id
 * @property string|null $outcome
 * @property string|null $weight
 * @property string|null $next_action
 * @property string|null $next_action_owner_id
 * @property \Illuminate\Support\Carbon|null $next_action_due
 * @property string|null $note
 * @property int|null $display_order
 * @property string $updated_by
 */
class OppmWorkstreamNote extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'oppm_workstream_notes';

    protected $fillable = [
        'tenant_id', 'project_id', 'component_id', 'outcome', 'weight', 'next_action', 'next_action_owner_id',
        'next_action_due', 'note', 'display_order', 'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = ['weight' => 'decimal:2', 'next_action_due' => 'date', 'display_order' => 'integer'];
}
