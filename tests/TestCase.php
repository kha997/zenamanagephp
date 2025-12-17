<?php

namespace Tests;

use Carbon\Carbon;
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

        $this->applySmokeSuiteFixedNow();
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
        // Check if RefreshDatabase trait is being used - if so, let it handle migrations
        $usesRefreshDatabase = in_array(
            \Illuminate\Foundation\Testing\RefreshDatabase::class,
            class_uses_recursive(static::class)
        );
        
        if ($usesRefreshDatabase) {
            // RefreshDatabase will handle migrations, so skip our custom logic
            return;
        }
        
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
        session(['selected_tenant_id' => $tenant->id]);

        if (app()->bound('request')) {
            $request = app('request');
            $request->attributes->set('tenant_id', $tenant->id);
            $request->attributes->set('active_tenant_id', $tenant->id);
        }

        app()->instance('current_tenant_id', $tenant->id);
    }

    private function applySmokeSuiteFixedNow(): void
    {
        $fixedNow = env('SMOKE_SUITE_FIXED_NOW');
        if ($fixedNow) {
            Carbon::setTestNow(Carbon::parse($fixedNow));
            return;
        }

        Carbon::setTestNow();
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
     * Create and authenticate a user for a tenant (canonical helper for multi-tenant tests)
     * 
     * This helper ensures proper tenant context setup according to the canonical tenant resolution rule:
     * 1. Creates a tenant (or uses provided one)
     * 2. Creates a user with tenant_id set (legacy fallback)
     * 3. Attaches user to tenant via pivot table with is_default = true (primary resolution)
     * 4. Loads tenants relationship on user (ensures defaultTenant() can access pivot)
     * 5. Authenticates user via Sanctum
     * 
     * This ensures TenancyService.resolveActiveTenantId() returns the correct tenant via:
     * - user->defaultTenant() which checks pivot is_default first
     * - Falls back to user->tenant_id if no pivot
     * 
     * @param \App\Models\Tenant|null $tenant Optional tenant (creates one if not provided)
     * @param array $userAttributes Optional user attributes
     * @param string $role Optional role for pivot table (default: 'pm')
     * @return array ['user' => User, 'tenant' => Tenant]
     */
    protected function actingAsTenantUser(?\App\Models\Tenant $tenant = null, array $userAttributes = [], string $role = 'pm'): array
    {
        // Create tenant if not provided
        if (!$tenant) {
            $tenant = \App\Models\Tenant::factory()->create();
        }
        
        // Create user with tenant_id (legacy fallback)
        $user = \App\Models\User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
        ], $userAttributes));
        
        // Attach user to tenant via pivot table with is_default = true
        // This is the primary way defaultTenant() resolves the tenant
        $user->tenants()->attach($tenant->id, [
            'role' => $role,
            'is_default' => true,
        ]);
        
        // Refresh user and load tenants relationship to ensure defaultTenant() can access pivot
        $user->refresh();
        $user->load('tenants');
        
        // Authenticate user via Sanctum
        \Laravel\Sanctum\Sanctum::actingAs($user, [], 'sanctum');
        
        return ['user' => $user, 'tenant' => $tenant];
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
            $table->json('settings')->nullable();
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
                $table->string('remember_token')->nullable();
                $table->string('role')->default('member');
                $table->boolean('is_active')->default(true);
                $table->json('profile_data')->nullable();
                $table->timestamps();
                
                $table->index(['tenant_id', 'email']);
            });
        }

        if (!Schema::hasTable('user_tenants')) {
            Schema::create('user_tenants', function ($table) {
                $table->string('id')->primary();
                $table->string('user_id');
                $table->string('tenant_id');
                $table->string('role')->nullable();
                $table->boolean('is_default')->default(false);
                $table->timestamps();
                $table->softDeletes();
            });
        }

        if (!Schema::hasTable('user_notification_preferences')) {
            Schema::create('user_notification_preferences', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('user_id');
                $table->string('type', 100);
                $table->boolean('is_enabled')->default(true);
                $table->boolean('in_app_enabled')->default(true);
                $table->boolean('email_enabled')->default(false);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('notifications')) {
            Schema::create('notifications', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('user_id');
                $table->string('module', 50)->nullable();
                $table->string('type', 100);
                $table->string('title');
                $table->text('message')->nullable();
                $table->string('entity_type')->nullable();
                $table->string('entity_id')->nullable();
                $table->boolean('is_read')->default(false);
                $table->text('metadata')->nullable();
                $table->timestamps();
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
                $table->text('description')->nullable();
                $table->text('tags')->nullable();
                $table->string('template_id')->nullable();
                $table->timestamp('last_activity_at')->nullable();
                $table->json('settings')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
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

        if (!Schema::hasTable('project_tasks')) {
            Schema::create('project_tasks', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('status')->nullable();
                $table->boolean('is_milestone')->default(false);
                $table->boolean('is_completed')->default(false);
                $table->integer('sort_order')->default(0);
                $table->date('due_date')->nullable();
                $table->integer('duration_days')->default(0);
                $table->float('progress_percent')->default(0);
                $table->boolean('is_hidden')->default(false);
                $table->string('assignee_id')->nullable();
                $table->string('template_id')->nullable();
                $table->string('template_task_id')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['tenant_id', 'status']);
                $table->index(['project_id', 'is_hidden']);
            });
        }

        if (!Schema::hasTable('project_activities')) {
            Schema::create('project_activities', function ($table) {
                $table->id();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('user_id')->nullable();
                $table->string('action');
                $table->string('entity_type');
                $table->string('entity_id')->nullable();
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->string('ip_address')->nullable();
                $table->string('user_agent')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['project_id', 'created_at']);
            });
        }

        if (!Schema::hasTable('contracts')) {
            Schema::create('contracts', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('code');
                $table->string('name');
                $table->string('status');
                $table->string('client_id')->nullable();
                $table->string('project_id')->nullable();
                $table->timestamp('signed_at')->nullable();
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_to')->nullable();
                $table->string('currency')->nullable();
                $table->decimal('total_value', 18, 2)->nullable();
                $table->text('notes')->nullable();
                $table->string('created_by_id')->nullable();
                $table->string('updated_by_id')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
            });
        }

        if (!Schema::hasTable('change_orders')) {
            Schema::create('change_orders', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('code');
                $table->string('title')->nullable();
                $table->text('reason')->nullable();
                $table->string('status');
                $table->decimal('amount_delta', 18, 2)->default(0);
                $table->timestamp('effective_date')->nullable();
                $table->json('metadata')->nullable();
                $table->boolean('requires_dual_approval')->default(false);
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->string('first_approved_by')->nullable();
                $table->timestamp('first_approved_at')->nullable();
                $table->string('second_approved_by')->nullable();
                $table->timestamp('second_approved_at')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'project_id']);
            });
        }

        if (!Schema::hasTable('contract_payment_certificates')) {
            Schema::create('contract_payment_certificates', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('code');
                $table->string('title')->nullable();
                $table->timestamp('period_start')->nullable();
                $table->timestamp('period_end')->nullable();
                $table->string('status')->nullable();
                $table->decimal('amount_before_retention', 18, 2)->nullable();
                $table->decimal('retention_percent_override', 5, 2)->nullable();
                $table->decimal('retention_amount', 18, 2)->nullable();
                $table->decimal('amount_payable', 18, 2)->nullable();
                $table->json('metadata')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'project_id']);
            });
        }

        if (!Schema::hasTable('contract_actual_payments')) {
            Schema::create('contract_actual_payments', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('contract_id');
                $table->string('certificate_id')->nullable();
                $table->timestamp('paid_date')->nullable();
                $table->decimal('amount_paid', 18, 2)->nullable();
                $table->string('currency')->nullable();
                $table->string('payment_method')->nullable();
                $table->string('reference_no')->nullable();
                $table->json('metadata')->nullable();
                $table->string('created_by')->nullable();
                $table->string('updated_by')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'project_id']);
            });
        }

        if (!Schema::hasTable('contract_payments')) {
            Schema::create('contract_payments', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('contract_id');
                $table->string('code')->nullable();
                $table->string('name');
                $table->string('type')->nullable();
                $table->date('due_date');
                $table->decimal('amount', 18, 2)->default(0);
                $table->string('currency')->default('USD');
                $table->string('status')->default('planned');
                $table->timestamp('paid_at')->nullable();
                $table->text('notes')->nullable();
                $table->integer('sort_order')->default(0);
                $table->string('created_by_id')->nullable();
                $table->string('updated_by_id')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->index(['tenant_id', 'contract_id']);
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

        if (!Schema::hasTable('project_health_settings')) {
            Schema::create('project_health_settings', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id')->unique();
                $table->integer('high_risk_threshold')->default(80);
                $table->integer('risk_jump_threshold')->default(20);
                $table->float('overdue_tasks_weight')->default(1);
                $table->float('pending_approvals_weight')->default(1);
                $table->float('recent_activity_weight')->default(1);
                $table->float('inspection_payment_outstanding_weight')->default(15);
                $table->float('inspection_payment_overdue_weight')->default(25);
                $table->float('inspection_cost_variance_weight')->default(15);
                $table->float('inspection_cost_unreviewed_weight')->default(10);
                $table->float('inspection_blocked_completion_weight')->default(10);
                $table->timestamps();
            });
        }

        if (!Schema::hasTable('inspections')) {
            Schema::create('inspections', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('client_name');
                $table->string('client_phone')->nullable();
                $table->string('client_company')->nullable();
                $table->double('scope_area_m2')->default(0);
                $table->string('purpose');
                $table->string('structure_type');
                $table->string('survey_method');
                $table->string('engineer_in_charge')->nullable();
                $table->date('requested_date');
                $table->string('status')->default('requested');
                $table->text('description')->nullable();
                $table->timestamps();
                $table->softDeletes();
                $table->index(['tenant_id', 'project_id']);
            });
        }

        if (!Schema::hasTable('project_health_risk_snapshots')) {
            Schema::create('project_health_risk_snapshots', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->string('project_id');
                $table->string('status', 32);
                $table->unsignedTinyInteger('risk_score')->default(0);
                $table->unsignedSmallInteger('overdue_tasks')->default(0);
                $table->unsignedSmallInteger('pending_approvals')->default(0);
                $table->unsignedSmallInteger('activity_events')->default(0);
                $table->unsignedSmallInteger('inspection_score_delta')->default(0);
                $table->text('insights_summary')->nullable();
                $table->timestamp('captured_at')->nullable();
                $table->timestamps();

                $table->index(['tenant_id', 'project_id']);
                $table->index(['tenant_id', 'captured_at']);
            });
        }

        if (!Schema::hasTable('finance_snapshots')) {
            Schema::create('finance_snapshots', function ($table) {
                $table->string('id')->primary();
                $table->string('tenant_id');
                $table->timestamp('as_of_at');
                $table->string('currency')->nullable();
                $table->json('totals_json')->nullable();
                $table->json('buckets_json')->nullable();
                $table->json('sources_json')->nullable();
                $table->json('top_projects_json')->nullable();
                $table->string('prev_snapshot_id')->nullable();
                $table->timestamps();
            });
        }
    }
}
