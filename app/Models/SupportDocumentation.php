<?php

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class SupportDocumentation extends Model
{
    use HasFactory, HasUlids, SoftDeletes, TenantScope;

    protected $fillable = [
        'tenant_id',
        'title',
        'slug',
        'content',
        'category',
        'status',
        'tags',
        'author_id',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'author_id' => 'string',
    ];

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
