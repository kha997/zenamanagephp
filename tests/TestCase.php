<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\DBDriver;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fix UrlGenerator issue in CLI context
        $this->fixUrlGeneratorForTesting();
        
        // Run migrations for SQLite testing
        $this->runMigrations();
        
        // Bootstrap RBAC and Tenancy
        $this->bootstrapRBACAndTenancy();
    }

    /**
     * Fix UrlGenerator issue in CLI context
     */
    protected function fixUrlGeneratorForTesting(): void
    {
        if (php_sapi_name() === 'cli') {
            // Create a mock request for URL generation
            $request = \Illuminate\Http\Request::create('http://localhost', 'GET');
            
            // Bind the request to the container
            $this->app->instance('request', $request);
            
            // Set the URL generator's request
            $urlGenerator = $this->app->make(\Illuminate\Routing\UrlGenerator::class);
            $urlGenerator->setRequest($request);
            
            // Set the global URL facade
            \Illuminate\Support\Facades\URL::setRequest($request);
        }
    }

    /**
     * Run migrations for testing
     */
    protected function runMigrations(): void
    {
        if (DBDriver::isSqlite()) {
            // For SQLite, create tables manually to avoid migration issues
            $this->createTestTables();
        } else {
            // For MySQL, run actual migrations
            Artisan::call('migrate:fresh');
        }
    }

    /**
     * Bootstrap RBAC and Tenancy for tests
     */
    protected function bootstrapRBACAndTenancy(): void
    {
        // Create a default tenant
        $tenant = $this->createDefaultTenant();
        
        // Create a test user with admin role
        $user = $this->createTestUser($tenant);
        
        // Set up tenancy context if needed
        $this->setupTenancyContext($tenant);
        
        // Store user and tenant for use in tests
        $this->app->instance('test.user', $user);
        $this->app->instance('test.tenant', $tenant);
    }

    /**
     * Create default tenant for testing
     */
    protected function createDefaultTenant(): object
    {
        if (Schema::hasTable('tenants')) {
            $tenant = DB::table('tenants')->first();
            if (!$tenant) {
                $tenantId = \Illuminate\Support\Str::ulid();
                DB::table('tenants')->insert([
                    'id' => $tenantId,
                    'name' => 'Test Tenant',
                    'slug' => 'test-tenant',
                    'domain' => 'test.local',
                    'status' => 'active',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $tenant = DB::table('tenants')->where('id', $tenantId)->first();
            }
            return $tenant;
        }
        
        return (object) ['id' => 'test-tenant-id'];
    }

    /**
     * Create test user with admin role
     */
    protected function createTestUser(object $tenant): object
    {
        if (Schema::hasTable('users')) {
            $user = DB::table('users')->first();
            if (!$user) {
                $userId = \Illuminate\Support\Str::ulid();
                DB::table('users')->insert([
                    'id' => $userId,
                    'tenant_id' => $tenant->id,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                    'email_verified_at' => now(),
                    'password' => bcrypt('password'),
                    'role' => 'admin',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
                $user = DB::table('users')->where('id', $userId)->first();
            }
            return $user;
        }
        
        return (object) ['id' => 'test-user-id', 'tenant_id' => $tenant->id];
    }

    /**
     * Setup tenancy context
     */
    protected function setupTenancyContext(object $tenant): void
    {
        // If using a tenancy package, initialize it here
        // For now, we'll just set the tenant in the session
        session(['tenant_id' => $tenant->id]);
    }

    /**
     * Get the test user for authentication
     */
    protected function getTestUser(): object
    {
        return $this->app->make('test.user');
    }

    /**
     * Get the test tenant
     */
    protected function getTestTenant(): object
    {
        return $this->app->make('test.tenant');
    }

    /**
     * Authenticate as test user
     */
    protected function actingAsTestUser(): self
    {
        $user = $this->getTestUser();
        // Create a mock user object that implements Authenticatable
        $mockUser = new class($user) implements \Illuminate\Contracts\Auth\Authenticatable {
            private $user;
            
            public function __construct($user) {
                $this->user = $user;
            }
            
            public function getAuthIdentifierName() {
                return 'id';
            }
            
            public function getAuthIdentifier() {
                return $this->user->id;
            }
            
            public function getAuthPassword() {
                return $this->user->password ?? '';
            }
            
            public function getRememberToken() {
                return '';
            }
            
            public function setRememberToken($value) {}
            
            public function getRememberTokenName() {
                return '';
            }
            
            // Add tenant property for middleware compatibility
            public function __get($name) {
                if ($name === 'tenant_id') {
                    return $this->user->tenant_id ?? null;
                }
                if ($name === 'tenant') {
                    return $this->user->tenant_id ?? null;
                }
                return $this->user->$name ?? null;
            }
            
            public function __isset($name) {
                return isset($this->user->$name);
            }
        };
        
        return $this->actingAs($mockUser, 'web');
    }

    protected function createTestTables(): void
    {
        // Create tenants table
        if (!Schema::hasTable('tenants')) {
            Schema::create('tenants', function ($table) {
                $table->string('id')->primary();
                $table->string('name');
                $table->string('domain')->unique();
                $table->string('slug')->unique();
                $table->string('status')->default('trial');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }

        // Create users table
        if (!Schema::hasTable('users')) {
            Schema::create('users', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('name');
                $table->string('email')->unique();
                $table->timestamp('email_verified_at')->nullable();
                $table->string('password');
                $table->string('role')->default('member');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                
                $table->index(['tenant_id', 'email']);
            });
        }

        // Create projects table
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('name');
                $table->string('code')->nullable();
                $table->string('status')->default('active');
                $table->string('owner_id')->nullable();
                $table->text('tags')->nullable();
                $table->date('start_date')->nullable();
                $table->date('due_date')->nullable();
                $table->string('priority')->default('normal');
                $table->integer('progress')->default(0);
                $table->integer('progress_pct')->default(0);
                $table->decimal('budget_total', 15, 2)->default(0);
                $table->decimal('budget_planned', 15, 2)->default(0);
                $table->decimal('budget_actual', 15, 2)->default(0);
                $table->integer('estimated_hours')->default(0);
                $table->integer('actual_hours')->default(0);
                $table->string('risk_level')->default('low');
                $table->boolean('is_template')->default(false);
                $table->integer('completion_percentage')->default(0);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'status']);
                $table->index(['tenant_id', 'owner_id']);
                $table->index(['tenant_id', 'created_at']);
            });
        }

        // Create tasks table
        if (!Schema::hasTable('tasks')) {
            Schema::create('tasks', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->default('todo');
                $table->string('priority')->default('medium');
                $table->string('assignee_id')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->integer('progress_percent')->default(0);
                $table->decimal('estimated_hours', 8, 2)->nullable();
                $table->decimal('actual_hours', 8, 2)->nullable();
                $table->text('tags')->nullable();
                $table->text('dependencies')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['assignee_id', 'status']);
            });
        }

        // Create audit_logs table
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function ($table) {
                $table->id();
                $table->string('event');
                $table->string('user_id');
                $table->string('tenant_id');
                $table->string('model_type')->nullable();
                $table->string('model_id')->nullable();
                $table->text('data');
                $table->string('ip_address')->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'created_at']);
                $table->index(['user_id', 'created_at']);
            });
        }
    }
}