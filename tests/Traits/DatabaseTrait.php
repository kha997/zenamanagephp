<?php declare(strict_types=1);

namespace Tests\Traits;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\Traits\AuthenticationTrait;

/**
 * Trait để hỗ trợ database operations trong tests
 */
trait DatabaseTrait
{
    use RefreshDatabase;
    use AuthenticationTrait;
    
    /**
     * Setup test database
     */
    protected function setUpDatabase(): void
    {
        // Run migrations
        Artisan::call('migrate:fresh', ['--env' => 'testing']);
        
        // Seed basic data if needed
        $this->seedBasicData();
    }
    
    /**
     * Seed basic data cho testing
     */
    protected function seedBasicData(): void
    {
        // Seed basic roles và permissions
        Artisan::call('db:seed', [
            '--class' => 'RolePermissionSeeder',
            '--env' => 'testing'
        ]);
    }
}
