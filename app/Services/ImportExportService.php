<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Facades\Auth;

use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Import/Export Service
 * 
 * Handles data import/export operations for various entities
 */
class ImportExportService
{
    private SecureAuditService $auditService;
    private BulkOperationsService $bulkOperationsService;
    private int $maxImportRows;
    private array $allowedFileTypes;

    public function __construct(
        SecureAuditService $auditService,
        BulkOperationsService $bulkOperationsService
    ) {
        $this->auditService = $auditService;
        $this->bulkOperationsService = $bulkOperationsService;
        $this->maxImportRows = config('import.max_rows', 10000);
        $this->allowedFileTypes = ['xlsx', 'xls', 'csv'];
    }

    /*
    |--------------------------------------------------------------------------
    | Export Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Export users to Excel
     */
    public function exportUsers(array $filters = [], string $format = 'xlsx'): string
    {
        $query = User::query();
        
        // Apply filters
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('is_active', $filters['status'] === 'active');
        }
        
        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }
        
        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $users = $query->get();
        
        $filename = $this->generateExportFilename('users', $format);
        $filepath = $this->exportToExcel($users, $filename, $this->getUserExportColumns());
        
        // Log export
        $this->auditService->logAction(
            userId: Auth::id() ?? 'system',
            action: 'export_users',
            entityType: 'User',
            newData: [
                'format' => $format,
                'count' => $users->count(),
                'filters' => $filters,
                'filename' => $filename
            ]
        );

        return $filepath;
    }

    /**
     * Export projects to Excel
     */
    public function exportProjects(array $filters = [], string $format = 'xlsx'): string
    {
        $query = Project::query();
        
        // Apply filters
        if (isset($filters['tenant_id'])) {
            $query->where('tenant_id', $filters['tenant_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        $projects = $query->get();
        
        $filename = $this->generateExportFilename('projects', $format);
        $filepath = $this->exportToExcel($projects, $filename, $this->getProjectExportColumns());
        
        // Log export
        $this->auditService->logAction(
            userId: Auth::id() ?? 'system',
            action: 'export_projects',
            entityType: 'Project',
            newData: [
                'format' => $format,
                'count' => $projects->count(),
                'filters' => $filters,
                'filename' => $filename
            ]
        );

        return $filepath;
    }

    /**
     * Export tasks to Excel
     */
    public function exportTasks(array $filters = [], string $format = 'xlsx'): string
    {
        $query = Task::query();
        
        // Apply filters
        if (isset($filters['project_id'])) {
            $query->where('project_id', $filters['project_id']);
        }
        
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        
        if (isset($filters['assignee_id'])) {
            $query->where('assignee_id', $filters['assignee_id']);
        }

        $tasks = $query->get();
        
        $filename = $this->generateExportFilename('tasks', $format);
        $filepath = $this->exportToExcel($tasks, $filename, $this->getTaskExportColumns());
        
        // Log export
        $this->auditService->logAction(
            userId: Auth::id() ?? 'system',
            action: 'export_tasks',
            entityType: 'Task',
            newData: [
                'format' => $format,
                'count' => $tasks->count(),
                'filters' => $filters,
                'filename' => $filename
            ]
        );

        return $filepath;
    }

    /**
     * Export data to Excel file
     */
    private function exportToExcel(Collection $data, string $filename, array $columns): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Set headers
        $headerRow = 1;
        foreach ($columns as $index => $column) {
            $sheet->setCellValueByColumnAndRow($index + 1, $headerRow, $column['title']);
        }
        
        // Set data
        $row = 2;
        foreach ($data as $item) {
            foreach ($columns as $index => $column) {
                $value = $this->getColumnValue($item, $column);
                $sheet->setCellValueByColumnAndRow($index + 1, $row, $value);
            }
            $row++;
        }
        
        // Auto-size columns
        foreach ($columns as $index => $column) {
            $sheet->getColumnDimensionByColumn($index + 1)->setAutoSize(true);
        }
        
        // Save file
        $writer = new Xlsx($spreadsheet);
        $filepath = storage_path('app/exports/' . $filename);
        
        // Ensure directory exists
        if (!file_exists(dirname($filepath))) {
            mkdir(dirname($filepath), 0755, true);
        }
        
        $writer->save($filepath);
        
        return $filepath;
    }

    /*
    |--------------------------------------------------------------------------
    | Import Operations
    |--------------------------------------------------------------------------
    */

    /**
     * Import users from Excel/CSV
     */
    public function importUsers(string $filepath, array $options = []): array
    {
        $data = $this->parseImportFile($filepath);
        $this->validateImportData($data, 'users');
        
        $results = [
            'total' => count($data),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'imported_users' => []
        ];

        try {
            // Use bulk operations service
            $bulkResults = $this->bulkOperationsService->bulkCreateUsers($data, $options['tenant_id'] ?? null);
            
            $results['success'] = $bulkResults['success'];
            $results['failed'] = $bulkResults['failed'];
            $results['errors'] = $bulkResults['errors'];
            $results['imported_users'] = $bulkResults['created_users'];

            // Log import
            $this->auditService->logAction(
                userId: Auth::id() ?? 'system',
                action: 'import_users',
                entityType: 'User',
                newData: [
                    'filepath' => $filepath,
                    'total' => $results['total'],
                    'success' => $results['success'],
                    'failed' => $results['failed'],
                    'options' => $options
                ]
            );

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Import projects from Excel/CSV
     */
    public function importProjects(string $filepath, array $options = []): array
    {
        $data = $this->parseImportFile($filepath);
        $this->validateImportData($data, 'projects');
        
        $results = [
            'total' => count($data),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'imported_projects' => []
        ];

        try {
            // Use bulk operations service
            $bulkResults = $this->bulkOperationsService->bulkCreateProjects($data, $options['tenant_id'] ?? null);
            
            $results['success'] = $bulkResults['success'];
            $results['failed'] = $bulkResults['failed'];
            $results['errors'] = $bulkResults['errors'];
            $results['imported_projects'] = $bulkResults['created_projects'];

            // Log import
            $this->auditService->logAction(
                userId: Auth::id() ?? 'system',
                action: 'import_projects',
                entityType: 'Project',
                newData: [
                    'filepath' => $filepath,
                    'total' => $results['total'],
                    'success' => $results['success'],
                    'failed' => $results['failed'],
                    'options' => $options
                ]
            );

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Import tasks from Excel/CSV
     */
    public function importTasks(string $filepath, string $projectId, array $options = []): array
    {
        $data = $this->parseImportFile($filepath);
        $this->validateImportData($data, 'tasks');
        
        $results = [
            'total' => count($data),
            'success' => 0,
            'failed' => 0,
            'errors' => [],
            'imported_tasks' => []
        ];

        try {
            // Use bulk operations service
            $bulkResults = $this->bulkOperationsService->bulkCreateTasks($data, $projectId, $options['tenant_id'] ?? null);
            
            $results['success'] = $bulkResults['success'];
            $results['failed'] = $bulkResults['failed'];
            $results['errors'] = $bulkResults['errors'];
            $results['imported_tasks'] = $bulkResults['created_tasks'];

            // Log import
            $this->auditService->logAction(
                userId: Auth::id() ?? 'system',
                action: 'import_tasks',
                entityType: 'Task',
                projectId: $projectId,
                newData: [
                    'filepath' => $filepath,
                    'project_id' => $projectId,
                    'total' => $results['total'],
                    'success' => $results['success'],
                    'failed' => $results['failed'],
                    'options' => $options
                ]
            );

        } catch (\Exception $e) {
            $results['errors'][] = [
                'error' => $e->getMessage()
            ];
        }

        return $results;
    }

    /**
     * Parse import file (Excel/CSV)
     */
    private function parseImportFile(string $filepath): array
    {
        $extension = strtolower(pathinfo($filepath, PATHINFO_EXTENSION));
        
        if (!in_array($extension, $this->allowedFileTypes)) {
            throw new \Exception('Unsupported file type. Allowed: ' . implode(', ', $this->allowedFileTypes));
        }

        if ($extension === 'csv') {
            $reader = new CsvReader();
        } else {
            $reader = new XlsxReader();
        }

        $spreadsheet = $reader->load($filepath);
        $worksheet = $spreadsheet->getActiveSheet();
        $rows = $worksheet->toArray();

        if (count($rows) < 2) {
            throw new \Exception('File must contain at least a header row and one data row');
        }

        if (count($rows) > $this->maxImportRows + 1) {
            throw new \Exception("Too many rows. Maximum allowed: {$this->maxImportRows}");
        }

        // Convert to associative array
        $headers = array_shift($rows);
        $data = [];

        foreach ($rows as $row) {
            $rowData = [];
            foreach ($headers as $index => $header) {
                $rowData[$header] = $row[$index] ?? null;
            }
            $data[] = $rowData;
        }

        return $data;
    }

    /**
     * Validate import data
     */
    private function validateImportData(array $data, string $type): void
    {
        if (empty($data)) {
            throw new \Exception('No data to import');
        }

        // Define required fields for each type
        $requiredFields = [
            'users' => ['name', 'email'],
            'projects' => ['name', 'description'],
            'tasks' => ['title', 'description']
        ];

        if (!isset($requiredFields[$type])) {
            throw new \Exception("Unknown import type: {$type}");
        }

        $required = $requiredFields[$type];
        
        foreach ($data as $index => $row) {
            foreach ($required as $field) {
                if (empty($row[$field])) {
                    throw new \Exception("Row " . ($index + 1) . ": Required field '{$field}' is missing");
                }
            }
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Helper Methods
    |--------------------------------------------------------------------------
    */

    /**
     * Generate export filename
     */
    private function generateExportFilename(string $type, string $format): string
    {
        $timestamp = Carbon::now()->format('Y-m-d_H-i-s');
        return "{$type}_export_{$timestamp}.{$format}";
    }

    /**
     * Get column value for export
     */
    private function getColumnValue($item, array $column): mixed
    {
        $value = data_get($item, $column['field']);
        
        if (isset($column['formatter']) && $column['formatter'] instanceof Closure) {
            return $column['formatter']($value, $item);
        }
        
        return $value;
    }

    /**
     * Get user export columns
     */
    private function getUserExportColumns(): array
    {
        return [
            ['field' => 'id', 'title' => 'ID'],
            ['field' => 'name', 'title' => 'Name'],
            ['field' => 'email', 'title' => 'Email'],
            ['field' => 'phone', 'title' => 'Phone'],
            ['field' => 'is_active', 'title' => 'Status', 'formatter' => fn($value) => $value ? 'Active' : 'Inactive'],
            ['field' => 'created_at', 'title' => 'Created At', 'formatter' => fn($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : ''],
            ['field' => 'last_login_at', 'title' => 'Last Login', 'formatter' => fn($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : 'Never'],
        ];
    }

    /**
     * Get project export columns
     */
    private function getProjectExportColumns(): array
    {
        return [
            ['field' => 'id', 'title' => 'ID'],
            ['field' => 'name', 'title' => 'Project Name'],
            ['field' => 'description', 'title' => 'Description'],
            ['field' => 'status', 'title' => 'Status'],
            ['field' => 'start_date', 'title' => 'Start Date'],
            ['field' => 'end_date', 'title' => 'End Date'],
            ['field' => 'created_at', 'title' => 'Created At', 'formatter' => fn($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : ''],
        ];
    }

    /**
     * Get task export columns
     */
    private function getTaskExportColumns(): array
    {
        return [
            ['field' => 'id', 'title' => 'ID'],
            ['field' => 'title', 'title' => 'Task Title'],
            ['field' => 'description', 'title' => 'Description'],
            ['field' => 'status', 'title' => 'Status'],
            ['field' => 'priority', 'title' => 'Priority'],
            ['field' => 'due_date', 'title' => 'Due Date'],
            ['field' => 'assignee_id', 'title' => 'Assignee ID'],
            ['field' => 'created_at', 'title' => 'Created At', 'formatter' => fn($value) => $value ? Carbon::parse($value)->format('Y-m-d H:i:s') : ''],
        ];
    }

    /**
     * Get import template
     */
    public function getImportTemplate(string $type): string
    {
        $columns = match($type) {
            'users' => $this->getUserExportColumns(),
            'projects' => $this->getProjectExportColumns(),
            'tasks' => $this->getTaskExportColumns(),
            default => throw new \Exception("Unknown template type: {$type}")
        };

        $filename = "{$type}_import_template.xlsx";
        $filepath = $this->exportToExcel(collect([]), $filename, $columns);
        
        return $filepath;
    }

    /**
     * Clean up old export files
     */
    public function cleanupOldExports(int $daysOld = 7): int
    {
        $exportDir = storage_path('app/exports');
        $cutoffTime = Carbon::now()->subDays($daysOld);
        $deletedCount = 0;

        if (is_dir($exportDir)) {
            $files = glob($exportDir . '/*');
            
            foreach ($files as $file) {
                if (is_file($file) && filemtime($file) < $cutoffTime->timestamp) {
                    unlink($file);
                    $deletedCount++;
                }
            }
        }

        return $deletedCount;
    }
}
