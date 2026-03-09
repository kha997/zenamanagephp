<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('change_request_comments', function (Blueprint $table) {
            $table->string('id')->primary(); // ULID
            $table->string('change_request_id'); // ULID
            $table->string('user_id'); // ULID
            $table->text('comment');
            $table->string('parent_id')->nullable(); // ULID for replies
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            // Foreign keys
            $table->foreign('change_request_id')->references('id')->on('change_requests')->onDelete('cascade');
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');

            // Indexes
            $table->index(['change_request_id', 'created_at']);
            $table->index(['user_id', 'created_at']);
            $table->index('parent_id');
        });

        // Add self-referencing foreign key after table creation
        Schema::table('change_request_comments', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('change_request_comments')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $this->dropForeignIfExists('change_request_comments', ['parent_id']);
        $this->dropTableIfExists('change_request_comments');

        $this->dropForeignIfExists('zena_change_request_comments', ['parent_id']);
        $this->dropTableIfExists('zena_change_request_comments');
    }

    private function dropTableIfExists(string $tableName): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::dropIfExists($tableName);
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }

    private function dropForeignIfExists(string $tableName, array|string $foreign): void
    {
        if (!Schema::hasTable($tableName)) {
            return;
        }

        try {
            Schema::table($tableName, function (Blueprint $table) use ($foreign) {
                $table->dropForeign($foreign);
            });
        } catch (\Throwable $e) {
            // Intentionally swallow for idempotent rollback in partial DB states.
        }
    }
};
