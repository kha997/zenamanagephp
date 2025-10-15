<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Dashboard extends Model
{
    use HasFactory;
    protected $fillable = [
        'name',
        'description',
        'user_id',
        'tenant_id',
        'widget_config',
        'layout',
        'is_default'
    ];

    protected $casts = [
        'user_id' => 'string',
        'tenant_id' => 'string',
        'widget_config' => 'array',
        'layout' => 'array',
        'is_default' => 'boolean'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class);
    }
}
