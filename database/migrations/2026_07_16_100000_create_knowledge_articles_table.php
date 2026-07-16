<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('knowledge_articles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained();
            $table->string('type', 30);
            $table->string('title', 255);
            $table->string('category', 100)->nullable();
            $table->text('body')->nullable();
            $table->json('checklist_items')->nullable();
            $table->json('tags')->nullable();
            $table->foreignUlid('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->string('status', 20)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->foreignUlid('created_by')->nullable()->constrained('users');
            $table->foreignUlid('updated_by')->nullable()->constrained('users');
            $table->timestamps();

            $table->index(['tenant_id', 'type', 'status'], 'knowledge_articles_tenant_type_status_index');
            $table->index(['tenant_id', 'category'], 'knowledge_articles_tenant_category_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('knowledge_articles');
    }
};
