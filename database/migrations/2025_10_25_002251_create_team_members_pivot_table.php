<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\SqliteCompatibleMigration;
use App\Support\DBDriver;

return new class extends Migration
{
    use SqliteCompatibleMigration;

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('team_members')) {
            Schema::create('team_members', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('team_id');
                $table->string('user_id');
                $table->string('role')->default('member');
                $table->boolean('is_active')->default(true);
                $table->timestamps();

                // Add foreign key constraints if supported
                $this->addForeignKeyConstraint($table, 'team_id', 'id', 'teams');
                $this->addForeignKeyConstraint($table, 'user_id', 'id', 'users');

                // Add indexes
                $this->addIndex($table, ['team_id', 'user_id'], 'team_members_team_user_index');
                $this->addIndex($table, ['user_id'], 'team_members_user_index');
                $this->addIndex($table, ['is_active'], 'team_members_active_index');

                // Add unique constraint
                $this->addUniqueConstraint($table, ['team_id', 'user_id'], 'team_members_team_user_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_members');
    }
};