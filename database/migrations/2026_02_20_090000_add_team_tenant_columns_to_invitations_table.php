<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            if (!Schema::hasColumn('invitations', 'tenant_id')) {
                $table->ulid('tenant_id')->nullable()->after('id');
                $table->index(['tenant_id', 'status'], 'invitations_tenant_status_idx');
            }

            if (!Schema::hasColumn('invitations', 'team_id')) {
                $table->ulid('team_id')->nullable()->after('tenant_id');
                $table->index(['tenant_id', 'team_id', 'status'], 'invitations_tenant_team_status_idx');
            }

            if (!Schema::hasColumn('invitations', 'invited_by_user_id')) {
                $table->ulid('invited_by_user_id')->nullable()->after('invited_by');
                $table->index('invited_by_user_id', 'invitations_invited_by_user_idx');
            }

            if (!Schema::hasColumn('invitations', 'accepted_by_user_id')) {
                $table->ulid('accepted_by_user_id')->nullable()->after('accepted_by');
                $table->index('accepted_by_user_id', 'invitations_accepted_by_user_idx');
            }

            if (!Schema::hasColumn('invitations', 'revoked_at')) {
                $table->timestamp('revoked_at')->nullable()->after('accepted_at');
            }

            if (!Schema::hasColumn('invitations', 'revoked_by_user_id')) {
                $table->ulid('revoked_by_user_id')->nullable()->after('revoked_at');
                $table->index('revoked_by_user_id', 'invitations_revoked_by_user_idx');
            }
        });

        Schema::table('invitations', function (Blueprint $table): void {
            if (Schema::hasColumn('invitations', 'tenant_id')) {
                try {
                    $table->foreign('tenant_id')->references('id')->on('tenants')->nullOnDelete();
                } catch (\Throwable $e) {
                    // Keep migration idempotent for existing databases.
                }
            }

            if (Schema::hasColumn('invitations', 'team_id')) {
                try {
                    $table->foreign('team_id')->references('id')->on('teams')->nullOnDelete();
                } catch (\Throwable $e) {
                    // Keep migration idempotent for existing databases.
                }
            }

            if (Schema::hasColumn('invitations', 'invited_by_user_id')) {
                try {
                    $table->foreign('invited_by_user_id')->references('id')->on('users')->nullOnDelete();
                } catch (\Throwable $e) {
                    // Keep migration idempotent for existing databases.
                }
            }

            if (Schema::hasColumn('invitations', 'accepted_by_user_id')) {
                try {
                    $table->foreign('accepted_by_user_id')->references('id')->on('users')->nullOnDelete();
                } catch (\Throwable $e) {
                    // Keep migration idempotent for existing databases.
                }
            }

            if (Schema::hasColumn('invitations', 'revoked_by_user_id')) {
                try {
                    $table->foreign('revoked_by_user_id')->references('id')->on('users')->nullOnDelete();
                } catch (\Throwable $e) {
                    // Keep migration idempotent for existing databases.
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            foreach ([
                'invitations_tenant_status_idx',
                'invitations_tenant_team_status_idx',
                'invitations_invited_by_user_idx',
                'invitations_accepted_by_user_idx',
                'invitations_revoked_by_user_idx',
            ] as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            foreach ([
                'invitations_tenant_id_foreign',
                'invitations_team_id_foreign',
                'invitations_invited_by_user_id_foreign',
                'invitations_accepted_by_user_id_foreign',
                'invitations_revoked_by_user_id_foreign',
            ] as $foreignName) {
                try {
                    $table->dropForeign($foreignName);
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            if (Schema::hasColumn('invitations', 'revoked_by_user_id')) {
                $table->dropColumn('revoked_by_user_id');
            }

            if (Schema::hasColumn('invitations', 'revoked_at')) {
                $table->dropColumn('revoked_at');
            }

            if (Schema::hasColumn('invitations', 'accepted_by_user_id')) {
                $table->dropColumn('accepted_by_user_id');
            }

            if (Schema::hasColumn('invitations', 'invited_by_user_id')) {
                $table->dropColumn('invited_by_user_id');
            }

            if (Schema::hasColumn('invitations', 'team_id')) {
                $table->dropColumn('team_id');
            }

            if (Schema::hasColumn('invitations', 'tenant_id')) {
                $table->dropColumn('tenant_id');
            }
        });
    }
};
