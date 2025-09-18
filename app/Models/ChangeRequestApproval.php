<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Src\Foundation\Traits\HasTimestamps;

/**
 * ChangeRequestApproval Model - Multi-level approvals for change requests
 * 
 * @property string $id ULID primary key
 * @property string $change_request_id ID change request (ULID)
 * @property string $user_id ID người phê duyệt (ULID)
 * @property string $level Cấp độ phê duyệt
 * @property string $status Trạng thái phê duyệt
 * @property string|null $comments Ghi chú phê duyệt
 * @property \Carbon\Carbon|null $approved_at Ngày phê duyệt
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChangeRequestApproval extends Model
{
    use HasFactory, HasUlids, HasTimestamps;

    protected $fillable = [
        'id',
        'change_request_id',
        'user_id',
        'level',
        'status',
        'comments',
        'approved_at',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
    ];

    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Relationship: Approval belongs to change request
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ZenaChangeRequest::class, 'change_request_id');
    }

    /**
     * Relationship: Approval belongs to user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Approve this level
     */
    public function approve(string $comments = null): bool
    {
        return $this->update([
            'status' => 'approved',
            'comments' => $comments,
            'approved_at' => now(),
        ]);
    }

    /**
     * Reject this level
     */
    public function reject(string $comments): bool
    {
        return $this->update([
            'status' => 'rejected',
            'comments' => $comments,
        ]);
    }

    /**
     * Check if approved
     */
    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    /**
     * Check if rejected
     */
    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    /**
     * Check if pending
     */
    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
