<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Organization relationship
            if (!Schema::hasColumn('users', 'organization_id')) {
                $table->unsignedBigInteger('organization_id')->nullable();
            }
            
            // User profile fields
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable();
            }
            if (!Schema::hasColumn('users', 'phone')) {
                $table->string('phone')->nullable();
            }
            if (!Schema::hasColumn('users', 'avatar_url')) {
                $table->string('avatar_url')->nullable();
            }
            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title')->nullable();
            }
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable();
            }
            
            // Status and verification
            if (!Schema::hasColumn('users', 'status')) {
                $table->enum('status', ['active', 'inactive', 'pending', 'suspended'])->default('pending');
            }
            if (!Schema::hasColumn('users', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'password_changed_at')) {
                $table->timestamp('password_changed_at')->nullable();
            }
            
            // Invitation tracking
            if (!Schema::hasColumn('users', 'invitation_id')) {
                $table->unsignedBigInteger('invitation_id')->nullable();
            }
            if (!Schema::hasColumn('users', 'invited_at')) {
                $table->timestamp('invited_at')->nullable();
            }
            if (!Schema::hasColumn('users', 'joined_at')) {
                $table->timestamp('joined_at')->nullable();
            }
            
            // Preferences
            if (!Schema::hasColumn('users', 'timezone')) {
                $table->string('timezone')->default('UTC');
            }
            if (!Schema::hasColumn('users', 'language')) {
                $table->string('language')->default('en');
            }
            if (!Schema::hasColumn('users', 'preferences')) {
                $table->json('preferences')->nullable();
            }
            
            // Indexes (skip if they already exist)
            try {
                $table->index(['organization_id', 'status']);
            } catch (\Exception $e) {
                // Index already exists
            }
            try {
                $table->index('email_verified_at');
            } catch (\Exception $e) {
                // Index already exists
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (!Schema::hasTable('users')) {
            return;
        }

        $this->dropForeignIfExists('users', ['organization_id']);
        $this->dropForeignIfExists('users', ['invitation_id']);

        $this->dropIndexIfExists('users', ['organization_id', 'status']);
        $this->dropIndexIfExists('users', ['email_verified_at']);

        $columns = [
            'organization_id',
            'first_name',
            'last_name',
            'phone',
            'avatar_url',
            'job_title',
            'department',
            'status',
            'email_verified_at',
            'last_login_at',
            'password_changed_at',
            'invitation_id',
            'invited_at',
            'joined_at',
            'timezone',
            'language',
            'preferences',
        ];

        $existingColumns = [];
        foreach ($columns as $column) {
            if (Schema::hasColumn('users', $column)) {
                $existingColumns[] = $column;
            }
        }

        if ($existingColumns === []) {
            return;
        }

        try {
            Schema::table('users', function (Blueprint $table) use ($existingColumns) {
                $table->dropColumn($existingColumns);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }

    private function dropForeignIfExists(string $tableName, array $columns): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($columns) {
                $table->dropForeign($columns);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }

    private function dropIndexIfExists(string $tableName, string|array $index): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($index) {
                $table->dropIndex($index);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }
};
