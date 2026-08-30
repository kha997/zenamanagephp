<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * GAP-048 §9 — remove the DB-level `DEFAULT 'architecture'` safety net and
 * make `service_category` nullable. Historical data is not touched or
 * reclassified; only the mechanism producing new false values is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('service_category')->nullable()->default(null)->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table): void {
            $table->string('service_category')->default('architecture')->change();
        });
    }
};
