<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vendor extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'vendors';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tenant_id',
        'code',
        'name',
        'contact_name',
        'email',
        'phone',
        'address',
        'is_active',
    ];

    protected $casts = [
        'tenant_id' => 'string',
        'code' => 'string',
        'name' => 'string',
        'contact_name' => 'string',
        'email' => 'string',
        'phone' => 'string',
        'address' => 'string',
        'is_active' => 'boolean',
    ];

    protected $attributes = [
        'is_active' => true,
    ];
}
