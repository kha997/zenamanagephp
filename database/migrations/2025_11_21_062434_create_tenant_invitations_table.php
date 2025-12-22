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
     * Creates tenant_invitations table for managing tenant member invitations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('tenant_invitations')) {
            Schema::create('tenant_invitations', function (Blueprint $table) {
                $table->ulid('id')->primary();
                $table->string('tenant_id');
                $table->string('email');
                $table->string('role'); // Must match a key from config('permissions.tenant_roles')
                $table->string('token')->unique(); // Used for accept flow
                $table->string('status')->default('pending'); // pending, accepted, revoked, expired
                $table->string('invited_by')->nullable(); // FK to users.id
                $table->dateTime('expires_at')->nullable();
                $table->dateTime('accepted_at')->nullable();
                $table->dateTime('revoked_at')->nullable();
                $table->timestamps();

                // Foreign key constraints
                $this->addForeignKeyConstraint($table, 'tenant_id', 'id', 'tenants', 'cascade');
                $this->addForeignKeyConstraint($table, 'invited_by', 'id', 'users', 'set null');

                // Indexes
                $this->addIndex($table, ['tenant_id'], 'tenant_invitations_tenant_id_index');
                $this->addIndex($table, ['email'], 'tenant_invitations_email_index');
                $this->addIndex($table, ['status'], 'tenant_invitations_status_index');
                $this->addIndex($table, ['token'], 'tenant_invitations_token_index');
                $this->addIndex($table, ['tenant_id', 'email', 'status'], 'tenant_invitations_tenant_email_status_index');

                // Note: Unique constraint for (tenant_id, email, status='pending') is enforced
                // at application level to avoid duplicate pending invitations
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenant_invitations');
    }
};
