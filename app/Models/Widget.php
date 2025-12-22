<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Widget extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'settings',
        'dashboard_id',
        'user_id',
        'tenant_id'
    ];

    protected $casts = [
        'user_id' => 'string',
        'tenant_id' => 'string',
        'settings' => 'array'
    ];

    public function dashboard(): BelongsTo
    {
        return $this->belongsTo(Dashboard::class);
    }
}