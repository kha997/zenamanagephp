<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalLoginToken extends Model
{
    use HasUlids;

    protected $table = 'portal_login_tokens';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'token_hash',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'account_id' => 'string',
        'token_hash' => 'string',
        'expires_at' => 'datetime',
        'used_at' => 'datetime',
    ];

    protected $hidden = [
        'token_hash',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
