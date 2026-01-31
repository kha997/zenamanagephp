<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('audit_logs', 'route')) {
                $table->string('route')->nullable()->after('user_agent');
            }

            if (!Schema::hasColumn('audit_logs', 'method')) {
                $table->string('method', 10)->nullable()->after('route');
            }

            if (!Schema::hasColumn('audit_logs', 'status_code')) {
                $table->unsignedSmallInteger('status_code')->nullable()->after('method');
            }

            if (!Schema::hasColumn('audit_logs', 'meta')) {
                $table->json('meta')->nullable()->after('status_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            if (Schema::hasColumn('audit_logs', 'meta')) {
                $table->dropColumn('meta');
            }

            if (Schema::hasColumn('audit_logs', 'status_code')) {
                $table->dropColumn('status_code');
            }

            if (Schema::hasColumn('audit_logs', 'method')) {
                $table->dropColumn('method');
            }

            if (Schema::hasColumn('audit_logs', 'route')) {
                $table->dropColumn('route');
            }
        });
    }
};
