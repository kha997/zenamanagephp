<?php declare(strict_types=1);

namespace Tests\Feature\Zena;

use Tests\TestCase;

/**
 * @group zena-invariants
 */
class ZenaTestingDatabaseInvariantTest extends TestCase
{
    public function test_testing_environment_matches_invariants_db_mode(): void
    {
        $this->assertTestingDatabaseMode();
    }
}
