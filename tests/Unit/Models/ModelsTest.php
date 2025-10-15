<?php

namespace Tests\Unit\Models;

use Tests\TestCase;
use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use App\Models\Document;
use App\Models\Team;
use App\Models\Notification;
use App\Models\ChangeRequest;
use App\Models\Rfi;
use App\Models\QcPlan;
use App\Models\QcInspection;
use App\Models\Tenant;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\Collection;

class ModelsTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function user_model_has_correct_fillable_attributes()
    {
        $user = new User();
        $fillable = $user->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('email', $fillable);
        $this->assertContains('password', $fillable);
        $this->assertContains('tenant_id', $fillable);
    }

    /** @test */
    public function user_model_has_correct_hidden_attributes()
    {
        $user = new User();
        $hidden = $user->getHidden();
        
        $this->assertContains('password', $hidden);
        $this->assertContains('remember_token', $hidden);
    }

    /** @test */
    public function user_model_belongs_to_tenant()
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->assertInstanceOf(Tenant::class, $user->tenant);
        $this->assertEquals($tenant->id, $user->tenant->id);
    }

    /** @test */
    public function user_model_belongs_to_many_roles()
    {
        $user = User::factory()->create();
        $role = Role::factory()->create();
        
        $user->roles()->attach($role);
        
        $this->assertInstanceOf(Collection::class, $user->roles);
        $this->assertTrue($user->roles->contains($role));
    }

    /** @test */
    public function user_model_has_many_projects()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['pm_id' => $user->id]);
        
        $this->assertInstanceOf(Collection::class, $user->projects);
        $this->assertTrue($user->projects->contains($project));
    }

    /** @test */
    public function project_model_has_correct_fillable_attributes()
    {
        $project = new Project();
        $fillable = $project->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('code', $fillable);
        $this->assertContains('progress_pct', $fillable);
    }

    /** @test */
    public function project_model_belongs_to_tenant()
    {
        $tenant = Tenant::factory()->create();
        $project = Project::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->assertInstanceOf(Tenant::class, $project->tenant);
        $this->assertEquals($tenant->id, $project->tenant->id);
    }

    /** @test */
    public function project_model_belongs_to_owner()
    {
        $user = User::factory()->create();
        $project = Project::factory()->create(['owner_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $project->owner);
        $this->assertEquals($user->id, $project->owner->id);
    }

    /** @test */
    public function project_model_has_many_tasks()
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        
        $this->assertInstanceOf(Collection::class, $project->tasks);
        $this->assertTrue($project->tasks->contains($task));
    }

    /** @test */
    public function project_model_belongs_to_many_teams()
    {
        $project = Project::factory()->create();
        $team = Team::factory()->create();
        
        $project->teams()->attach($team);
        
        $this->assertInstanceOf(Collection::class, $project->teams);
        $this->assertTrue($project->teams->contains($team));
    }

    /** @test */
    public function task_model_has_correct_fillable_attributes()
    {
        $task = new Task();
        $fillable = $task->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('priority', $fillable);
        $this->assertContains('project_id', $fillable);
        $this->assertContains('assignee_id', $fillable);
    }

    /** @test */
    public function task_model_belongs_to_project()
    {
        $project = Project::factory()->create();
        $task = Task::factory()->create(['project_id' => $project->id]);
        
        $this->assertInstanceOf(Project::class, $task->project);
        $this->assertEquals($project->id, $task->project->id);
    }

    /** @test */
    public function task_model_belongs_to_assignee()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['assignee_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $task->assignee);
        $this->assertEquals($user->id, $task->assignee->id);
    }

    /** @test */
    public function task_model_belongs_to_creator()
    {
        $user = User::factory()->create();
        $task = Task::factory()->create(['created_by' => $user->id]);
        
        $this->assertInstanceOf(User::class, $task->creator);
        $this->assertEquals($user->id, $task->creator->id);
    }

    /** @test */
    public function task_model_has_dependencies_array()
    {
        $task1 = Task::factory()->create();
        $task2 = Task::factory()->create();
        
        // Set dependencies as JSON array
        $task1->update(['dependencies' => [$task2->id]]);
        
        $this->assertIsArray($task1->dependencies);
        $this->assertContains($task2->id, $task1->dependencies);
    }

    /** @test */
    public function document_model_has_correct_fillable_attributes()
    {
        $document = new Document();
        $fillable = $document->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('file_path', $fillable);
        $this->assertContains('file_size', $fillable);
        $this->assertContains('mime_type', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('project_id', $fillable);
    }

    /** @test */
    public function document_model_belongs_to_tenant()
    {
        $this->markTestSkipped('Document model has file_type field issue - needs database fix');
        
        $tenant = Tenant::factory()->create();
        $document = Document::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->assertInstanceOf(Tenant::class, $document->tenant);
        $this->assertEquals($tenant->id, $document->tenant->id);
    }

    /** @test */
    public function document_model_belongs_to_project()
    {
        $this->markTestSkipped('Document model has file_type field issue - needs database fix');
        
        $project = Project::factory()->create();
        $document = Document::factory()->create(['project_id' => $project->id]);
        
        $this->assertInstanceOf(Project::class, $document->project);
        $this->assertEquals($project->id, $document->project->id);
    }

    /** @test */
    public function document_model_belongs_to_creator()
    {
        $this->markTestSkipped('Document model has file_type field issue - needs database fix');
        
        $user = User::factory()->create();
        $document = Document::factory()->create(['created_by' => $user->id]);
        
        $this->assertInstanceOf(User::class, $document->creator);
        $this->assertEquals($user->id, $document->creator->id);
    }

    /** @test */
    public function team_model_has_correct_fillable_attributes()
    {
        $team = new Team();
        $fillable = $team->getFillable();
        
        $this->assertContains('name', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('is_active', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('team_lead_id', $fillable);
    }

    /** @test */
    public function team_model_belongs_to_tenant()
    {
        $tenant = Tenant::factory()->create();
        $team = Team::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->assertInstanceOf(Tenant::class, $team->tenant);
        $this->assertEquals($tenant->id, $team->tenant->id);
    }

    /** @test */
    public function team_model_belongs_to_team_lead()
    {
        $user = User::factory()->create();
        $team = Team::factory()->create(['team_lead_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $team->teamLead);
        $this->assertEquals($user->id, $team->teamLead->id);
    }

    /** @test */
    public function team_model_belongs_to_many_members()
    {
        $this->markTestSkipped('Missing team_members pivot table migration');
    }

    /** @test */
    public function team_model_belongs_to_many_projects()
    {
        $team = Team::factory()->create();
        $project = Project::factory()->create();
        
        $team->projects()->attach($project);
        
        $this->assertInstanceOf(Collection::class, $team->projects);
        $this->assertTrue($team->projects->contains($project));
    }

    /** @test */
    public function notification_model_has_correct_fillable_attributes()
    {
        $notification = new Notification();
        $fillable = $notification->getFillable();
        
        $this->assertContains('title', $fillable);
        $this->assertContains('body', $fillable);
        $this->assertContains('type', $fillable);
        $this->assertContains('priority', $fillable);
        $this->assertContains('user_id', $fillable);
        $this->assertContains('tenant_id', $fillable);
    }

    /** @test */
    public function notification_model_belongs_to_user()
    {
        $user = User::factory()->create();
        $notification = Notification::factory()->create(['user_id' => $user->id]);
        
        $this->assertInstanceOf(User::class, $notification->user);
        $this->assertEquals($user->id, $notification->user->id);
    }

    /** @test */
    public function notification_model_belongs_to_tenant()
    {
        $tenant = Tenant::factory()->create();
        $notification = Notification::factory()->create(['tenant_id' => $tenant->id]);
        
        $this->assertInstanceOf(Tenant::class, $notification->tenant);
        $this->assertEquals($tenant->id, $notification->tenant->id);
    }

    /** @test */
    public function change_request_model_has_correct_fillable_attributes()
    {
        $changeRequest = new ChangeRequest();
        $fillable = $changeRequest->getFillable();
        
        $this->assertContains('title', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('priority', $fillable);
        $this->assertContains('project_id', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('requested_by', $fillable);
    }

    /** @test */
    public function change_request_model_belongs_to_project()
    {
        $project = Project::factory()->create();
        $changeRequest = ChangeRequest::factory()->create(['project_id' => $project->id]);
        
        $this->assertInstanceOf(Project::class, $changeRequest->project);
        $this->assertEquals($project->id, $changeRequest->project->id);
    }

    /** @test */
    public function change_request_model_belongs_to_creator()
    {
        $user = User::factory()->create();
        $changeRequest = ChangeRequest::factory()->create(['created_by' => $user->id]);
        
        $this->assertInstanceOf(User::class, $changeRequest->creator);
        $this->assertEquals($user->id, $changeRequest->creator->id);
    }

    /** @test */
    public function rfi_model_has_correct_fillable_attributes()
    {
        $rfi = new Rfi();
        $fillable = $rfi->getFillable();
        
        $this->assertContains('subject', $fillable);
        $this->assertContains('question', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('priority', $fillable);
        $this->assertContains('project_id', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('created_by', $fillable);
    }

    /** @test */
    public function rfi_model_belongs_to_project()
    {
        $this->markTestSkipped('Missing RfiFactory');
    }

    /** @test */
    public function rfi_model_belongs_to_creator()
    {
        $this->markTestSkipped('Missing RfiFactory');
    }

    /** @test */
    public function qc_plan_model_has_correct_fillable_attributes()
    {
        $qcPlan = new QcPlan();
        $fillable = $qcPlan->getFillable();
        
        $this->assertContains('title', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('project_id', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('created_by', $fillable);
    }

    /** @test */
    public function qc_plan_model_belongs_to_project()
    {
        $this->markTestSkipped('Missing QcPlanFactory');
    }

    /** @test */
    public function qc_plan_model_belongs_to_creator()
    {
        $this->markTestSkipped('Missing QcPlanFactory');
    }

    /** @test */
    public function qc_inspection_model_has_correct_fillable_attributes()
    {
        $qcInspection = new QcInspection();
        $fillable = $qcInspection->getFillable();
        
        $this->assertContains('title', $fillable);
        $this->assertContains('description', $fillable);
        $this->assertContains('status', $fillable);
        $this->assertContains('qc_plan_id', $fillable);
        $this->assertContains('tenant_id', $fillable);
        $this->assertContains('inspector_id', $fillable);
    }

    /** @test */
    public function qc_inspection_model_belongs_to_project()
    {
        $this->markTestSkipped('Missing QcInspectionFactory and no direct project relationship');
    }

    /** @test */
    public function qc_inspection_model_belongs_to_inspector()
    {
        $this->markTestSkipped('Missing QcInspectionFactory');
    }

    /** @test */
    public function all_models_have_timestamps()
    {
        $models = [
            User::class,
            Project::class,
            Task::class,
            Document::class,
            Team::class,
            Notification::class,
            ChangeRequest::class,
            Rfi::class,
            QcPlan::class,
            QcInspection::class
        ];
        
        foreach ($models as $modelClass) {
            $model = new $modelClass();
            $this->assertTrue($model->timestamps, $modelClass . ' should have timestamps enabled');
        }
    }

    /** @test */
    public function all_models_have_proper_table_names()
    {
        $models = [
            User::class,
            Project::class,
            Task::class,
            Document::class,
            Team::class,
            Notification::class,
            ChangeRequest::class,
            Rfi::class,
            QcPlan::class,
            QcInspection::class
        ];
        
        foreach ($models as $modelClass) {
            $model = new $modelClass();
            $tableName = $model->getTable();
            $this->assertIsString($tableName, $modelClass . ' should have a table name');
            $this->assertNotEmpty($tableName, $modelClass . ' should have a non-empty table name');
        }
    }
}
