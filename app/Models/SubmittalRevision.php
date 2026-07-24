<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubmittalRevision extends Model
{
    use HasUlids, TenantScope;

    protected $table = 'submittal_revisions';
    protected $keyType = 'string';
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = [
        'tenant_id',
        'submittal_id',
        'revision_no',
        'revision_summary',
        'title',
        'description',
        'file_url',
        'attachment_manifest',
        'submitted_by',
        'submitted_at',
        'decision',
        'decided_by',
        'decided_at',
        'decision_comments',
        'created_at',
    ];

    protected $casts = [
        'revision_no' => 'integer',
        'attachment_manifest' => 'array',
        'submitted_at' => 'datetime',
        'decided_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function submittal(): BelongsTo
    {
        return $this->belongsTo(Submittal::class, 'submittal_id');
    }

    public function submittedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function decidedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
