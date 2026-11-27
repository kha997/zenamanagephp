<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * OWN-2026-024 (O-17h) H2b — additive, backward-compatible (classification: expand).
 *
 * 1. role_permission_revocations: durable, deliberate revocations (D4). A row with superseded_at NULL is ACTIVE; `active_key`
 *    is "<role_id>|<permission_code>" only while active (NULL once superseded), and its UNIQUE index makes two concurrent active
 *    revocations of the same grant impossible on both MySQL and SQLite (NULLs never collide).
 * 2. platform_operators: the independent, server-provisioned Platform Operator ledger (D6). `active_key` = user_id while active.
 * 3. role_permissions.grant_source (nullable): 'seed' | 'manual' | 'platform'. NULL = granted before H2b (unknown origin).
 *
 * No existing row is changed, nothing is backfilled, no grant is revoked. down() drops exactly what up() added.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('role_permission_revocations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('role_id', 26);
            $table->string('permission_id', 26);
            $table->string('permission_code');
            $table->string('tenant_id', 26)->nullable(); // the role's tenant scope; NULL = global role
            $table->string('revoked_by_user_id', 26)->nullable(); // app user, when the actor is one
            $table->string('revoked_by_actor'); // e.g. "user:<id>" or "server-cli:<os user>"
            $table->text('reason');
            $table->string('approval_reference')->nullable();
            $table->unsignedBigInteger('audit_log_id')->nullable();
            $table->timestamp('revoked_at');
            $table->timestamp('superseded_at')->nullable();
            $table->string('superseded_by_actor')->nullable();
            $table->text('superseded_reason')->nullable();
            $table->unsignedBigInteger('superseded_audit_log_id')->nullable();
            $table->string('active_key')->nullable()->unique('rpr_active_key_unique');
            $table->timestamps();

            $table->index(['role_id', 'permission_code'], 'rpr_role_code_index');
        });

        Schema::create('platform_operators', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('user_id', 26);
            $table->string('granted_by_actor');
            $table->text('reason');
            $table->string('approval_reference');
            $table->unsignedBigInteger('grant_audit_log_id')->nullable();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable();
            $table->string('revoked_by_actor')->nullable();
            $table->text('revoke_reason')->nullable();
            $table->unsignedBigInteger('revoke_audit_log_id')->nullable();
            $table->string('active_key')->nullable()->unique('platform_operators_active_key_unique');
            $table->timestamps();

            $table->index('user_id', 'platform_operators_user_index');
        });

        if (!Schema::hasColumn('role_permissions', 'grant_source')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->string('grant_source', 16)->nullable();
            });
        }
    }

    public function down(): void
    {
        // OWN-2026-024 H3 (Gate 6): dropping the ledgers after real revocations / provisioning would silently restore revoked
        // access on the next production:rbac (the seed filter would have nothing to read). Refuse; the Owner must first
        // re-grant or otherwise decide each record through the governed commands (manual gate, see 104 §7).
        $active = 0;
        if (Schema::hasTable('role_permission_revocations')) {
            $active += \Illuminate\Support\Facades\DB::table('role_permission_revocations')->whereNull('superseded_at')->count();
        }
        if (Schema::hasTable('platform_operators')) {
            $active += \Illuminate\Support\Facades\DB::table('platform_operators')->whereNotNull('active_key')->count();
        }
        if ($active > 0) {
            throw new \RuntimeException("Refusing to roll back OWN-2026-024 H2b: {$active} active revocation / Platform Operator record(s) would be lost and revoked access could be restored by the next seeding. Resolve them through rbac:permission / platform:operator first.");
        }

        if (Schema::hasColumn('role_permissions', 'grant_source')) {
            Schema::table('role_permissions', function (Blueprint $table) {
                $table->dropColumn('grant_source');
            });
        }
        Schema::dropIfExists('platform_operators');
        Schema::dropIfExists('role_permission_revocations');
    }
};
