<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->foreignUlid('approver_id')->nullable()->after('approval_status')
                ->constrained('users')->nullOnDelete();
            $table->index(['tenant_id', 'approver_id'], 'documents_tenant_approver_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_tenant_approver_id_index');
            $table->dropConstrainedForeignId('approver_id');
        });
    }
};
