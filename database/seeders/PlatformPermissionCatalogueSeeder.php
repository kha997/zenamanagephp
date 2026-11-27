<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Permission;
use App\Support\PlatformPermissions;
use Illuminate\Database\Seeder;

/**
 * OWN-2026-024 (O-17h) H2a, Owner Gate 2 D1: creates the five platform permission codes ONLY. It grants them to nobody —
 * Platform Operators are provisioned by a separate server-only step (D6, H2b). Idempotent: existing rows are left as they are.
 */
class PlatformPermissionCatalogueSeeder extends Seeder
{
    public function run(): void
    {
        foreach (PlatformPermissions::CATALOGUE as $code => $definition) {
            Permission::query()->firstOrCreate(
                ['code' => $code],
                [
                    'name' => $code,
                    'module' => 'platform',
                    'action' => $definition['action'],
                    'description' => $definition['description'],
                ],
            );
        }
    }
}
