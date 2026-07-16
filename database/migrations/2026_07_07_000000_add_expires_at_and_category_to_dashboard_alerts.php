<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dashboard_alerts', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('triggered_at');
            $table->string('category')->nullable()->after('type');

            $table->index(['expires_at']);
            $table->index(['category']);
        });
    }

    public function down(): void
    {
        Schema::table('dashboard_alerts', function (Blueprint $table) {
            $table->dropIndex(['expires_at']);
            $table->dropIndex(['category']);
            $table->dropColumn(['expires_at', 'category']);
        });
    }
};
