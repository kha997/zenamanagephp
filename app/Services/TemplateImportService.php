<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use App\Models\TemplateSet;
use App\Models\TemplatePhase;
use App\Models\TemplateDiscipline;
use App\Models\TemplateTask;
use App\Models\TemplateTaskDependency;
use App\Models\TemplatePreset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * TemplateImportService
 * 
 * Service for importing template sets from CSV, XLSX, or JSON files.
 * Handles parsing, validation, normalization, and persistence of template data.
 */
class TemplateImportService
{
    private int $maxRows;
    private array $allowedMimeTypes;
    private array $requiredCsvHeaders;

    public function __construct()
    {
        $this->maxRows = config('csv.max_rows', 10000);
        $this->allowedMimeTypes = [
            'text/csv',
            'text/plain',
            'application/csv',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/json',
        ];
        $this->requiredCsvHeaders = [
            'phase_code',
            'phase_name',
            'discipline_code',
            'discipline_name',
            'task_code',
            'task_name',
        ];
    }

    /**
     * Import template set from uploaded file
     * 
     * @param UploadedFile $file The uploaded file (CSV, XLSX, or JSON)
     * @param User $user The user performing the import
     * @param string|null $tenantId Optional tenant ID (null for global template)
     * @return TemplateSet The created template set
     * @throws \Exception If import fails
     */
    public function importFromFile(UploadedFile $file, User $user, ?string $tenantId = null): TemplateSet
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $mimeType = $file->getMimeType();

        if (!in_array($mimeType, $this->allowedMimeTypes)) {
            throw new \Exception("Invalid file type. Allowed: CSV, XLSX, JSON");
        }

