<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\SqliteCompatibleMigration;
use App\Support\DBDriver;

return new class extends Migration
{
    use SqliteCompatibleMigration;

    /**
     * Run the migrations.
     * 
     * Creates user_tenants pivot table for multi-tenant membership
     * and backfills existing users.tenant_id relationships.
     */
    public function up(): void
    {
        if (!Schema::hasTable('user_tenants')) {
            Schema::create('user_tenants', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('user_id');
                $table->string('tenant_id');
                $table->string('role')->nullable()->default('member'); // e.g., 'owner', 'member', 'admin'
                $table->boolean('is_default')->default(false); // Default tenant for this user
                $table->timestamps();
                $table->softDeletes();

                // Foreign key constraints
                $this->addForeignKeyConstraint($table, 'user_id', 'id', 'users', 'cascade');
                $this->addForeignKeyConstraint($table, 'tenant_id', 'id', 'tenants', 'cascade');

                // Indexes
                $this->addIndex($table, ['user_id'], 'user_tenants_user_id_index');
                $this->addIndex($table, ['tenant_id'], 'user_tenants_tenant_id_index');
                $this->addIndex($table, ['is_default'], 'user_tenants_is_default_index');
                $this->addIndex($table, ['user_id', 'tenant_id'], 'user_tenants_user_tenant_index');

                // Unique constraint: one user can only have one membership per tenant
                $this->addUniqueConstraint($table, ['user_id', 'tenant_id'], 'user_tenants_user_tenant_unique');
            });
        }

        // Backfill: Create pivot rows for existing users with tenant_id
        $this->backfillExistingTenantMemberships();
    }

    /**
     * Backfill existing tenant memberships from users.tenant_id
     */
    private function backfillExistingTenantMemberships(): void
    {
        // Only backfill if users table exists and has tenant_id column
        if (!Schema::hasTable('users') || !Schema::hasColumn('users', 'tenant_id')) {
            return;
        }

        // Get all users with tenant_id
        $usersWithTenants = DB::table('users')
            ->whereNotNull('tenant_id')
            ->whereNull('deleted_at')
            ->get(['id', 'tenant_id']);

        foreach ($usersWithTenants as $user) {
            // Check if pivot row already exists (avoid duplicates)
            $exists = DB::table('user_tenants')
                ->where('user_id', $user->id)
                ->where('tenant_id', $user->tenant_id)
                ->exists();

            if (!$exists) {
                // Insert pivot row with is_default=true for existing relationships
                DB::table('user_tenants')->insert([
                    'id' => (string) \Illuminate\Support\Str::ulid(),
                    'user_id' => $user->id,
                    'tenant_id' => $user->tenant_id,
                    'role' => 'owner', // Default role for existing relationships
                    'is_default' => true, // Mark as default since it's the only one
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_tenants');
    }
};
