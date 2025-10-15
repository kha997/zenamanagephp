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
        // Thêm các columns mới cần thiết cho unified Project model
        Schema::table('projects', function (Blueprint $table) {
            // Thêm các columns mới nếu chưa có
            if (!Schema::hasColumn('projects', 'budget_planned')) {
                $table->decimal('budget_planned', 15, 2)->default(0)->after('budget_total');
            }
            
            if (!Schema::hasColumn('projects', 'budget_actual')) {
                $table->decimal('budget_actual', 15, 2)->default(0)->after('budget_planned');
            }
            
            if (!Schema::hasColumn('projects', 'priority')) {
                $table->enum('priority', ['low', 'normal', 'medium', 'high', 'urgent'])->default('normal')->after('status');
            }
            
            if (!Schema::hasColumn('projects', 'settings')) {
                $table->json('settings')->nullable()->after('tags');
            }
            
            // Thêm soft deletes nếu chưa có
            if (!Schema::hasColumn('projects', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Migrate data từ ZenaProject sang Project
        $this->migrateZenaProjectData();
        
        // Tạo bảng project_team_members nếu chưa có
        if (!Schema::hasTable('project_team_members')) {
            Schema::create('project_team_members', function (Blueprint $table) {
                $table->id();
                $table->string('project_id');
                $table->string('user_id');
                $table->string('role')->default('member');
                $table->timestamp('joined_at')->nullable();
                $table->timestamp('left_at')->nullable();
                $table->timestamps();
                
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->unique(['project_id', 'user_id']);
                $table->index(['project_id', 'role']);
            });
        }

        // Tạo bảng project_milestones nếu chưa có
        if (!Schema::hasTable('project_milestones')) {
            Schema::create('project_milestones', function (Blueprint $table) {
                $table->id();
                $table->string('project_id');
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('target_date')->nullable();
                $table->date('completed_date')->nullable();
                $table->string('status')->default('pending'); // pending, completed, overdue
                $table->integer('order')->default(0);
                $table->timestamps();
                
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->index(['project_id', 'status']);
                $table->index(['project_id', 'order']);
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop new tables
        Schema::dropIfExists('project_milestones');
        Schema::dropIfExists('project_team_members');
        
        // Remove added columns
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'budget_planned',
                'budget_actual', 
                'priority',
                'settings',
                'deleted_at'
            ]);
        });
    }

    /**
     * Migrate data từ ZenaProject sang Project
     */
    private function migrateZenaProjectData(): void
    {
        // Kiểm tra xem có bảng zena_projects không
        if (!Schema::hasTable('zena_projects')) {
            return;
        }

        // Lấy tất cả data từ zena_projects
        $zenaProjects = DB::table('zena_projects')->get();
        
        foreach ($zenaProjects as $zenaProject) {
            // Kiểm tra xem project đã tồn tại chưa
            $existingProject = DB::table('projects')->where('id', $zenaProject->id)->first();
            
            if (!$existingProject) {
                // Tạo project mới từ zena_project data
                DB::table('projects')->insert([
                    'id' => $zenaProject->id,
                    'tenant_id' => $zenaProject->tenant_id ?? 'default-tenant',
                    'code' => $zenaProject->code ?? $this->generateProjectCode(),
                    'name' => $zenaProject->name,
                    'description' => $zenaProject->description,
                    'client_id' => $zenaProject->client_id,
                    'pm_id' => $zenaProject->pm_id,
                    'start_date' => $zenaProject->start_date,
                    'end_date' => $zenaProject->end_date,
                    'status' => $this->mapZenaStatusToProjectStatus($zenaProject->status),
                    'progress' => $zenaProject->progress ?? 0,
                    'budget_planned' => $zenaProject->budget ?? 0,
                    'budget_actual' => $zenaProject->actual_cost ?? 0,
                    'priority' => 'medium',
                    'tags' => null,
                    'settings' => null,
                    'created_at' => $zenaProject->created_at,
                    'updated_at' => $zenaProject->updated_at,
                ]);
            } else {
                // Update existing project với data từ zena_project
                DB::table('projects')->where('id', $zenaProject->id)->update([
                    'budget_planned' => $zenaProject->budget ?? $existingProject->budget_total ?? 0,
                    'budget_actual' => $zenaProject->actual_cost ?? 0,
                    'priority' => 'medium',
                    'settings' => json_encode([
                        'client_name' => $zenaProject->client_name ?? null,
                        'location' => $zenaProject->location ?? null,
                    ])
                ]);
            }
        }

        // Migrate project users từ zena_project_users sang project_team_members
        if (Schema::hasTable('zena_project_users')) {
            $zenaProjectUsers = DB::table('zena_project_users')->get();
            
            foreach ($zenaProjectUsers as $zenaProjectUser) {
                // Kiểm tra xem đã tồn tại chưa
                $existingMember = DB::table('project_team_members')
                    ->where('project_id', $zenaProjectUser->project_id)
                    ->where('user_id', $zenaProjectUser->user_id)
                    ->first();
                
                if (!$existingMember) {
                    DB::table('project_team_members')->insert([
                        'project_id' => $zenaProjectUser->project_id,
                        'user_id' => $zenaProjectUser->user_id,
                        'role' => $this->mapZenaRoleToProjectRole($zenaProjectUser->role_on_project ?? 'member'),
                        'joined_at' => $zenaProjectUser->created_at,
                        'created_at' => $zenaProjectUser->created_at,
                        'updated_at' => $zenaProjectUser->updated_at,
                    ]);
                }
            }
        }
    }

    /**
     * Map ZenaProject status sang Project status
     */
    private function mapZenaStatusToProjectStatus(?string $zenaStatus): string
    {
        return match($zenaStatus) {
            'planning' => 'planning',
            'active' => 'active',
            'completed' => 'completed',
            'cancelled' => 'cancelled',
            default => 'draft'
        };
    }

    /**
     * Map ZenaProject role sang Project role
     */
    private function mapZenaRoleToProjectRole(?string $zenaRole): string
    {
        return match($zenaRole) {
            'project_manager' => 'project_manager',
            'admin' => 'admin',
            'member' => 'member',
            default => 'member'
        };
    }

    /**
     * Generate project code
     */
    private function generateProjectCode(): string
    {
        $year = date('Y');
        $count = DB::table('projects')->whereYear('created_at', $year)->count() + 1;
        return "PRJ-{$year}-" . str_pad($count, 4, '0', STR_PAD_LEFT);
    }
};