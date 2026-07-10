<?php declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * Ensures a Tenant row named 'Z.E.N.A' exists — the anchor for the
 * zena-boq-core integration's tenant gate (spec Phase 2). Not a schema
 * change, so this is a seeder, not a migration.
 */
class ZenaBoqTenantSeeder extends Seeder
{
    public function run(): void
    {
        Tenant::firstOrCreate(
            ['name' => 'Z.E.N.A'],
            [
                'status' => 'active',
                'is_active' => true,
            ]
        );
    }
}
