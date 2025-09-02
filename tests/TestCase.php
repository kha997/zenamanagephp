<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;
    
    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Đăng ký middleware alias cho test environment
        $this->app['router']->aliasMiddleware('rbac', \Src\RBAC\Middleware\RBACMiddleware::class);
        
        // RefreshDatabase trait đã tự động xử lý migrations
        // Không cần gọi migrate:fresh thủ công
    }
}
