<?php

declare(strict_types=1);

namespace App\Models\ProjectOppm;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-110 A-1 — project-level OPPM overlay: management summary and the project's next action.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string|null $summary
 * @property string|null $next_action
 * @property string|null $next_action_owner_id
 * @property \Illuminate\Support\Carbon|null $next_action_due
 * @property string $updated_by
 */
class OppmProjectNote extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'oppm_project_notes';

    protected $fillable = ['tenant_id', 'project_id', 'summary', 'next_action', 'next_action_owner_id', 'next_action_due', 'updated_by'];

    /** @var array<string, string> */
    protected $casts = ['next_action_due' => 'date'];
}
