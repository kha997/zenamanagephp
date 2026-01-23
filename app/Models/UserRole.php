<?php declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Str;

class UserRole extends Pivot
{
    protected $table = 'user_roles';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id',
        'user_id',
        'role_id',
    ];

    public $timestamps = true;

    protected static function booted(): void
    {
        static::creating(function (self $pivot): void {
            if (! $pivot->{$pivot->getKeyName()}) {
                $pivot->{$pivot->getKeyName()} = (string) Str::ulid();
            }
        });
    }
}
