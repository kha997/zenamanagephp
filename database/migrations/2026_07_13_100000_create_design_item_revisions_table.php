<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('design_item_revisions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('tenant_id')->index();
            $table->string('design_item_id')->index();
            $table->unsignedInteger('revision_no');
            $table->text('client_feedback');
            $table->string('requested_by')->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['design_item_id', 'revision_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('design_item_revisions');
    }
};
