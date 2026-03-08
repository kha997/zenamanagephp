<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Add missing created_at indexes for time-based queries
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['created_at'], 'projects_created_at_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['created_at'], 'users_created_at_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        // Add composite indexes for common N+1 patterns
        try {
            Schema::table('projects', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'projects_tenant_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        try {
            Schema::table('users', function (Blueprint $table) {
                $table->index(['tenant_id', 'status'], 'users_tenant_status_index');
            });
        } catch (\Exception $e) {
            // Index might already exist
        }
        
        // Add indexes for document relationships
        try {
            Schema::table('document_versions', function (Blueprint $table) {
                $table->index(['document_id', 'created_at'], 'document_versions_document_created_index');
                $table->index(['created_by', 'created_at'], 'document_versions_created_by_created_index');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
        
        // Add indexes for task assignments
        try {
            Schema::table('task_assignments', function (Blueprint $table) {
                $table->index(['task_id', 'user_id'], 'task_assignments_task_user_index');
                $table->index(['user_id', 'created_at'], 'task_assignments_user_created_index');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
        
        // Add indexes for project team members
        try {
            Schema::table('project_team_members', function (Blueprint $table) {
                $table->index(['project_id', 'user_id'], 'project_team_members_project_user_index');
                $table->index(['user_id', 'created_at'], 'project_team_members_user_created_index');
            });
        } catch (\Exception $e) {
            // Indexes might already exist
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            try {
                $table->dropIndex('projects_created_at_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('projects_tenant_status_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
        
        Schema::table('tasks', function (Blueprint $table) {
            try {
                $table->dropIndex('tasks_created_at_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('tasks_project_status_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('tasks_assignee_status_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
        
        Schema::table('users', function (Blueprint $table) {
            try {
                $table->dropIndex('users_created_at_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('users_tenant_status_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
        
        Schema::table('document_versions', function (Blueprint $table) {
            try {
                $table->dropIndex('document_versions_document_created_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('document_versions_created_by_created_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
        
        Schema::table('task_assignments', function (Blueprint $table) {
            try {
                $table->dropIndex('task_assignments_task_user_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('task_assignments_user_created_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
        
        Schema::table('project_team_members', function (Blueprint $table) {
            try {
                $table->dropIndex('project_team_members_project_user_index');
            } catch (\Exception $e) {
                // Index might not exist
            }

            try {
                $table->dropIndex('project_team_members_user_created_index');
            } catch (\Exception $e) {
                // Index might not exist
            }
        });
    }
};
