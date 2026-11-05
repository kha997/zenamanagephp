<?php

declare(strict_types=1);

namespace Tests\Feature\Deployment;

use Tests\TestCase;

/**
 * GAP-115 M-1: every migration file must be classified expand / breaking, otherwise `deploy:migrate` stops the deploy.
 * This keeps CI red until a new migration is classified.
 */
class MigrationClassificationCoverageTest extends TestCase
{
    public function test_every_migration_is_classified(): void
    {
        /** @var array<string, string> $classes */
        $classes = json_decode((string) file_get_contents(database_path('migrations/classifications.json')), true, flags: JSON_THROW_ON_ERROR);
        $missing = [];
        foreach (glob(database_path('migrations/*.php')) ?: [] as $file) {
            $name = basename($file, '.php');
            if (!in_array($classes[$name] ?? null, ['expand', 'breaking'], true)) {
                $missing[] = $name;
            }
        }

        $this->assertSame([], $missing, 'Classify these migrations in database/migrations/classifications.json.');
    }
}
