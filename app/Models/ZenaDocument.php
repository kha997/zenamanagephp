<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ZenaDocument extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'id',
        'project_id',
        'tenant_id',
        'uploaded_by',
        'name',
        'original_name',
        'file_path',
        'file_size',
        'file_type',
        'mime_type',
        'file_hash',
        'metadata',
        'parent_document_id',
        'version',
        'version_note',
        'is_active',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_active' => 'boolean',
        'file_size' => 'integer',
        'version' => 'integer',
    ];

    /**
     * Get the project that owns the document.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the tenant that owns the document.
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Get the user who uploaded the document.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get the parent document.
     */
    public function parentDocument(): BelongsTo
    {
        return $this->belongsTo(ZenaDocument::class, 'parent_document_id');
    }

    /**
     * Get the document versions.
     */
    public function versions(): HasMany
    {
        return $this->hasMany(ZenaDocument::class, 'parent_document_id');
    }
}