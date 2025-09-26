<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Csv;
use Barryvdh\DomPDF\Facade\Pdf;

class ExportService
{
    /**
     * Export data to CSV format
     */
    public function exportToCSV(array $data, string $filename, array $columns = []): string
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $userId = Auth::id();
        
        $filePath = "exports/{$tenantId}/{$userId}/" . $filename . '_' . now()->format('Y-m-d_H-i-s') . '.csv';
        
        $csvData = [];
        
        // Add headers
        if (!empty($columns)) {
            $csvData[] = array_values($columns);
        } elseif (!empty($data)) {
            $csvData[] = array_keys($data[0]);
        }
        
        // Add data rows
        foreach ($data as $row) {
            if (!empty($columns)) {
                $csvRow = [];
                foreach ($columns as $key => $label) {
                    $csvRow[] = $row[$key] ?? '';
                }
                $csvData[] = $csvRow;
            } else {
                $csvData[] = array_values($row);
            }
        }
        
        // Create CSV content
        $csvContent = '';
        foreach ($csvData as $row) {
            $csvContent .= $this->arrayToCsv($row) . "\n";
        }
        
        // Store file
        Storage::put($filePath, $csvContent);
        
        return $filePath;
    }
    
    /**
     * Export data to Excel format
     */
    public function exportToExcel(array $data, string $filename, array $columns = []): string
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $userId = Auth::id();
        
        $filePath = "exports/{$tenantId}/{$userId}/" . $filename . '_' . now()->format('Y-m-d_H-i-s') . '.xlsx';
        
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $row = 1;
        
        // Add headers
        if (!empty($columns)) {
            $col = 1;
            foreach ($columns as $key => $label) {
                $sheet->setCellValueByColumnAndRow($col, $row, $label);
                $col++;
            }
            $row++;
        } elseif (!empty($data)) {
            $col = 1;
            foreach (array_keys($data[0]) as $header) {
                $sheet->setCellValueByColumnAndRow($col, $row, $header);
                $col++;
            }
            $row++;
        }
        
        // Add data rows
        foreach ($data as $dataRow) {
            $col = 1;
            if (!empty($columns)) {
                foreach ($columns as $key => $label) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $dataRow[$key] ?? '');
                    $col++;
                }
            } else {
                foreach ($dataRow as $value) {
                    $sheet->setCellValueByColumnAndRow($col, $row, $value);
                    $col++;
                }
            }
            $row++;
        }
        
        // Auto-size columns
        foreach (range('A', $sheet->getHighestColumn()) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        
        // Save file
        $writer = new Xlsx($spreadsheet);
        $writer->save(storage_path('app/' . $filePath));
        
        return $filePath;
    }
    
    /**
     * Export data to PDF format
     */
    public function exportToPDF(array $data, string $filename, array $columns = [], string $title = ''): string
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $userId = Auth::id();
        
        $filePath = "exports/{$tenantId}/{$userId}/" . $filename . '_' . now()->format('Y-m-d_H-i-s') . '.pdf';
        
        $pdf = Pdf::loadView('exports.pdf-template', [
            'data' => $data,
            'columns' => $columns,
            'title' => $title ?: $filename,
            'exportedAt' => now()->format('Y-m-d H:i:s'),
            'exportedBy' => Auth::user()->name ?? 'Unknown User'
        ]);
        
        $pdf->save(storage_path('app/' . $filePath));
        
        return $filePath;
    }
    
    /**
     * Export projects data
     */
    public function exportProjects(array $filters = []): array
    {
        $projects = $this->getProjectsData($filters);
        
        $columns = [
            'name' => 'Project Name',
            'code' => 'Project Code',
            'status' => 'Status',
            'health' => 'Health',
            'progress' => 'Progress (%)',
            'budget' => 'Budget',
            'start_date' => 'Start Date',
            'due_date' => 'Due Date',
            'project_manager' => 'Project Manager',
            'team_size' => 'Team Size'
        ];
        
        return [
            'csv' => $this->exportToCSV($projects, 'projects', $columns),
            'excel' => $this->exportToExcel($projects, 'projects', $columns),
            'pdf' => $this->exportToPDF($projects, 'projects', $columns, 'Projects Report')
        ];
    }
    
    /**
     * Export tasks data
     */
    public function exportTasks(array $filters = []): array
    {
        $tasks = $this->getTasksData($filters);
        
        $columns = [
            'title' => 'Task Title',
            'project' => 'Project',
            'status' => 'Status',
            'priority' => 'Priority',
            'assigned_to' => 'Assigned To',
            'due_date' => 'Due Date',
            'estimated_hours' => 'Estimated Hours',
            'actual_hours' => 'Actual Hours',
            'progress' => 'Progress (%)',
            'created_at' => 'Created Date'
        ];
        
        return [
            'csv' => $this->exportToCSV($tasks, 'tasks', $columns),
            'excel' => $this->exportToExcel($tasks, 'tasks', $columns),
            'pdf' => $this->exportToPDF($tasks, 'tasks', $columns, 'Tasks Report')
        ];
    }
    
    /**
     * Export documents data
     */
    public function exportDocuments(array $filters = []): array
    {
        $documents = $this->getDocumentsData($filters);
        
        $columns = [
            'title' => 'Document Title',
            'filename' => 'Filename',
            'type' => 'Type',
            'size' => 'Size',
            'status' => 'Status',
            'project' => 'Project',
            'uploaded_by' => 'Uploaded By',
            'uploaded_at' => 'Upload Date',
            'version' => 'Version',
            'description' => 'Description'
        ];
        
        return [
            'csv' => $this->exportToCSV($documents, 'documents', $columns),
            'excel' => $this->exportToExcel($documents, 'documents', $columns),
            'pdf' => $this->exportToPDF($documents, 'documents', $columns, 'Documents Report')
        ];
    }
    
    /**
     * Export users data (admin only)
     */
    public function exportUsers(array $filters = []): array
    {
        if (!Auth::user()->hasRole('super_admin')) {
            throw new \Exception('Unauthorized to export users data');
        }
        
        $users = $this->getUsersData($filters);
        
        $columns = [
            'name' => 'Name',
            'email' => 'Email',
            'role' => 'Role',
            'status' => 'Status',
            'tenant' => 'Tenant',
            'last_login' => 'Last Login',
            'created_at' => 'Created Date',
            'phone' => 'Phone',
            'department' => 'Department'
        ];
        
        return [
            'csv' => $this->exportToCSV($users, 'users', $columns),
            'excel' => $this->exportToExcel($users, 'users', $columns),
            'pdf' => $this->exportToPDF($users, 'users', $columns, 'Users Report')
        ];
    }
    
    /**
     * Export tenants data (admin only)
     */
    public function exportTenants(array $filters = []): array
    {
        if (!Auth::user()->hasRole('super_admin')) {
            throw new \Exception('Unauthorized to export tenants data');
        }
        
        $tenants = $this->getTenantsData($filters);
        
        $columns = [
            'name' => 'Tenant Name',
            'domain' => 'Domain',
            'plan' => 'Plan',
            'status' => 'Status',
            'users_count' => 'Users Count',
            'projects_count' => 'Projects Count',
            'storage_used' => 'Storage Used',
            'created_at' => 'Created Date',
            'last_activity' => 'Last Activity'
        ];
        
        return [
            'csv' => $this->exportToCSV($tenants, 'tenants', $columns),
            'excel' => $this->exportToExcel($tenants, 'columns', $columns),
            'pdf' => $this->exportToPDF($tenants, 'tenants', $columns, 'Tenants Report')
        ];
    }
    
    /**
     * Get export history for user
     */
    public function getExportHistory(): array
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $userId = Auth::id();
        
        $exportPath = "exports/{$tenantId}/{$userId}/";
        
        if (!Storage::exists($exportPath)) {
            return [];
        }
        
        $files = Storage::files($exportPath);
        $exports = [];
        
        foreach ($files as $file) {
            $exports[] = [
                'filename' => basename($file),
                'path' => $file,
                'size' => Storage::size($file),
                'created_at' => Storage::lastModified($file),
                'download_url' => route('exports.download', ['file' => basename($file)])
            ];
        }
        
        // Sort by creation date (newest first)
        usort($exports, fn($a, $b) => $b['created_at'] <=> $a['created_at']);
        
        return $exports;
    }
    
    /**
     * Delete export file
     */
    public function deleteExport(string $filename): bool
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $userId = Auth::id();
        
        $filePath = "exports/{$tenantId}/{$userId}/{$filename}";
        
        if (Storage::exists($filePath)) {
            return Storage::delete($filePath);
        }
        
        return false;
    }
    
    /**
     * Clean old export files
     */
    public function cleanOldExports(int $daysOld = 7): int
    {
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $userId = Auth::id();
        
        $exportPath = "exports/{$tenantId}/{$userId}/";
        
        if (!Storage::exists($exportPath)) {
            return 0;
        }
        
        $files = Storage::files($exportPath);
        $deletedCount = 0;
        
        foreach ($files as $file) {
            $lastModified = Storage::lastModified($file);
            $daysSinceModified = (time() - $lastModified) / (24 * 60 * 60);
            
            if ($daysSinceModified > $daysOld) {
                if (Storage::delete($file)) {
                    $deletedCount++;
                }
            }
        }
        
        return $deletedCount;
    }
    
    /**
     * Convert array to CSV string
     */
    private function arrayToCsv(array $data): string
    {
        $csv = '';
        foreach ($data as $value) {
            $csv .= '"' . str_replace('"', '""', $value) . '",';
        }
        return rtrim($csv, ',');
    }
    
    // Mock data methods - these would be replaced with actual database queries
    
    private function getProjectsData(array $filters): array
    {
        return [
            [
                'name' => 'Website Redesign',
                'code' => 'WR-2024',
                'status' => 'active',
                'health' => 'good',
                'progress' => 75,
                'budget' => '$50,000',
                'start_date' => '2024-01-15',
                'due_date' => '2024-06-30',
                'project_manager' => 'John Doe',
                'team_size' => 5
            ],
            [
                'name' => 'Mobile App Development',
                'code' => 'MAD-2024',
                'status' => 'planning',
                'health' => 'at_risk',
                'progress' => 25,
                'budget' => '$75,000',
                'start_date' => '2024-02-01',
                'due_date' => '2024-08-31',
                'project_manager' => 'Jane Smith',
                'team_size' => 8
            ]
        ];
    }
    
    private function getTasksData(array $filters): array
    {
        return [
            [
                'title' => 'Design Homepage',
                'project' => 'Website Redesign',
                'status' => 'in_progress',
                'priority' => 'high',
                'assigned_to' => 'Mike Johnson',
                'due_date' => '2024-03-15',
                'estimated_hours' => 40,
                'actual_hours' => 25,
                'progress' => 62,
                'created_at' => '2024-01-20'
            ],
            [
                'title' => 'Setup Development Environment',
                'project' => 'Mobile App Development',
                'status' => 'pending',
                'priority' => 'medium',
                'assigned_to' => 'Sarah Wilson',
                'due_date' => '2024-02-28',
                'estimated_hours' => 16,
                'actual_hours' => 0,
                'progress' => 0,
                'created_at' => '2024-02-01'
            ]
        ];
    }
    
    private function getDocumentsData(array $filters): array
    {
        return [
            [
                'title' => 'Project Requirements',
                'filename' => 'requirements.pdf',
                'type' => 'PDF',
                'size' => '2.5 MB',
                'status' => 'approved',
                'project' => 'Website Redesign',
                'uploaded_by' => 'John Doe',
                'uploaded_at' => '2024-01-15',
                'version' => '1.0',
                'description' => 'Detailed project requirements'
            ]
        ];
    }
    
    private function getUsersData(array $filters): array
    {
        return [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'Project Manager',
                'status' => 'active',
                'tenant' => 'Acme Corp',
                'last_login' => '2024-03-10',
                'created_at' => '2024-01-01',
                'phone' => '+1-555-0123',
                'department' => 'Engineering'
            ]
        ];
    }
    
    private function getTenantsData(array $filters): array
    {
        return [
            [
                'name' => 'Acme Corporation',
                'domain' => 'acme.com',
                'plan' => 'Enterprise',
                'status' => 'active',
                'users_count' => 25,
                'projects_count' => 8,
                'storage_used' => '15.2 GB',
                'created_at' => '2024-01-01',
                'last_activity' => '2024-03-10'
            ]
        ];
    }
}
