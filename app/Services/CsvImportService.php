<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\Project;
use App\Models\Task;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class CsvImportService
{
    private int $maxRows;
    private array $allowedMimeTypes;

    public function __construct()
    {
        $this->maxRows = config('csv.max_rows', 10000);
        $this->allowedMimeTypes = ['text/csv', 'text/plain', 'application/csv'];
    }

    /**
     * Import users from CSV file
     */
    public function importUsers(UploadedFile $file, array $options = []): array
    {
        try {
            $data = $this->parseCsvFile($file);
            $this->validateUserData($data);
            
            $results = [
                'total' => count($data),
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'imported_users' => []
            ];

            DB::beginTransaction();

            try {
                foreach ($data as $index => $row) {
                    try {
                        $user = $this->createOrUpdateUser($row, $options);
                        $results['success']++;
                        $results['imported_users'][] = [
                            'id' => $user->id,
                            'name' => $user->name,
                            'email' => $user->email
                        ];
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $index + 2, // +2 because CSV has header and array is 0-indexed
                            'error' => $e->getMessage(),
                            'data' => $row
                        ];
                    }
                }

                DB::commit();

                Log::info('User CSV import completed', [
                    'total' => $results['total'],
                    'success' => $results['success'],
                    'failed' => $results['failed'],
                    'user_id' => auth()->id()
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('User CSV import failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Import projects from CSV file
     */
    public function importProjects(UploadedFile $file, array $options = []): array
    {
        try {
            $data = $this->parseCsvFile($file);
            $this->validateProjectData($data);
            
            $results = [
                'total' => count($data),
                'success' => 0,
                'failed' => 0,
                'errors' => [],
                'imported_projects' => []
            ];

            DB::beginTransaction();

            try {
                foreach ($data as $index => $row) {
                    try {
                        $project = $this->createOrUpdateProject($row, $options);
                        $results['success']++;
                        $results['imported_projects'][] = [
                            'id' => $project->id,
                            'name' => $project->name,
                            'status' => $project->status
                        ];
                    } catch (\Exception $e) {
                        $results['failed']++;
                        $results['errors'][] = [
                            'row' => $index + 2,
                            'error' => $e->getMessage(),
                            'data' => $row
                        ];
                    }
                }

                DB::commit();

                Log::info('Project CSV import completed', [
                    'total' => $results['total'],
                    'success' => $results['success'],
                    'failed' => $results['failed'],
                    'user_id' => auth()->id()
                ]);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }

            return $results;

        } catch (\Exception $e) {
            Log::error('Project CSV import failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName()
            ]);
            throw $e;
        }
    }

    /**
     * Parse CSV file
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        if (!in_array($file->getMimeType(), $this->allowedMimeTypes)) {
            throw new \Exception('Invalid file type. Only CSV files are allowed.');
        }

        $content = file_get_contents($file->getPathname());
        $lines = str_getcsv($content, "\n");
        
        if (count($lines) < 2) {
            throw new \Exception('CSV file must contain at least a header row and one data row.');
        }

        if (count($lines) > $this->maxRows + 1) {
            throw new \Exception("Too many rows. Maximum allowed: {$this->maxRows}");
        }

        // Parse header
        $headers = str_getcsv($lines[0]);
        
        // Parse data rows
        $data = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            }
        }

        return $data;
    }

    /**
     * Validate user data
     */
    private function validateUserData(array $data): void
    {
        $requiredFields = ['name', 'email'];
        
        foreach ($data as $index => $row) {
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    throw new \Exception("Row " . ($index + 2) . ": Required field '{$field}' is missing.");
                }
            }

            // Validate email format
            if (!filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                throw new \Exception("Row " . ($index + 2) . ": Invalid email format.");
            }
        }
    }

    /**
     * Validate project data
     */
    private function validateProjectData(array $data): void
    {
        $requiredFields = ['name'];
        
        foreach ($data as $index => $row) {
            foreach ($requiredFields as $field) {
                if (empty($row[$field])) {
                    throw new \Exception("Row " . ($index + 2) . ": Required field '{$field}' is missing.");
                }
            }
        }
    }

    /**
     * Create or update user
     */
    private function createOrUpdateUser(array $row, array $options): User
    {
        $updateExisting = $options['update_existing'] ?? false;
        
        if ($updateExisting) {
            $user = User::where('email', $row['email'])->first();
        } else {
            $user = null;
        }

        if ($user) {
            // Update existing user
            $user->update([
                'name' => $row['name'],
                'phone' => $row['phone'] ?? $user->phone,
                'department' => $row['department'] ?? $user->department,
                'status' => $row['status'] ?? $user->status,
            ]);
        } else {
            // Create new user
            $user = User::create([
                'name' => $row['name'],
                'email' => $row['email'],
                'password' => Hash::make($row['password'] ?? 'password123'),
                'phone' => $row['phone'] ?? null,
                'department' => $row['department'] ?? null,
                'status' => $row['status'] ?? 'active',
                'tenant_id' => $options['tenant_id'] ?? auth()->user()->tenant_id,
                'email_verified_at' => now(),
            ]);
        }

        return $user;
    }

    /**
     * Create or update project
     */
    private function createOrUpdateProject(array $row, array $options): Project
    {
        $updateExisting = $options['update_existing'] ?? false;
        
        if ($updateExisting) {
            $project = Project::where('name', $row['name'])->first();
        } else {
            $project = null;
        }

        if ($project) {
            // Update existing project
            $project->update([
                'description' => $row['description'] ?? $project->description,
                'status' => $row['status'] ?? $project->status,
                'priority' => $row['priority'] ?? $project->priority,
                'budget' => $row['budget'] ?? $project->budget,
                'start_date' => $row['start_date'] ?? $project->start_date,
                'end_date' => $row['end_date'] ?? $project->end_date,
            ]);
        } else {
            // Create new project
            $project = Project::create([
                'name' => $row['name'],
                'code' => $row['code'] ?? strtoupper(substr(preg_replace('/[^a-zA-Z0-9]/', '', $row['name']), 0, 8)),
                'description' => $row['description'] ?? '',
                'status' => $row['status'] ?? 'planning',
                'priority' => $row['priority'] ?? 'medium',
                'budget' => $row['budget'] ?? null,
                'start_date' => $row['start_date'] ?? null,
                'end_date' => $row['end_date'] ?? null,
                'owner_id' => auth()->id() ?? 1, // Default to user ID 1 if no auth
                'tenant_id' => $options['tenant_id'] ?? auth()->user()->tenant_id ?? 'default',
            ]);
        }

        return $project;
    }

    /**
     * Validate CSV file
     */
    public function validateFile(UploadedFile $file, string $type): array
    {
        try {
            $data = $this->parseCsvFile($file);
            
            $validation = [
                'valid' => true,
                'errors' => [],
                'warnings' => [],
                'row_count' => count($data)
            ];

            // Type-specific validation
            switch ($type) {
                case 'users':
                    $this->validateUserData($data);
                    break;
                case 'projects':
                    $this->validateProjectData($data);
                    break;
                default:
                    throw new \Exception("Unknown import type: {$type}");
            }

            return $validation;

        } catch (\Exception $e) {
            return [
                'valid' => false,
                'errors' => [$e->getMessage()],
                'warnings' => [],
                'row_count' => 0
            ];
        }
    }

    /**
     * Get import template
     */
    public function getTemplate(string $type): string
    {
        $templates = [
            'users' => [
                ['name', 'email', 'phone', 'department', 'status', 'password'],
                ['John Doe', 'john@example.com', '+1234567890', 'Engineering', 'active', 'password123'],
                ['Jane Smith', 'jane@example.com', '+1234567891', 'Marketing', 'active', 'password123']
            ],
            'projects' => [
                ['name', 'description', 'status', 'priority', 'budget', 'start_date', 'end_date'],
                ['Project Alpha', 'Description of Project Alpha', 'planning', 'high', '100000', '2025-01-01', '2025-12-31'],
                ['Project Beta', 'Description of Project Beta', 'active', 'medium', '50000', '2025-02-01', '2025-11-30']
            ],
            'tasks' => [
                ['title', 'description', 'status', 'priority', 'project_id', 'assigned_to', 'due_date'],
                ['Task 1', 'Description of Task 1', 'pending', 'high', 'project-id-1', 'user-id-1', '2025-01-15'],
                ['Task 2', 'Description of Task 2', 'in_progress', 'medium', 'project-id-1', 'user-id-2', '2025-01-20']
            ]
        ];

        if (!isset($templates[$type])) {
            throw new \Exception("Unknown template type: {$type}");
        }

        $csvData = $templates[$type];
        $output = fopen('php://temp', 'r+');
        
        foreach ($csvData as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
        
        return $csvContent;
    }
}