        try {
            if ($extension === 'json' || $mimeType === 'application/json') {
                $content = file_get_contents($file->getPathname());
                $payload = json_decode($content, true);
                
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception('Invalid JSON: ' . json_last_error_msg());
                }
                
                return $this->importFromJson($payload, $user, $tenantId);
            } else {
                // CSV or XLSX
                return $this->importFromCsvOrExcel($file, $user, $tenantId);
            }
        } catch (\Exception $e) {
            Log::error('Template import failed', [
                'error' => $e->getMessage(),
                'file_name' => $file->getClientOriginalName(),
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
            ]);
            throw $e;
        }
    }

    /**
     * Import template set from JSON payload
     * 
     * @param array $payload JSON payload following the template schema
     * @param User $user The user performing the import
     * @param string|null $tenantId Optional tenant ID (null for global template)
     * @return TemplateSet The created template set
     * @throws \Exception If import fails
     */
    public function importFromJson(array $payload, User $user, ?string $tenantId = null): TemplateSet
    {
        // Validate JSON schema
        $this->validateJsonSchema($payload);

        return DB::transaction(function () use ($payload, $user, $tenantId) {
            // Create template set
            $set = TemplateSet::create([
                'code' => $this->normalizeCode($payload['set']['code']),
                'name' => $payload['set']['name'],
                'description' => $payload['set']['description'] ?? null,
                'version' => $payload['set']['version'] ?? '1.0',
                'is_active' => $payload['set']['is_active'] ?? true,
                'created_by' => $user->id,
                'tenant_id' => $tenantId,
                'metadata' => $payload['set']['metadata'] ?? null,
            ]);

            // Validate uniqueness for global templates
            if ($set->is_global) {
                $existing = TemplateSet::whereNull('tenant_id')
                    ->where('code', $set->code)
                    ->where('id', '!=', $set->id)
                    ->exists();
                
                if ($existing) {
                    throw new \Exception("Global template with code '{$set->code}' already exists");
                }
            }

            // Create phases
            $phaseMap = [];
            foreach ($payload['phases'] ?? [] as $phaseData) {
                $phase = TemplatePhase::create([
                    'set_id' => $set->id,
                    'code' => $this->normalizeCode($phaseData['code']),
                    'name' => $phaseData['name'],
                    'order_index' => $phaseData['order'] ?? 0,
                    'metadata' => $phaseData['metadata'] ?? null,
                ]);
                $phaseMap[$phase->code] = $phase->id;
            }

            // Create disciplines
            $disciplineMap = [];
            foreach ($payload['disciplines'] ?? [] as $disciplineData) {
                $discipline = TemplateDiscipline::create([
                    'set_id' => $set->id,
                    'code' => $this->normalizeCode($disciplineData['code']),
                    'name' => $disciplineData['name'],
                    'color_hex' => $disciplineData['color'] ?? null,
                    'order_index' => $disciplineData['order'] ?? 0,
                    'metadata' => $disciplineData['metadata'] ?? null,
                ]);
                $disciplineMap[$discipline->code] = $discipline->id;
            }

            // Create tasks
            $taskMap = [];
            $taskCodes = [];
            foreach ($payload['tasks'] ?? [] as $taskData) {
                $taskCode = $this->normalizeCode($taskData['code']);
                
                // Check uniqueness within set
                if (isset($taskCodes[$taskCode])) {
                    throw new \Exception("Duplicate task code '{$taskCode}' in template set");
                }
                $taskCodes[$taskCode] = true;

                $phaseCode = $this->normalizeCode($taskData['phase']);
                $disciplineCode = $this->normalizeCode($taskData['discipline']);

                if (!isset($phaseMap[$phaseCode])) {
                    throw new \Exception("Phase '{$phaseCode}' not found for task '{$taskCode}'");
                }

                if (!isset($disciplineMap[$disciplineCode])) {
                    throw new \Exception("Discipline '{$disciplineCode}' not found for task '{$taskCode}'");
                }

                $task = TemplateTask::create([
                    'set_id' => $set->id,
                    'phase_id' => $phaseMap[$phaseCode],
                    'discipline_id' => $disciplineMap[$disciplineCode],
                    'code' => $taskCode,
                    'name' => $taskData['name'],
                    'description' => $taskData['description'] ?? null,
                    'est_duration_days' => $taskData['est_duration_days'] ?? null,
                    'role_key' => $taskData['role_key'] ?? null,
                    'deliverable_type' => $taskData['deliverable_type'] ?? null,
                    'order_index' => $taskData['order'] ?? 0,
                    'is_optional' => $taskData['is_optional'] ?? false,
                    'metadata' => $taskData['metadata'] ?? null,
                ]);
                $taskMap[$taskCode] = $task->id;
            }

            // Create dependencies
            foreach ($payload['tasks'] ?? [] as $taskData) {
                if (empty($taskData['depends_on'])) {
                    continue;
                }

                $taskCode = $this->normalizeCode($taskData['code']);
                $taskId = $taskMap[$taskCode] ?? null;

                if (!$taskId) {
                    continue;
                }

                $dependsOnCodes = is_array($taskData['depends_on']) 
                    ? $taskData['depends_on'] 
                    : [$taskData['depends_on']];

                foreach ($dependsOnCodes as $dependsOnCode) {
                    $dependsOnCode = $this->normalizeCode($dependsOnCode);
                    $dependsOnTaskId = $taskMap[$dependsOnCode] ?? null;

                    if (!$dependsOnTaskId) {
                        Log::warning('Dependency task not found', [
                            'task_code' => $taskCode,
                            'depends_on_code' => $dependsOnCode,
                        ]);
                        continue;
                    }

                    TemplateTaskDependency::create([
                        'set_id' => $set->id,
                        'task_id' => $taskId,
                        'depends_on_task_id' => $dependsOnTaskId,
                    ]);
                }
            }

            // Create presets
            foreach ($payload['presets'] ?? [] as $presetData) {
                TemplatePreset::create([
                    'set_id' => $set->id,
                    'code' => $this->normalizeCode($presetData['code']),
                    'name' => $presetData['name'],
                    'description' => $presetData['description'] ?? null,
                    'filters' => $presetData['filters'] ?? [],
                ]);
            }

            Log::info('Template set imported successfully', [
                'set_id' => $set->id,
                'code' => $set->code,
                'user_id' => $user->id,
                'tenant_id' => $tenantId,
            ]);

            return $set->fresh(['phases', 'disciplines', 'tasks', 'presets']);
        });
    }

    /**
     * Import template set from CSV or Excel file
     * 
     * @param UploadedFile $file The uploaded file
     * @param User $user The user performing the import
     * @param string|null $tenantId Optional tenant ID
     * @return TemplateSet The created template set
     * @throws \Exception If import fails
     */
    private function importFromCsvOrExcel(UploadedFile $file, User $user, ?string $tenantId = null): TemplateSet
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $data = [];

        if ($extension === 'csv') {
            $data = $this->parseCsvFile($file);
        } else {
            $data = $this->parseExcelFile($file);
        }

        // Validate headers
        if (empty($data)) {
            throw new \Exception('File is empty or invalid');
        }

        $headers = array_keys($data[0]);
        $this->validateCsvHeaders($headers);

        // Transform CSV data to JSON-like structure
        $payload = $this->transformCsvToJson($data);

        return $this->importFromJson($payload, $user, $tenantId);
    }

    /**
     * Parse CSV file
     */
    private function parseCsvFile(UploadedFile $file): array
    {
        $content = file_get_contents($file->getPathname());
        $lines = str_getcsv($content, "\n");

        if (count($lines) < 2) {
            throw new \Exception('CSV file must contain at least a header row and one data row.');
        }

        if (count($lines) > $this->maxRows + 1) {
            throw new \Exception("Too many rows. Maximum allowed: {$this->maxRows}");
        }

        // Parse header and normalize
        $headers = array_map([$this, 'normalizeHeader'], str_getcsv($lines[0]));

        // Parse data rows
        $data = [];
        for ($i = 1; $i < count($lines); $i++) {
            $row = str_getcsv($lines[$i]);
            if (count($row) === count($headers)) {
                $data[] = array_combine($headers, $row);
            } else {
                Log::warning('CSV row skipped due to column count mismatch', [
                    'row' => $i + 1,
                    'expected' => count($headers),
                    'actual' => count($row),
                ]);
            }
        }

        return $data;
    }

    /**
     * Parse Excel file
     */
    private function parseExcelFile(UploadedFile $file): array
    {
        try {
            $spreadsheet = IOFactory::load($file->getPathname());
            $worksheet = $spreadsheet->getActiveSheet();
            $rows = $worksheet->toArray();

            if (count($rows) < 2) {
                throw new \Exception('Excel file must contain at least a header row and one data row.');
            }

            if (count($rows) > $this->maxRows + 1) {
                throw new \Exception("Too many rows. Maximum allowed: {$this->maxRows}");
            }

            // Parse header and normalize
            $headers = array_map([$this, 'normalizeHeader'], array_shift($rows));

            // Parse data rows
            $data = [];
            foreach ($rows as $row) {
                if (count($row) === count($headers)) {
                    $data[] = array_combine($headers, $row);
                }
            }

            return $data;
        } catch (\Exception $e) {
            throw new \Exception('Failed to parse Excel file: ' . $e->getMessage());
        }
    }

    /**
     * Transform CSV data to JSON-like structure
     */
    private function transformCsvToJson(array $csvData): array
    {
        // Extract unique phases, disciplines, and tasks
        $phases = [];
        $disciplines = [];
        $tasks = [];
        $presets = [];

        $phaseOrder = 0;
        $disciplineOrder = 0;
        $phaseMap = [];
        $disciplineMap = [];

        foreach ($csvData as $row) {
            // Phase
            $phaseCode = $this->normalizeCode($row['phase_code'] ?? '');
            if (!empty($phaseCode) && !isset($phaseMap[$phaseCode])) {
                $phases[] = [
                    'code' => $phaseCode,
                    'name' => $row['phase_name'] ?? $phaseCode,
                    'order' => $phaseOrder++,
                ];
                $phaseMap[$phaseCode] = true;
            }

            // Discipline
            $disciplineCode = $this->normalizeCode($row['discipline_code'] ?? '');
            if (!empty($disciplineCode) && !isset($disciplineMap[$disciplineCode])) {
                $disciplines[] = [
                    'code' => $disciplineCode,
                    'name' => $row['discipline_name'] ?? $disciplineCode,
                    'color' => $row['color_hex'] ?? null,
                    'order' => $disciplineOrder++,
                ];
                $disciplineMap[$disciplineCode] = true;
            }

            // Task
            $taskCode = $this->normalizeCode($row['task_code'] ?? '');
            if (!empty($taskCode)) {
                $dependsOnCodes = [];
                if (!empty($row['depends_on_codes'])) {
                    $dependsOnCodes = array_map(
                        [$this, 'normalizeCode'],
                        explode('|', $row['depends_on_codes'])
                    );
                }

                $tasks[] = [
                    'code' => $taskCode,
                    'name' => $row['task_name'] ?? $taskCode,
                    'phase' => $phaseCode,
                    'discipline' => $disciplineCode,
                    'description' => $row['description'] ?? null,
                    'est_duration_days' => !empty($row['est_duration_days']) ? (int)$row['est_duration_days'] : null,
                    'role_key' => $row['role_key'] ?? null,
                    'deliverable_type' => $row['deliverable_type'] ?? null,
                    'order' => !empty($row['order_index']) ? (int)$row['order_index'] : 0,
                    'is_optional' => !empty($row['is_optional']) && in_array(strtolower($row['is_optional']), ['1', 'true', 'yes']),
                    'depends_on' => $dependsOnCodes,
                ];
            }
        }

        // Generate template set code and name from first row or use defaults
        $setCode = $this->normalizeCode($csvData[0]['set_code'] ?? 'WBS-IMPORT-' . date('YmdHis'));
        $setName = $csvData[0]['set_name'] ?? 'Imported Template Set';

        return [
            'set' => [
                'code' => $setCode,
                'name' => $setName,
                'version' => $csvData[0]['version'] ?? '1.0',
                'is_active' => true,
            ],
            'phases' => $phases,
            'disciplines' => $disciplines,
            'tasks' => $tasks,
            'presets' => $presets,
        ];
    }

    /**
     * Validate CSV headers
     */
    public function validateCsvHeaders(array $headers): bool
    {
        $normalizedHeaders = array_map([$this, 'normalizeHeader'], $headers);
        
        foreach ($this->requiredCsvHeaders as $required) {
            if (!in_array($required, $normalizedHeaders)) {
                throw new \Exception("Required header '{$required}' is missing. Found headers: " . implode(', ', $normalizedHeaders));
            }
        }

        return true;
    }

    /**
     * Validate JSON schema
     */
    private function validateJsonSchema(array $payload): void
    {
        if (empty($payload['set'])) {
            throw new \Exception('Missing required field: set');
        }

        if (empty($payload['set']['code'])) {
            throw new \Exception('Missing required field: set.code');
        }

        if (empty($payload['set']['name'])) {
            throw new \Exception('Missing required field: set.name');
        }

        // Phases, disciplines, and tasks are optional but recommended
    }

    /**
     * Normalize code (uppercase, spaces/hyphens to underscores)
     */
    public function normalizeCode(string $code): string
    {
        // Trim whitespace
        $code = trim($code);
        
        // Convert to uppercase
        $code = strtoupper($code);
        
        // Replace spaces and hyphens with underscores
        $code = preg_replace('/[\s\-]+/', '_', $code);
        
        // Remove any remaining non-alphanumeric characters except underscores
        $code = preg_replace('/[^A-Z0-9_]/', '', $code);
        
        // Remove multiple consecutive underscores
        $code = preg_replace('/_+/', '_', $code);
        
        // Remove leading/trailing underscores
        $code = trim($code, '_');
        
        return $code;
    }

    /**
     * Normalize CSV header (map localized names to snake_case)
     */
    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        
        // Map common Vietnamese/localized headers to English
        $headerMap = [
            'mã giai đoạn' => 'phase_code',
            'tên giai đoạn' => 'phase_name',
            'mã chuyên ngành' => 'discipline_code',
            'tên chuyên ngành' => 'discipline_name',
            'mã công việc' => 'task_code',
            'tên công việc' => 'task_name',
            'mô tả' => 'description',
            'thời gian ước tính (ngày)' => 'est_duration_days',
            'vai trò' => 'role_key',
            'loại sản phẩm' => 'deliverable_type',
            'thứ tự' => 'order_index',
            'tùy chọn' => 'is_optional',
            'phụ thuộc' => 'depends_on_codes',
        ];

        $headerLower = strtolower($header);
        if (isset($headerMap[$headerLower])) {
            return $headerMap[$headerLower];
        }

        // Convert to snake_case
        $header = strtolower($header);
        $header = preg_replace('/[\s\-]+/', '_', $header);
        $header = preg_replace('/[^a-z0-9_]/', '', $header);
        $header = preg_replace('/_+/', '_', $header);
        $header = trim($header, '_');

        return $header;
    }
}

