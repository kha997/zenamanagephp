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
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            DB::statement('PRAGMA foreign_keys=OFF');

            $existing = DB::table('user_roles')->get();

            Schema::dropIfExists('user_roles_temp');
            Schema::create('user_roles_temp', function (Blueprint $table) {
                $table->ulid('user_id');
                $table->ulid('role_id');
                $table->timestamps();

                $table->primary(['user_id', 'role_id']);
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('zena_roles')->onDelete('cascade');
            });

            foreach ($existing as $row) {
                DB::table('user_roles_temp')->insert([
                    'user_id' => $row->user_id,
                    'role_id' => $row->role_id,
                    'created_at' => $row->created_at,
                    'updated_at' => $row->updated_at,
                ]);
            }

            Schema::drop('user_roles');
            Schema::rename('user_roles_temp', 'user_roles');

            DB::statement('PRAGMA foreign_keys=ON');

            return;
        }

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
