<?php declare(strict_types=1);

namespace Tests\Feature\Api;

use Tests\TestCase;

class ApiTestConfigurationTest extends TestCase
{
    public function test_testing_environment_boots(): void
    {
        $this->assertTrue(app()->environment('testing'), 'Application should boot in the testing environment');

        $this->assertNotEmpty(config('cache.default'), 'Cache driver configuration is missing');
        $this->assertNotEmpty(config('database.default'), 'Database driver configuration is missing');

        if (config('cache.default') === 'redis') {
            $redisConfig = config('database.redis.default');
            $this->assertNotEmpty(
                $redisConfig,
                'Redis cache driver is configured but database.redis.default is not defined'
            );
        }
    }
}
