<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Support\DBDriver;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Drop duplicate columns first (separate operations for SQLite)
        if (Schema::hasColumn('notifications', 'message')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('message');
            });
        }
        
        if (Schema::hasColumn('notifications', 'is_read')) {
            Schema::table('notifications', function (Blueprint $table) {
                $table->dropColumn('is_read');
            });
        }
        
        // Add missing columns
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'priority')) {
                $table->enum('priority', ['critical', 'normal', 'low'])->default('normal')->after('type');
            }
            
            if (!Schema::hasColumn('notifications', 'body')) {
                $table->text('body')->nullable()->after('title');
            }
            
            if (!Schema::hasColumn('notifications', 'link_url')) {
                $table->string('link_url')->nullable()->after('body');
            }
            
            if (!Schema::hasColumn('notifications', 'channel')) {
                $table->enum('channel', ['inapp', 'email', 'webhook'])->default('inapp')->after('link_url');
            }
            
            if (!Schema::hasColumn('notifications', 'metadata')) {
                $table->json('metadata')->nullable()->after('data');
            }
            
            if (!Schema::hasColumn('notifications', 'event_key')) {
                $table->string('event_key')->nullable()->after('metadata');
            }
            
            if (!Schema::hasColumn('notifications', 'project_id')) {
                $table->ulid('project_id')->nullable()->after('event_key');
            }
        });
        
        // Add foreign key constraints
        Schema::table('notifications', function (Blueprint $table) {
            if (!Schema::hasColumn('notifications', 'project_id')) {
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
            }
        });
        
        // Add indexes
        Schema::table('notifications', function (Blueprint $table) {
            $indexes = [
                'notifications_user_id_read_at_index',
                'notifications_priority_index',
                'notifications_channel_index',
                'notifications_project_id_index',
                'notifications_event_key_index'
            ];
            
            foreach ($indexes as $index) {
                if (!$this->indexExists($index)) {
                    switch ($index) {
                        case 'notifications_user_id_read_at_index':
                            $table->index(['user_id', 'read_at']);
                            break;
                        case 'notifications_priority_index':
                            $table->index('priority');
                            break;
                        case 'notifications_channel_index':
                            $table->index('channel');
                            break;
                        case 'notifications_project_id_index':
                            $table->index('project_id');
                            break;
                        case 'notifications_event_key_index':
                            $table->index('event_key');
                            break;
                    }
                }
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            // Drop foreign keys
            if (DBDriver::isMysql()) {
                $table->dropForeign(['project_id']);
            }
            
            // Drop indexes
            $table->dropIndex(['user_id', 'read_at']);
            $table->dropIndex(['priority']);
            $table->dropIndex(['channel']);
            $table->dropIndex(['project_id']);
            $table->dropIndex(['event_key']);
            
            // Drop columns
            $table->dropColumn([
                'priority',
                'body',
                'link_url',
                'channel',
                'metadata',
                'event_key',
                'project_id'
            ]);
        });
    }
    
    /**
     * Check if index exists (compatible with SQLite and MySQL)
     */
    private function indexExists(string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();
        
        if ($connection->getDriverName() === 'sqlite') {
            $indexes = $connection->select("PRAGMA index_list('notifications')");
            foreach ($indexes as $index) {
                if ($index->name === $indexName) {
                    return true;
                }
            }
            return false;
        } else {
            $indexes = $connection->select("SHOW INDEX FROM notifications WHERE Key_name = ?", [$indexName]);
            return count($indexes) > 0;
        }
    }
};
