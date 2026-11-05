<?php

declare(strict_types=1);

namespace App\Models\ProjectOppm;

use App\Traits\TenantScope;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * GAP-110 K-1 — an append-only OPPM period report: the full page data as JSON plus its sha256. Updating or deleting a
 * stored snapshot is refused, so later changes to live data never alter a closed report.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $project_id
 * @property string $period_label
 * @property array<string, mixed> $payload
 * @property string $payload_hash
 * @property string $created_by
 * @property \Illuminate\Support\Carbon $created_at
 */
class OppmSnapshot extends Model
{
    use HasUlids;
    use TenantScope;

    protected $table = 'oppm_snapshots';

    protected $fillable = ['tenant_id', 'project_id', 'period_label', 'payload', 'payload_hash', 'created_by'];

    /** @var array<string, string> */
    protected $casts = ['payload' => 'array'];

    protected static function booted(): void
    {
        static::updating(static function (): void {
            throw new \LogicException('Bản chốt OPPM không được sửa.');
        });
        static::deleting(static function (): void {
            throw new \LogicException('Bản chốt OPPM không được xoá.');
        });
    }
}
