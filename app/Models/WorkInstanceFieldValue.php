<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkInstanceFieldValue extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'work_instance_field_values';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'work_instance_step_id',
        'field_key',
        'value_string',
        'value_number',
        'value_date',
        'value_datetime',
        'value_json',
    ];

    protected $casts = [
        'value_number' => 'float',
        'value_date' => 'date',
        'value_datetime' => 'datetime',
        'value_json' => 'array',
    ];

    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkInstanceStep::class, 'work_instance_step_id');
    }
}
