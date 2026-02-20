<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            if (!Schema::hasColumn('invitations', 'token_hash')) {
                $table->string('token_hash', 64)->nullable()->after('token');
                $table->index('token_hash', 'invitations_token_hash_idx');
            }

            if (!Schema::hasColumn('invitations', 'token_version')) {
                $table->unsignedSmallInteger('token_version')->nullable()->after('token_hash');
            }

            if (
                Schema::hasColumn('invitations', 'tenant_id')
                && Schema::hasColumn('invitations', 'team_id')
                && Schema::hasColumn('invitations', 'status')
                && Schema::hasColumn('invitations', 'expires_at')
            ) {
                $table->index(
                    ['tenant_id', 'team_id', 'status', 'expires_at'],
                    'invitations_tenant_team_status_expiry_idx'
                );
            }
        });

        DB::table('invitations')
            ->select(['id', 'token'])
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $token = (string) ($row->token ?? '');
                    if ($token === '') {
                        continue;
                    }

                    DB::table('invitations')
                        ->where('id', $row->id)
                        ->whereNull('token_hash')
                        ->update([
                            'token_hash' => hash('sha256', $token),
                            'token_version' => 1,
                        ]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            foreach ([
                'invitations_token_hash_idx',
                'invitations_tenant_team_status_expiry_idx',
            ] as $indexName) {
                try {
                    $table->dropIndex($indexName);
                } catch (\Throwable $e) {
                    // no-op for idempotent rollback
                }
            }

            if (Schema::hasColumn('invitations', 'token_version')) {
                $table->dropColumn('token_version');
            }

            if (Schema::hasColumn('invitations', 'token_hash')) {
                $table->dropColumn('token_hash');
            }
        });
    }
};
