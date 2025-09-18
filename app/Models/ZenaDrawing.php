<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ZenaDrawing extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = [
        'project_id',
        'code',
        'name',
        'version',
        'status',
        'file_url',
        'file_name',
        'file_size',
        'uploaded_by',
        'metadata',
    ];

    protected $casts = [
        'file_size' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the project that owns the drawing.
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(ZenaProject::class, 'project_id');
    }

    /**
     * Get the user who uploaded the drawing.
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * Get drawing status badge color.
     */
    public function getStatusBadgeColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'bg-gray-100 text-gray-800',
            'review' => 'bg-yellow-100 text-yellow-800',
            'approved' => 'bg-green-100 text-green-800',
            'issued' => 'bg-blue-100 text-blue-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Get formatted file size.
     */
    public function getFormattedFileSizeAttribute(): string
    {
        if (!$this->file_size) {
            return 'Unknown';
        }

        $bytes = $this->file_size;
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    /**
     * Get file extension.
     */
    public function getFileExtensionAttribute(): string
    {
        if (!$this->file_name) {
            return '';
        }

        return strtolower(pathinfo($this->file_name, PATHINFO_EXTENSION));
    }

    /**
     * Check if drawing is ready for review.
     */
    public function getIsReadyForReviewAttribute(): bool
    {
        return $this->status === 'draft' && $this->file_url;
    }

    /**
     * Scope for drawings by status.
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope for drawings uploaded by user.
     */
    public function scopeUploadedBy($query, $userId)
    {
        return $query->where('uploaded_by', $userId);
    }

    /**
     * Scope for drawings ready for review.
     */
    public function scopeReadyForReview($query)
    {
        return $query->where('status', 'draft')
            ->whereNotNull('file_url');
    }
}
