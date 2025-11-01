<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\CsvExportService;
use App\Services\CsvImportService;
use App\Models\User;
use App\Models\Project;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CsvServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CsvExportService $exportService;
    protected CsvImportService $importService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->exportService = new CsvExportService();
        $this->importService = new CsvImportService();
    }

    /**
     * Test CSV export for users
     */
    public function test_export_users_to_csv(): void
    {
        // Create test users
        $user1 = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $user2 = User::factory()->create(['name' => 'Jane Smith', 'email' => 'jane@example.com']);

        $csvContent = $this->exportService->exportUsers();

        $this->assertIsString($csvContent);
        $this->assertStringContainsString('John Doe', $csvContent);
        $this->assertStringContainsString('Jane Smith', $csvContent);
        $this->assertStringContainsString('john@example.com', $csvContent);
        $this->assertStringContainsString('jane@example.com', $csvContent);
    }

    /**
     * Test CSV export for projects
     */
    public function test_export_projects_to_csv(): void
    {
        // Create test projects
        $project1 = Project::factory()->create(['name' => 'Project Alpha', 'status' => 'active']);
        $project2 = Project::factory()->create(['name' => 'Project Beta', 'status' => 'planning']);

        $csvContent = $this->exportService->exportProjects();

        $this->assertIsString($csvContent);
        $this->assertStringContainsString('Project Alpha', $csvContent);
        $this->assertStringContainsString('Project Beta', $csvContent);
        $this->assertStringContainsString('active', $csvContent);
        $this->assertStringContainsString('planning', $csvContent);
    }

    /**
     * Test CSV export with filters
     */
    public function test_export_users_with_filters(): void
    {
        // Create test users with different statuses
        $activeUser = User::factory()->create(['status' => 'active']);
        $inactiveUser = User::factory()->create(['status' => 'inactive']);

        $csvContent = $this->exportService->exportUsers(['status' => 'active']);

        $this->assertStringContainsString($activeUser->name, $csvContent);
        $this->assertStringNotContainsString($inactiveUser->name, $csvContent);
    }

    /**
     * Test CSV import for users
     */
    public function test_import_users_from_csv(): void
    {
        $csvContent = "name,email,phone,department,status,password\n" .
                     "John Doe,john@example.com,+1234567890,Engineering,active,password123\n" .
                     "Jane Smith,jane@example.com,+1234567891,Marketing,active,password123";

        $file = $this->createCsvFile($csvContent);
        
        $options = [
            'tenant_id' => 'test-tenant',
            'update_existing' => false
        ];

        $result = $this->importService->importUsers($file, $options);

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['success']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);

        // Verify users were created
        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ]);
        $this->assertDatabaseHas('users', [
            'name' => 'Jane Smith',
            'email' => 'jane@example.com'
        ]);
    }

    /**
     * Test CSV import for projects
     */
    public function test_import_projects_from_csv(): void
    {
        $csvContent = "name,description,status,priority,budget,start_date,end_date\n" .
                     "Project Alpha,Description of Project Alpha,planning,high,100000,2025-01-01,2025-12-31\n" .
                     "Project Beta,Description of Project Beta,active,medium,50000,2025-02-01,2025-11-30";

        $file = $this->createCsvFile($csvContent);
        
        $options = [
            'tenant_id' => 'test-tenant',
            'update_existing' => false
        ];

        $result = $this->importService->importProjects($file, $options);

        // Debug: Check what happened
        if ($result['success'] === 0 && !empty($result['errors'])) {
            $this->fail('Project import failed: ' . json_encode($result['errors']));
        }

        $this->assertEquals(2, $result['total']);
        $this->assertEquals(2, $result['success']);
        $this->assertEquals(0, $result['failed']);
        $this->assertEmpty($result['errors']);

        // Verify projects were created
        $this->assertDatabaseHas('projects', [
            'name' => 'Project Alpha',
            'status' => 'planning'
        ]);
        $this->assertDatabaseHas('projects', [
            'name' => 'Project Beta',
            'status' => 'active'
        ]);
    }

    /**
     * Test CSV validation
     */
    public function test_validate_csv_file(): void
    {
        $csvContent = "name,email,phone,department,status,password\n" .
                     "John Doe,john@example.com,+1234567890,Engineering,active,password123";

        $file = $this->createCsvFile($csvContent);
        
        $validation = $this->importService->validateFile($file, 'users');

        $this->assertTrue($validation['valid']);
        $this->assertEmpty($validation['errors']);
        $this->assertEquals(1, $validation['row_count']);
    }

    /**
     * Test CSV validation with errors
     */
    public function test_validate_csv_file_with_errors(): void
    {
        $csvContent = "name,email,phone,department,status,password\n" .
                     "John Doe,invalid-email,+1234567890,Engineering,active,password123";

        $file = $this->createCsvFile($csvContent);
        
        $validation = $this->importService->validateFile($file, 'users');

        $this->assertFalse($validation['valid']);
        $this->assertNotEmpty($validation['errors']);
    }

    /**
     * Test import template generation
     */
    public function test_get_import_template(): void
    {
        $template = $this->importService->getTemplate('users');

        $this->assertIsString($template);
        $this->assertStringContainsString('name,email,phone,department,status,password', $template);
        $this->assertStringContainsString('John Doe', $template);
        $this->assertStringContainsString('jane@example.com', $template);
    }

    /**
     * Test import with update existing
     */
    public function test_import_users_update_existing(): void
    {
        // Create existing user
        $existingUser = User::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1111111111'
        ]);

        $csvContent = "name,email,phone,department,status,password\n" .
                     "John Doe,john@example.com,+1234567890,Engineering,active,password123";

        $file = $this->createCsvFile($csvContent);
        
        $options = [
            'tenant_id' => 'test-tenant',
            'update_existing' => true
        ];

        $result = $this->importService->importUsers($file, $options);

        $this->assertEquals(1, $result['total']);
        $this->assertEquals(1, $result['success']);
        $this->assertEquals(0, $result['failed']);

        // Verify user was updated
        $existingUser->refresh();
        $this->assertEquals('+1234567890', $existingUser->phone);
        $this->assertEquals('Engineering', $existingUser->department);
    }

    /**
     * Test import with missing required fields
     */
    public function test_import_users_missing_required_fields(): void
    {
        $csvContent = "name,email,phone,department,status,password\n" .
                     ",john@example.com,+1234567890,Engineering,active,password123";

        $file = $this->createCsvFile($csvContent);
        
        $options = [
            'tenant_id' => 'test-tenant',
            'update_existing' => false
        ];

        // This should throw an exception during validation
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Required field 'name' is missing");
        
        $this->importService->importUsers($file, $options);
    }

    /**
     * Test export statistics
     */
    public function test_get_export_stats(): void
    {
        $stats = $this->exportService->getExportStats();

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('users', $stats);
        $this->assertArrayHasKey('projects', $stats);
        $this->assertArrayHasKey('export_formats', $stats);
        $this->assertArrayHasKey('max_rows', $stats);
        $this->assertIsArray($stats['export_formats']);
        $this->assertContains('csv', $stats['export_formats']);
    }

    /**
     * Create CSV file for testing
     */
    private function createCsvFile(string $content): UploadedFile
    {
        Storage::fake('local');
        
        $filename = 'test.csv';
        Storage::put($filename, $content);
        
        $filePath = Storage::path($filename);
        
        return new UploadedFile(
            $filePath,
            $filename,
            'text/csv',
            null,
            true
        );
    }
}
