<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('zena_material_requests')) {
            return;
        }

        if ($this->usesSqlite()) {
            $this->rebuildTable('projects');
            return;
        }

        Schema::table('zena_material_requests', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('zena_material_requests', function (Blueprint $table): void {
            $table->foreign('project_id')->references('id')->on('projects')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('zena_material_requests')) {
            return;
        }

        if ($this->usesSqlite()) {
            $this->rebuildTable('zena_projects');
            return;
        }

        Schema::table('zena_material_requests', function (Blueprint $table): void {
            $table->dropForeign(['project_id']);
        });

        Schema::table('zena_material_requests', function (Blueprint $table): void {
            $table->foreign('project_id')->references('id')->on('zena_projects')->cascadeOnDelete();
        });
    }

    private function usesSqlite(): bool
    {
        return Schema::getConnection()->getDriverName() === 'sqlite';
    }

    private function rebuildTable(string $projectTable): void
    {
        $temporaryTable = 'zena_material_requests_rebuild';

        Schema::disableForeignKeyConstraints();

        Schema::dropIfExists($temporaryTable);

        Schema::create($temporaryTable, function (Blueprint $table) use ($projectTable): void {
            $table->ulid('id')->primary();
            $table->ulid('project_id');
            $table->string('request_number');
            $table->text('description');
            $table->enum('status', ['draft', 'submitted', 'approved', 'rejected', 'fulfilled'])->default('draft');
            $table->decimal('estimated_cost', 15, 2)->nullable();
            $table->date('required_date')->nullable();
            $table->ulid('requested_by');
            $table->ulid('approved_by')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->foreign('project_id')->references('id')->on($projectTable)->cascadeOnDelete();
            $table->foreign('requested_by')->references('id')->on('users')->cascadeOnDelete();
            $table->foreign('approved_by')->references('id')->on('users')->nullOnDelete();
            $table->index(['project_id', 'status']);
        });

        DB::table($temporaryTable)->insertUsing(
            [
                'id',
                'project_id',
                'request_number',
                'description',
                'status',
                'estimated_cost',
                'required_date',
                'requested_by',
                'approved_by',
                'approved_at',
                'created_at',
                'updated_at',
            ],
            DB::table('zena_material_requests')->select([
                'id',
                'project_id',
                'request_number',
                'description',
                'status',
                'estimated_cost',
                'required_date',
                'requested_by',
                'approved_by',
                'approved_at',
                'created_at',
                'updated_at',
            ])
        );

        Schema::drop('zena_material_requests');
        Schema::rename($temporaryTable, 'zena_material_requests');

        Schema::enableForeignKeyConstraints();
    }
};
