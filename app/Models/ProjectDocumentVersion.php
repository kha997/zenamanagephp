<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ProjectDocumentVersion Model
 * 
 * Stores historical versions of project documents
 * 
 * @property string $id
 * @property string $document_id
 * @property string $project_id
 * @property string|null $tenant_id
 * @property int $version_number
 * @property string|null $name
 * @property string|null $original_name
 * @property string $file_path
 * @property string|null $file_type
 * @property string|null $mime_type
 * @property int $file_size
 * @property string|null $file_hash
 * @property string|null $note
 * @property string $uploaded_by
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ProjectDocumentVersion extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'project_document_versions';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'document_id',
        'project_id',
        'tenant_id',
        'version_number',
        'name',
        'original_name',
        'file_path',
        'file_type',
        'mime_type',
        'file_size',
        'file_hash',
        'note',
        'uploaded_by',
    ];

    protected $casts = [
        'version_number' => 'integer',
        'file_size' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relationship to Document
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Relationship to Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Relationship to User (uploader)
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

