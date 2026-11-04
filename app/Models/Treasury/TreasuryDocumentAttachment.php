<?php

declare(strict_types=1);

namespace App\Models\Treasury;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * GAP-106 — an evidence file of a Treasury money document (S-2: Treasury-only, never in the general document list).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $financial_document_id
 * @property string $evidence_type
 * @property string $original_name
 * @property string $file_path
 * @property string $mime_type
 * @property int $file_size
 * @property string $file_hash
 * @property string|null $note
 * @property string $uploaded_by
 * @property \Illuminate\Support\Carbon $created_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class TreasuryDocumentAttachment extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'treasury_document_attachments';

    protected $fillable = [
        'tenant_id', 'project_id', 'financial_document_id', 'evidence_type', 'original_name', 'file_path',
        'mime_type', 'file_size', 'file_hash', 'note', 'uploaded_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'file_size' => 'integer',
    ];

    /** @return BelongsTo<TreasuryFinancialDocument, $this> */
    public function financialDocument(): BelongsTo
    {
        return $this->belongsTo(TreasuryFinancialDocument::class, 'financial_document_id');
    }
}
