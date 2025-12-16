<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Add tenant_id column (nullable initially for backfill)
            $table->string('tenant_id')->nullable()->after('organization_id');
            
            // Add used_at column for single-use token tracking
            $table->timestamp('used_at')->nullable()->after('accepted_at');
            
            // Note column already exists, but ensure it's there
            if (!Schema::hasColumn('invitations', 'note')) {
                $table->text('note')->nullable()->after('message');
            }
        });

        // Backfill tenant_id from organization_id
        // Strategy: 
        // 1. Find users with matching organization_id and get their tenant_id
        // 2. If no user found, try to find tenant by matching organization name/slug
        // 3. If still no match, use the inviter's tenant_id
        // 4. If all else fails, use first available tenant (for existing data)
        if (DBDriver::isMysql()) {
            DB::statement("
                UPDATE invitations i
                LEFT JOIN users u ON u.organization_id = i.organization_id AND u.tenant_id IS NOT NULL
                LEFT JOIN organizations o ON o.id = i.organization_id
                LEFT JOIN tenants t ON (t.name = o.name OR t.slug = o.slug)
                LEFT JOIN users inviter ON inviter.id = i.invited_by AND inviter.tenant_id IS NOT NULL
                LEFT JOIN tenants default_tenant ON default_tenant.id IS NOT NULL
                SET i.tenant_id = COALESCE(
                    u.tenant_id, 
                    t.id, 
                    inviter.tenant_id,
                    (SELECT id FROM tenants LIMIT 1)
                )
                WHERE i.tenant_id IS NULL
            ");
        } else {
            // SQLite compatible version - use subqueries
            $defaultTenantId = DB::table('tenants')->value('id');
            
            DB::table('invitations')
                ->whereNull('tenant_id')
                ->get()
                ->each(function ($invitation) use ($defaultTenantId) {
                    // Try to find tenant via user with same organization_id
                    $userTenantId = DB::table('users')
                        ->where('organization_id', $invitation->organization_id)
                        ->whereNotNull('tenant_id')
                        ->value('tenant_id');
                    
                    if ($userTenantId) {
                        DB::table('invitations')
                            ->where('id', $invitation->id)
                            ->update(['tenant_id' => $userTenantId]);
                        return;
                    }
                    
                    // Try to find tenant by organization name
                    $org = DB::table('organizations')->where('id', $invitation->organization_id)->first();
                    if ($org) {
                        $tenantId = DB::table('tenants')
                            ->where('name', $org->name)
                            ->orWhere('slug', $org->slug)
                            ->value('id');
                        
                        if ($tenantId) {
                            DB::table('invitations')
                                ->where('id', $invitation->id)
                                ->update(['tenant_id' => $tenantId]);
                            return;
                        }
                    }
                    
                    // Use inviter's tenant_id
                    $inviterTenantId = DB::table('users')
                        ->where('id', $invitation->invited_by)
                        ->whereNotNull('tenant_id')
                        ->value('tenant_id');
                    
                    if ($inviterTenantId) {
                        DB::table('invitations')
                            ->where('id', $invitation->id)
                            ->update(['tenant_id' => $inviterTenantId]);
                        return;
                    }
                    
                    // Fallback to default tenant
                    if ($defaultTenantId) {
                        DB::table('invitations')
                            ->where('id', $invitation->id)
                            ->update(['tenant_id' => $defaultTenantId]);
                    }
                });
        }

        // Make tenant_id NOT NULL after backfill (only if we have data)
        $hasInvitations = DB::table('invitations')->exists();
        if ($hasInvitations) {
            // Ensure all invitations have tenant_id before making it NOT NULL
            $nullCount = DB::table('invitations')->whereNull('tenant_id')->count();
            if ($nullCount > 0) {
                // If still null, set to first tenant
                $firstTenant = DB::table('tenants')->value('id');
                if ($firstTenant) {
                    DB::table('invitations')->whereNull('tenant_id')->update(['tenant_id' => $firstTenant]);
                }
            }
        }

        Schema::table('invitations', function (Blueprint $table) use ($hasInvitations) {
            // Only make NOT NULL if we have invitations, otherwise keep nullable for new records
            if ($hasInvitations) {
                $table->string('tenant_id')->nullable(false)->change();
            }
            
            // Add foreign key constraint
            if (DBDriver::isMysql()) {
                try {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
                } catch (\Exception $e) {
                    // Foreign key might already exist
                }
            }
            
            // Update indexes - drop old organization_id index if it exists
            try {
                $table->dropIndex(['organization_id', 'status']);
            } catch (\Exception $e) {
                // Index might not exist
            }
            
            // Add new tenant_id indexes
            $table->index(['tenant_id', 'status'], 'invitations_tenant_status_index');
            $table->index(['tenant_id', 'email'], 'invitations_tenant_email_index');
            $table->index(['tenant_id', 'expires_at'], 'invitations_tenant_expires_index');
        });

        // Drop organization_id column (mark as deprecated, keep for now to avoid breaking existing code)
        // We'll remove it in a future migration after all code is updated
        // Schema::table('invitations', function (Blueprint $table) {
        //     $table->dropColumn('organization_id');
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table) {
            // Restore organization_id index if needed
            if (!Schema::hasColumn('invitations', 'organization_id')) {
                // If organization_id was dropped, we can't fully reverse
                // This is a one-way migration
                return;
            }
            
            // Drop tenant_id indexes
            $table->dropIndex(['tenant_id', 'status']);
            $table->dropIndex(['tenant_id', 'email']);
            $table->dropIndex(['tenant_id', 'expires_at']);
            
            // Drop foreign key
            if (DBDriver::isMysql()) {
                $table->dropForeign(['tenant_id']);
            }
            
            // Restore organization_id index
            $table->index(['organization_id', 'status']);
        });

        Schema::table('invitations', function (Blueprint $table) {
            // Drop added columns
            $table->dropColumn(['tenant_id', 'used_at']);
            
            // Note column might have existed before, so we don't drop it
        });
    }
};
