<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('invitations') || !Schema::hasColumn('invitations', 'token')) {
            return;
        }

        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('token')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('invitations') || !Schema::hasColumn('invitations', 'token')) {
            return;
        }

        Schema::table('invitations', function (Blueprint $table): void {
            $table->string('token')->nullable(false)->change();
        });
    }
};
