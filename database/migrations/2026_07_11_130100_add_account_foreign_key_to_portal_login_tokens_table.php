<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('portal_login_tokens', function (Blueprint $table): void {
            $table->foreign('account_id', 'portal_login_tokens_account_id_foreign')
                ->references('id')->on('accounts')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('portal_login_tokens', function (Blueprint $table): void {
            $table->dropForeign('portal_login_tokens_account_id_foreign');
        });
    }
};
