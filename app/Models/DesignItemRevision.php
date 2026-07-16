<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One client revision request on a DesignItem ("sửa lần thứ N").
 * Created exclusively by Api\DesignItemController::updateStatus() on the
 * transition into revision_requested — never written anywhere else.
 */
class DesignItemRevision extends Model
{
    use HasUlids;
    use TenantScope;

    protected $fillable = [
        'tenant_id',
        'design_item_id',
        'revision_no',
        'client_feedback',
        'requested_by',
        'requested_at',
        'resolved_at',
    ];

    protected $casts = [
        'requested_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function designItem(): BelongsTo
    {
        return $this->belongsTo(DesignItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
