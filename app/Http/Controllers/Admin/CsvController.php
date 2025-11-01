<?php declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CsvExportService;
use App\Services\CsvImportService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class CsvController extends Controller
{
    public function __construct(
        private CsvExportService $exportService,
        private CsvImportService $importService
    ) {}

    /**
     * Export users to CSV
     */
    public function exportUsers(Request $request)
    {
        try {
            $filters = $request->only(['role', 'status', 'created_from', 'created_to']);
            $csvContent = $this->exportService->exportUsers($filters);
            
            $filename = 'users_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
            
            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('CSV export failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'filters' => $request->only(['role', 'status', 'created_from', 'created_to'])
            ]);

            return ApiResponse::error('Export failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Export projects to CSV
     */
    public function exportProjects(Request $request)
    {
        try {
            $filters = $request->only(['status', 'priority', 'created_from', 'created_to']);
            $csvContent = $this->exportService->exportProjects($filters);
            
            $filename = 'projects_export_' . now()->format('Y-m-d_H-i-s') . '.csv';
            
            return response($csvContent)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('CSV export failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'filters' => $request->only(['status', 'priority', 'created_from', 'created_to'])
            ]);

            return ApiResponse::error('Export failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import users from CSV
     */
    public function importUsers(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
                'update_existing' => 'boolean',
                'send_welcome_email' => 'boolean'
            ]);

            $file = $request->file('file');
            $options = [
                'update_existing' => $request->boolean('update_existing', false),
                'send_welcome_email' => $request->boolean('send_welcome_email', true),
                'tenant_id' => auth()->user()->tenant_id
            ];

            $result = $this->importService->importUsers($file, $options);

            return ApiResponse::success([
                'message' => 'Import completed',
                'results' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('CSV import failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'file_name' => $request->file('file')?->getClientOriginalName()
            ]);

            return ApiResponse::error('Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Import projects from CSV
     */
    public function importProjects(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240', // 10MB max
                'update_existing' => 'boolean'
            ]);

            $file = $request->file('file');
            $options = [
                'update_existing' => $request->boolean('update_existing', false),
                'tenant_id' => auth()->user()->tenant_id
            ];

            $result = $this->importService->importProjects($file, $options);

            return ApiResponse::success([
                'message' => 'Import completed',
                'results' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('CSV import failed', [
                'error' => $e->getMessage(),
                'user_id' => auth()->id(),
                'file_name' => $request->file('file')?->getClientOriginalName()
            ]);

            return ApiResponse::error('Import failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get import template
     */
    public function getImportTemplate(Request $request)
    {
        try {
            $type = $request->input('type', 'users');
            $template = $this->importService->getTemplate($type);
            
            $filename = $type . '_import_template.csv';
            
            return response($template)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');

        } catch (\Exception $e) {
            Log::error('Template generation failed', [
                'error' => $e->getMessage(),
                'type' => $request->input('type')
            ]);

            return ApiResponse::error('Template generation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Validate CSV file before import
     */
    public function validateCsv(Request $request)
    {
        try {
            $request->validate([
                'file' => 'required|file|mimes:csv,txt|max:10240',
                'type' => 'required|in:users,projects,tasks'
            ]);

            $file = $request->file('file');
            $type = $request->input('type');
            
            $validation = $this->importService->validateFile($file, $type);

            return ApiResponse::success([
                'valid' => $validation['valid'],
                'errors' => $validation['errors'],
                'warnings' => $validation['warnings'],
                'row_count' => $validation['row_count']
            ]);

        } catch (\Exception $e) {
            Log::error('CSV validation failed', [
                'error' => $e->getMessage(),
                'type' => $request->input('type')
            ]);

            return ApiResponse::error('Validation failed: ' . $e->getMessage(), 500);
        }
    }
}
