<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Material extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'materials';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'category',
        'unit',
        'description',
        'is_active',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'code' => 'string',
        'name' => 'string',
        'category' => 'string',
        'unit' => 'string',
        'description' => 'string',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];
}
