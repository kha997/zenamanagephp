<?php declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Tenant;
use App\Models\Project;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\TaskAttachment;
use App\Models\TaskAttachmentVersion;
use Illuminate\Support\Str;

/**
 * Phase 3 Test Data Seeder
 * 
 * Creates comprehensive test data for Phase 3 E2E tests:
 * - APP-FE-301: Frontend comment UI integration
 * - APP-FE-302: Kanban React board with ULID schema
 * - APP-BE-401: File attachments system
 * - Real-time updates testing
 */
class Phase3TestDataSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding Phase 3 test data...');

        // Create test tenant
        $tenant = $this->createTestTenant();

        // Create test users
        $users = $this->createTestUsers($tenant);

        // Create test projects
        $projects = $this->createTestProjects($tenant, $users);

        // Create test tasks with various statuses
        $tasks = $this->createTestTasks($tenant, $users, $projects);

        // Create test comments
        $this->createTestComments($tenant, $users, $tasks);

        // Create test attachments
        $this->createTestAttachments($tenant, $users, $tasks);

        $this->command->info('Phase 3 test data seeded successfully!');
    }

    /**
     * Create test tenant
     */
    private function createTestTenant(): Tenant
    {
        // Use existing Phase 3 Test Company tenant
        return Tenant::firstOrCreate(
            ['id' => '01K83FPK5XGPXF3V7ANJQRGX5X'],
            [
                'name' => 'Phase 3 Test Company',
                'domain' => 'phase3-test.local',
                'is_active' => true,
                'settings' => [
                    'timezone' => 'UTC',
                    'date_format' => 'Y-m-d',
                    'time_format' => 'H:i',
                    'currency' => 'USD',
                ],
            ]
        );
    }

    /**
     * Create test users
     */
    private function createTestUsers(Tenant $tenant): array
    {
        $users = [];

        // Project Manager - use existing test user
        $users['pm'] = User::firstOrCreate(
            ['email' => 'uat-pm@test.com'],
            [
                'id' => '01k7ygpf7bjk2nx7v8vb3h4b82',
                'tenant_id' => $tenant->id,
                'name' => 'UAT Project Manager',
                'password' => bcrypt('password'),
                'role' => 'PM',
                'is_active' => true,
            ]
        );

        // Developer
        $users['dev'] = User::firstOrCreate(
            ['email' => 'dev@phase3-test.local'],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Phase 3 Developer',
                'password' => bcrypt('password'),
                'role' => 'developer',
                'is_active' => true,
            ]
        );

        // Designer
        $users['designer'] = User::firstOrCreate(
            ['email' => 'designer@phase3-test.local'],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Phase 3 Designer',
                'password' => bcrypt('password'),
                'role' => 'designer',
                'is_active' => true,
            ]
        );

        // Client
        $users['client'] = User::firstOrCreate(
            ['email' => 'client@phase3-test.local'],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Phase 3 Client',
                'password' => bcrypt('password'),
                'role' => 'client',
                'is_active' => true,
            ]
        );

        return $users;
    }

    /**
     * Create test projects
     */
    private function createTestProjects(Tenant $tenant, array $users): array
    {
        $projects = [];

        // Main Phase 3 Test Project
        $projects['main'] = Project::firstOrCreate(
            ['code' => 'P3-INT'],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Phase 3 Integration Project',
                'description' => 'Comprehensive test project for Phase 3 features including comments, attachments, and real-time updates.',
                'status' => 'active',
                'pm_id' => $users['pm']->id,
                'client_id' => $users['client']->id,
                'start_date' => now()->subDays(30),
                'end_date' => now()->addDays(60),
                'budget_total' => 50000.00,
                'budget_planned' => 45000.00,
                'budget_actual' => 20000.00,
                'progress_pct' => 45,
            ]
        );

        // Kanban Test Project
        $projects['kanban'] = Project::firstOrCreate(
            ['code' => 'P3-KAN'],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Kanban Board Test Project',
                'description' => 'Project specifically for testing Kanban board functionality with various task statuses.',
                'status' => 'active',
                'pm_id' => $users['pm']->id,
                'client_id' => $users['client']->id,
                'start_date' => now()->subDays(15),
                'end_date' => now()->addDays(45),
                'budget_total' => 25000.00,
                'budget_planned' => 20000.00,
                'budget_actual' => 5000.00,
                'progress_pct' => 30,
            ]
        );

        // Real-time Test Project
        $projects['realtime'] = Project::firstOrCreate(
            ['code' => 'P3-RT'],
            [
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'name' => 'Real-time Updates Test Project',
                'description' => 'Project for testing real-time collaboration features.',
                'status' => 'active',
                'pm_id' => $users['pm']->id,
                'client_id' => $users['client']->id,
                'start_date' => now()->subDays(7),
                'end_date' => now()->addDays(30),
                'budget_total' => 15000.00,
                'budget_planned' => 12000.00,
                'budget_actual' => 2000.00,
                'progress_pct' => 20,
            ]
        );

        return $projects;
    }

    /**
     * Create test tasks with various statuses
     */
    private function createTestTasks(Tenant $tenant, array $users, array $projects): array
    {
        $tasks = [];

        // Tasks for main project
        $taskStatuses = ['backlog', 'todo', 'in_progress', 'blocked', 'done'];
        $priorities = ['low', 'normal', 'high', 'urgent'];

        foreach ($projects as $projectKey => $project) {
            foreach ($taskStatuses as $index => $status) {
                $priority = $priorities[$index % count($priorities)];
                
                $tasks[$projectKey . '_' . $status] = Task::create([
                    'id' => Str::ulid(),
                    'tenant_id' => $tenant->id,
                    'project_id' => $project->id,
                    'name' => ucfirst($status) . ' Task for ' . $project->name,
                    'description' => 'Test task for Phase 3 E2E testing. Status: ' . $status . ', Priority: ' . $priority,
                    'status' => $status,
                    'priority' => $priority,
                    'assignee_id' => $users['dev']->id,
                    'created_by' => $users['pm']->id,
                    'start_date' => now()->subDays(rand(1, 10)),
                    'end_date' => now()->addDays(rand(1, 30)),
                    'estimated_hours' => rand(2, 16),
                    'actual_hours' => $status === 'done' ? rand(2, 16) : null,
                    'progress_percent' => $this->getProgressForStatus($status),
                ]);
            }
        }

        // Additional tasks for comprehensive testing
        $tasks['comment_test'] = Task::create([
            'id' => Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $projects['main']->id,
            'name' => 'Comment System Test Task',
            'description' => 'Task specifically for testing comment functionality including replies, edits, and deletion.',
            'status' => 'in_progress',
            'priority' => 'high',
            'assignee_id' => $users['dev']->id,
            'created_by' => $users['pm']->id,
            'start_date' => now()->subDays(5),
            'end_date' => now()->addDays(10),
            'estimated_hours' => 8,
            'actual_hours' => 3,
            'progress_percent' => 40,
        ]);

        $tasks['attachment_test'] = Task::create([
            'id' => Str::ulid(),
            'tenant_id' => $tenant->id,
            'project_id' => $projects['main']->id,
            'name' => 'Attachment System Test Task',
            'description' => 'Task for testing file attachment functionality including upload, download, and categorization.',
            'status' => 'todo',
            'priority' => 'normal',
            'assignee_id' => $users['designer']->id,
            'created_by' => $users['pm']->id,
            'start_date' => now(),
            'end_date' => now()->addDays(7),
            'estimated_hours' => 4,
            'progress_percent' => 0,
        ]);

        return $tasks;
    }

    /**
     * Create test comments
     */
    private function createTestComments(Tenant $tenant, array $users, array $tasks): void
    {
        $commentTypes = ['general', 'question', 'suggestion', 'issue'];
        $commentContents = [
            'This task looks good, let\'s proceed with implementation.',
            'I have a question about the requirements. Can we clarify?',
            'I suggest we add more validation to this feature.',
            'There seems to be an issue with the current approach.',
            'Great work on this! Looking forward to the next phase.',
            'Can we schedule a meeting to discuss this further?',
            'The design looks perfect, no changes needed.',
            'I found a bug in the implementation, will fix it soon.',
        ];

        foreach ($tasks as $task) {
            // Create 3-5 comments per task
            $commentCount = rand(3, 5);
            
            for ($i = 0; $i < $commentCount; $i++) {
                $user = $users[array_rand($users)];
                $content = $commentContents[array_rand($commentContents)];
                $type = $commentTypes[array_rand($commentTypes)];
                
                TaskComment::create([
                    'id' => Str::ulid(),
                    'tenant_id' => $tenant->id,
                    'task_id' => $task->id,
                    'user_id' => $user->id,
                    'content' => $content,
                    'type' => $type,
                    'is_internal' => rand(0, 1) === 1,
                    'created_at' => now()->subDays(rand(1, 10)),
                ]);
            }
        }

        // Create some replies for comment testing
        $mainTask = $tasks['comment_test'];
        $parentComment = TaskComment::where('task_id', $mainTask->id)->first();
        
        if ($parentComment) {
            TaskComment::create([
                'id' => Str::ulid(),
                'tenant_id' => $tenant->id,
                'task_id' => $mainTask->id,
                'user_id' => $users['dev']->id,
                'content' => 'Thanks for the feedback! I\'ll implement the changes.',
                'type' => 'general',
                'is_internal' => false,
                'parent_id' => $parentComment->id,
                'created_at' => now()->subDays(2),
            ]);
        }
    }

    /**
     * Create test attachments
     */
    private function createTestAttachments(Tenant $tenant, array $users, array $tasks): void
    {
        $attachmentTypes = ['document', 'image', 'video', 'code'];
        $categories = ['design', 'report', 'code', 'other'];
        $fileNames = [
            'project-specification.pdf',
            'wireframe-design.png',
            'user-manual.docx',
            'test-results.xlsx',
            'code-review.md',
            'meeting-notes.txt',
            'architecture-diagram.svg',
            'bug-report.pdf',
        ];

        foreach ($tasks as $task) {
            // Create 2-4 attachments per task
            $attachmentCount = rand(2, 4);
            
            for ($i = 0; $i < $attachmentCount; $i++) {
                $user = $users[array_rand($users)];
                $fileName = $fileNames[array_rand($fileNames)];
                $type = $attachmentTypes[array_rand($attachmentTypes)];
                $category = $categories[array_rand($categories)];
                
                $attachment = TaskAttachment::create([
                    'id' => Str::ulid(),
                    'tenant_id' => $tenant->id,
                    'task_id' => $task->id,
                    'uploaded_by' => $user->id,
                    'name' => 'Test ' . ucfirst($category) . ' File',
                    'original_name' => $fileName,
                    'file_path' => 'test-attachments/' . $fileName,
                    'disk' => 'public',
                    'mime_type' => $this->getMimeType($fileName),
                    'extension' => pathinfo($fileName, PATHINFO_EXTENSION),
                    'size' => rand(1024, 10485760), // 1KB to 10MB
                    'hash' => Str::random(64),
                    'category' => $category,
                    'metadata' => [
                        'uploaded_by' => $user->name,
                        'upload_date' => now()->toISOString(),
                        'file_type' => $type,
                    ],
                    'tags' => [$category, 'test', 'phase3'],
                    'is_public' => rand(0, 1) === 1,
                    'is_active' => true,
                    'download_count' => rand(0, 10),
                    'created_at' => now()->subDays(rand(1, 7)),
                ]);

                // Create a version for some attachments
                // Note: Commented out for testing - task_attachment_versions table doesn't exist
                /*
                if (rand(0, 1) === 1) {
                    TaskAttachmentVersion::create([
                        'id' => Str::ulid(),
                        'task_attachment_id' => $attachment->id,
                        'user_id' => $user->id,
                        'version_number' => 2,
                        'path' => 'test-attachments/v2/' . $fileName,
                        'disk' => 'public',
                        'size' => rand(1024, 10485760),
                        'hash' => Str::random(64),
                        'change_description' => 'Updated version with improvements',
                        'metadata' => [
                            'version_notes' => 'Second version with bug fixes',
                        ],
                        'is_current' => true,
                        'created_at' => now()->subDays(rand(1, 3)),
                    ]);
                }
                */
            }
        }
    }

    /**
     * Get progress percentage based on status
     */
    private function getProgressForStatus(string $status): int
    {
        return match ($status) {
            'backlog' => 0,
            'todo' => 10,
            'in_progress' => 50,
            'blocked' => 30,
            'done' => 100,
            default => 0,
        };
    }

    /**
     * Get MIME type based on file extension
     */
    private function getMimeType(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        return match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'md' => 'text/markdown',
            'txt' => 'text/plain',
            'svg' => 'image/svg+xml',
            default => 'application/octet-stream',
        };
    }
}
