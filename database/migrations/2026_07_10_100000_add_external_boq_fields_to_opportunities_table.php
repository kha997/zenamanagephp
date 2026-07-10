<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// zena-boq-core integration — spec: docs/superpowers/specs/2026-07-09-zena-ops-roadmap-design.md (Phase 2).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->string('external_boq_project_code')->nullable()->after('converted_project_id');
            $table->string('external_quote_id')->nullable()->after('external_boq_project_code');
            $table->json('external_quote_snapshot')->nullable()->after('external_quote_id');
            $table->timestamp('external_quote_synced_at')->nullable()->after('external_quote_snapshot');
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropColumn([
                'external_boq_project_code',
                'external_quote_id',
                'external_quote_snapshot',
                'external_quote_synced_at',
            ]);
        });
    }
};
