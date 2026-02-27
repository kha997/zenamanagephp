<?php declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('deliverable_template_versions')) {
            return;
        }

        Schema::table('deliverable_template_versions', function (Blueprint $table): void {
            if (!Schema::hasColumn('deliverable_template_versions', 'semver')) {
                $table->string('semver')->nullable()->after('deliverable_template_id');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'storage_path')) {
                $table->string('storage_path')->nullable()->after('semver');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'checksum_sha256')) {
                $table->string('checksum_sha256', 64)->nullable()->after('storage_path');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'mime')) {
                $table->string('mime')->nullable()->after('checksum_sha256');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'size')) {
                $table->unsignedBigInteger('size')->nullable()->after('mime');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'placeholders_spec_json')) {
                $table->json('placeholders_spec_json')->nullable()->after('size');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'created_by')) {
                $table->string('created_by')->nullable()->after('published_by');
            }

            if (!Schema::hasColumn('deliverable_template_versions', 'updated_by')) {
                $table->string('updated_by')->nullable()->after('created_by');
            }
        });

        if (Schema::hasColumn('deliverable_template_versions', 'version')
            && Schema::hasColumn('deliverable_template_versions', 'semver')) {
            DB::table('deliverable_template_versions')
                ->whereNull('semver')
                ->update(['semver' => DB::raw('version')]);
        }

        if (Schema::hasColumn('deliverable_template_versions', 'metadata_json')
            && Schema::hasColumn('deliverable_template_versions', 'placeholders_spec_json')) {
            DB::table('deliverable_template_versions')
                ->whereNull('placeholders_spec_json')
                ->update(['placeholders_spec_json' => DB::raw('metadata_json')]);
        }

        Schema::table('deliverable_template_versions', function (Blueprint $table): void {
            try {
                $table->dropUnique('dt_versions_template_version_unique');
            } catch (\Throwable) {
                // Index may not exist depending on environment history.
            }

            try {
                $table->unique(['deliverable_template_id', 'semver'], 'dt_versions_template_semver_unique');
            } catch (\Throwable) {
                // Ignore if it already exists.
            }

            try {
                $table->index(['tenant_id', 'deliverable_template_id'], 'dt_versions_tenant_template_index');
            } catch (\Throwable) {
                // Ignore if it already exists.
            }

            try {
                $table->index(['tenant_id', 'published_at'], 'dt_versions_tenant_published_index');
            } catch (\Throwable) {
                // Ignore if it already exists.
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('deliverable_template_versions')) {
            return;
        }

        Schema::table('deliverable_template_versions', function (Blueprint $table): void {
            try {
                $table->dropUnique('dt_versions_template_semver_unique');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('dt_versions_tenant_template_index');
            } catch (\Throwable) {
            }

            try {
                $table->dropIndex('dt_versions_tenant_published_index');
            } catch (\Throwable) {
            }

            try {
                $table->unique(['deliverable_template_id', 'version'], 'dt_versions_template_version_unique');
            } catch (\Throwable) {
            }

            $columns = [];
            foreach (['semver', 'storage_path', 'checksum_sha256', 'mime', 'size', 'placeholders_spec_json', 'created_by', 'updated_by'] as $column) {
                if (Schema::hasColumn('deliverable_template_versions', $column)) {
                    $columns[] = $column;
                }
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
