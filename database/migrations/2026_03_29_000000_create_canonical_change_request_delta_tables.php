<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cr_links')) {
            Schema::create('cr_links', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('change_request_id');
                $table->string('linked_type');
                $table->string('linked_id');
                $table->text('link_description')->nullable();
                $table->timestamps();

                $table->foreign('change_request_id')->references('id')->on('change_requests')->onDelete('cascade');
                $table->index(['change_request_id', 'linked_type']);
                $table->unique(['change_request_id', 'linked_type', 'linked_id'], 'cr_links_unique_link');
            });
        }

        if (! Schema::hasTable('baselines')) {
            Schema::create('baselines', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('project_id');
                $table->string('type');
                $table->date('start_date');
                $table->date('end_date');
                $table->decimal('cost', 15, 2)->default(0);
                $table->string('linked_contract_id')->nullable();
                $table->unsignedInteger('version')->default(1);
                $table->text('note')->nullable();
                $table->string('created_by');
                $table->timestamps();

                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('linked_contract_id')->references('id')->on('change_requests')->onDelete('set null');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->index(['project_id', 'type', 'version']);
                $table->index(['linked_contract_id', 'type']);
            });
        }

        if (! Schema::hasTable('baseline_history')) {
            Schema::create('baseline_history', function (Blueprint $table) {
                $table->id();
                $table->ulid('baseline_id');
                $table->unsignedInteger('from_version')->default(0);
                $table->unsignedInteger('to_version');
                $table->text('note')->nullable();
                $table->string('created_by');
                $table->timestamps();

                $table->foreign('baseline_id')->references('id')->on('baselines')->onDelete('cascade');
                $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
                $table->index(['baseline_id', 'to_version']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('baseline_history');
        Schema::dropIfExists('baselines');
        Schema::dropIfExists('cr_links');
    }
};
