<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->string('lifecycle_status', 32)->nullable()->after('status');
            $table->string('approval_status', 32)->nullable()->after('lifecycle_status');
            $table->index(['tenant_id', 'lifecycle_status'], 'documents_tenant_lifecycle_status_index');
            $table->index(['tenant_id', 'approval_status'], 'documents_tenant_approval_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table): void {
            $table->dropIndex('documents_tenant_lifecycle_status_index');
            $table->dropIndex('documents_tenant_approval_status_index');
            $table->dropColumn(['lifecycle_status', 'approval_status']);
        });
    }
};
