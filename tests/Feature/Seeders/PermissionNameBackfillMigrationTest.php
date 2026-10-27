<?php

declare(strict_types=1);

namespace Tests\Feature\Seeders;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class PermissionNameBackfillMigrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backfill_sets_null_names_to_code_and_keeps_existing_names(): void
    {
        $this->insertPermission('gap079.null_name', null);
        $this->insertPermission('gap079.named', 'gap079 custom name');
        $this->assertNull($this->nameOf('gap079.null_name'), 'Test setup: row must start with a NULL name.');

        $migration = require database_path('migrations/2026_10_14_000000_backfill_null_permission_names.php');
        $migration->up();

        $this->assertSame('gap079.null_name', $this->nameOf('gap079.null_name'));
        $this->assertSame('gap079 custom name', $this->nameOf('gap079.named'));
    }

    private function insertPermission(string $code, ?string $name): void
    {
        DB::table('permissions')->insert([
            'id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name,
            'module' => 'gap079',
            'action' => Str::after($code, '.'),
            'description' => 'GAP-079 backfill test row',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function nameOf(string $code): ?string
    {
        return DB::table('permissions')->where('code', $code)->value('name');
    }
}
