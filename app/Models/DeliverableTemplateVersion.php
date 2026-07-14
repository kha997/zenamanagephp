<?php declare(strict_types=1);

namespace App\Models;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string $deliverable_template_id
 * @property string $version
 * @property string $semver
 * @property string $storage_path
 * @property string $checksum_sha256
 * @property string $mime
 * @property int $size
 * @property array<string, mixed> $placeholders_spec_json
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property string|null $published_by
 * @property string|null $created_by
 * @property string|null $updated_by
 * @method static \App\Models\DeliverableTemplateVersion create(array<string, mixed> $attributes = [])
 */
class DeliverableTemplateVersion extends Model
{
    use HasUlids, HasFactory, TenantScope;

    protected $table = 'deliverable_template_versions';
    protected $keyType = 'string';
    public $incrementing = false;

    /** @var list<string> */
    protected $fillable = [
        'tenant_id',
        'deliverable_template_id',
        'version',
        'semver',
        'storage_path',
        'checksum_sha256',
        'mime',
        'size',
        'placeholders_spec_json',
        'published_at',
        'published_by',
        'created_by',
        'updated_by',
    ];

    /** @var array<string, string> */
    protected $casts = [
        'placeholders_spec_json' => 'array',
        'size' => 'integer',
        'published_at' => 'datetime',
    ];

    public function template(): BelongsTo
    {
        return $this->belongsTo(DeliverableTemplate::class, 'deliverable_template_id');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
