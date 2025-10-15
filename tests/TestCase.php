<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Fix UrlGenerator issue in CLI context
        $this->fixUrlGeneratorForTesting();
        
        // Create tables manually for SQLite testing
        $this->createTestTables();
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