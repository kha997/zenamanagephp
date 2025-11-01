<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * ChangeRequestComment Model - Comments on change requests
 * 
 * @property string $id ULID primary key
 * @property string $change_request_id ID change request (ULID)
 * @property string $user_id ID người comment (ULID)
 * @property string $comment Nội dung comment
 * @property string|null $parent_id ID comment cha (ULID, nullable)
 * @property bool $is_internal Comment nội bộ
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class ChangeRequestComment extends Model
{

    protected $fillable = [
        'id',
        'change_request_id',
        'user_id',
        'comment',
        'parent_id',
        'is_internal',
    ];

    protected $casts = [
        'is_internal' => 'boolean',
    ];

    protected $attributes = [
        'is_internal' => false,
    ];

    /**
     * Relationship: Comment belongs to change request
     */
    public function changeRequest(): BelongsTo
    {
        return $this->belongsTo(ChangeRequest::class, 'change_request_id');
    }

    /**
     * Relationship: Comment belongs to user
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Relationship: Comment belongs to parent comment
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(ChangeRequestComment::class, 'parent_id');
    }

    /**
     * Relationship: Comment has many replies
     */
    public function replies(): HasMany
    {
        return $this->hasMany(ChangeRequestComment::class, 'parent_id');
    }
}