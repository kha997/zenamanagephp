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
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'tenant_id')) {
                $this->addColumnWithPositioning($table, 'tenant_id', 'ulid', ['nullable' => true], 'id');
                $this->addForeignKeyConstraint($table, 'tenant_id', 'id', 'tenants', 'cascade');
                $this->addIndex($table, ['tenant_id'], 'idx_users_tenant_id');
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
            // Only drop foreign keys for MySQL
            if (DBDriver::isMysql()) {
                $this->dropForeignKeyConstraint($table, 'tenant_id');
            }
            $table->dropIndex(['tenant_id']);
            $table->dropColumn('tenant_id');
        });
    }
};
