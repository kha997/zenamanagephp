<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Check if constraint or index exists
     */
    private function constraintExists(string $table, string $constraintName): bool
    {
        try {
            // For SQLite, we'll use a simpler approach
            if (DB::getDriverName() === 'sqlite') {
                // Check if the constraint exists by trying to create it
                // This is a simplified check for SQLite
                return false; // Always return false to allow creation
            }
            
            // For MySQL/PostgreSQL, use information_schema
            $constraints = DB::select("
                SELECT CONSTRAINT_NAME 
                FROM information_schema.TABLE_CONSTRAINTS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND CONSTRAINT_NAME = ?
            ", [$table, $constraintName]);
            
            if (count($constraints) > 0) {
                return true;
            }
            
            // Check indexes
            $indexes = DB::select("
                SELECT INDEX_NAME 
                FROM information_schema.STATISTICS 
                WHERE TABLE_SCHEMA = DATABASE() 
                AND TABLE_NAME = ? 
                AND INDEX_NAME = ?
            ", [$table, $constraintName]);
            
            return count($indexes) > 0;
        } catch (\Exception $e) {
            // If there's any error, assume constraint doesn't exist
            return false;
        }
    }

    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            // OIDC fields
            if (!Schema::hasColumn('users', 'oidc_provider')) {
                $table->string('oidc_provider')->nullable()->after('password_history');
            }
            if (!Schema::hasColumn('users', 'oidc_subject_id')) {
                $table->string('oidc_subject_id')->nullable()->after('oidc_provider');
            }
            if (!Schema::hasColumn('users', 'oidc_data')) {
                $table->json('oidc_data')->nullable()->after('oidc_subject_id');
            }
            
            // SAML fields
            if (!Schema::hasColumn('users', 'saml_provider')) {
                $table->string('saml_provider')->nullable()->after('oidc_data');
            }
            if (!Schema::hasColumn('users', 'saml_name_id')) {
                $table->string('saml_name_id')->nullable()->after('saml_provider');
            }
            if (!Schema::hasColumn('users', 'saml_data')) {
                $table->json('saml_data')->nullable()->after('saml_name_id');
            }
            
            // Additional user fields
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('saml_data');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'department')) {
                $table->string('department')->nullable()->after('last_name');
            }
            if (!Schema::hasColumn('users', 'job_title')) {
                $table->string('job_title')->nullable()->after('department');
            }
            if (!Schema::hasColumn('users', 'manager')) {
                $table->string('manager')->nullable()->after('job_title');
            }
            
            // Indexes
            if (!$this->constraintExists('users', 'users_oidc_provider_index')) {
                $table->index('oidc_provider');
            }
            if (!$this->constraintExists('users', 'users_oidc_subject_id_index')) {
                $table->index('oidc_subject_id');
            }
            if (!$this->constraintExists('users', 'users_saml_provider_index')) {
                $table->index('saml_provider');
            }
            if (!$this->constraintExists('users', 'users_saml_name_id_index')) {
                $table->index('saml_name_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->constraintExists('users', 'users_oidc_provider_index')) {
                $table->dropIndex(['oidc_provider']);
            }
            if ($this->constraintExists('users', 'users_oidc_subject_id_index')) {
                $table->dropIndex(['oidc_subject_id']);
            }
            if ($this->constraintExists('users', 'users_saml_provider_index')) {
                $table->dropIndex(['saml_provider']);
            }
            if ($this->constraintExists('users', 'users_saml_name_id_index')) {
                $table->dropIndex(['saml_name_id']);
            }
            
            if (Schema::hasColumn('users', 'oidc_provider')) {
                $table->dropColumn('oidc_provider');
            }
            if (Schema::hasColumn('users', 'oidc_subject_id')) {
                $table->dropColumn('oidc_subject_id');
            }
            if (Schema::hasColumn('users', 'oidc_data')) {
                $table->dropColumn('oidc_data');
            }
            if (Schema::hasColumn('users', 'saml_provider')) {
                $table->dropColumn('saml_provider');
            }
            if (Schema::hasColumn('users', 'saml_name_id')) {
                $table->dropColumn('saml_name_id');
            }
            if (Schema::hasColumn('users', 'saml_data')) {
                $table->dropColumn('saml_data');
            }
            if (Schema::hasColumn('users', 'first_name')) {
                $table->dropColumn('first_name');
            }
            if (Schema::hasColumn('users', 'last_name')) {
                $table->dropColumn('last_name');
            }
            if (Schema::hasColumn('users', 'department')) {
                $table->dropColumn('department');
            }
            if (Schema::hasColumn('users', 'job_title')) {
                $table->dropColumn('job_title');
            }
            if (Schema::hasColumn('users', 'manager')) {
                $table->dropColumn('manager');
            }
        });
    }
};