<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop the old constraint that references 'roles' table
        try {
            DB::statement('ALTER TABLE user_roles DROP FOREIGN KEY zena_user_roles_role_id_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }

        // Add the correct constraint that references 'zena_roles' table
        try {
            DB::statement('ALTER TABLE user_roles ADD CONSTRAINT user_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES zena_roles(id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            // Constraint might already exist, continue
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        try {
            DB::statement('ALTER TABLE user_roles DROP FOREIGN KEY user_roles_role_id_foreign');
        } catch (\Exception $e) {
            // Constraint might not exist, continue
        }

        try {
            DB::statement('ALTER TABLE user_roles ADD CONSTRAINT zena_user_roles_role_id_foreign FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE');
        } catch (\Exception $e) {
            // Constraint might already exist, continue
        }
    }
};