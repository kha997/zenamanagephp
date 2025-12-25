<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Service tập trung các validation rules phức tạp
 * Hỗ trợ custom validation và business rules
 */
class ValidationService
{
    /**
     * Validate project data với business rules
     */
    public function validateProjectData(array $data, ?string $projectId = null): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
            'tenant_id' => 'required|exists:tenants,id'
        ];
        
        // Nếu update, bỏ qua validation start_date
        if ($projectId) {
            $rules['start_date'] = 'required|date';
        }
        
        $validator = Validator::make($data, $rules);
        
        // Custom validation: placeholder (no additional logic yet)
        $validator->after(function ($validator) {
            // TODO: add overlap checks when needed
        });
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        return $validator->validated();
    }
    
    /**
     * Validate task dependencies để tránh circular dependency
     */
    public function validateTaskDependencies(array $dependencies, string $taskId): bool
    {
        if (empty($dependencies)) {
            return true;
        }
        
        // Kiểm tra self-reference
        if (in_array($taskId, $dependencies, true)) {
            throw ValidationException::withMessages([
                'dependencies' => 'Task không thể phụ thuộc vào chính nó'
            ]);
        }
        
        // Kiểm tra circular dependency bằng DFS
        $visited = [];
        $recursionStack = [];
        
        foreach ($dependencies as $depId) {
            if ($this->hasCircularDependency($depId, $taskId, $visited, $recursionStack)) {
                throw ValidationException::withMessages([
                    'dependencies' => 'Phát hiện circular dependency trong task dependencies'
                ]);
            }
        }
        
        return true;
    }
    
    /**
     * Validate permission matrix import data
     */
    public function validatePermissionMatrix(array $matrixData): array
    {
        $errors = [];
        $validRows = [];
        
        foreach ($matrixData as $index => $row) {
            $rowErrors = [];
            
            // Validate required fields
            if (empty($row['role_name'])) {
                $rowErrors[] = 'Role name is required';
            }
            
            if (empty($row['module'])) {
                $rowErrors[] = 'Module is required';
            }
            
            if (empty($row['action'])) {
                $rowErrors[] = 'Action is required';
            }
            
            // Validate permission code format
            if (!empty($row['module']) && !empty($row['action'])) {
                $expectedCode = strtolower($row['module']) . '.' . strtolower($row['action']);
                if ($row['permission_code'] !== $expectedCode) {
                    $rowErrors[] = "Permission code should be: {$expectedCode}";
                }
            }
            
            // Validate boolean allow field
            if (!in_array(strtolower($row['allow']), ['true', 'false'], true)) {
                $rowErrors[] = 'Allow field must be true or false';
            }
            
            if (!empty($rowErrors)) {
                $errors["row_{$index}"] = $rowErrors;
            } else {
                $validRows[] = $row;
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_rows' => $validRows,
            'total_rows' => count($matrixData),
            'valid_count' => count($validRows)
        ];
    }
    
    /**
     * Validate file upload
     */
    public function validateFileUpload(array $fileData, array $allowedTypes = [], int $maxSize = 10485760): array
    {
        $rules = [
            'file' => 'required|file|max:' . ($maxSize / 1024) // Convert to KB
        ];
        
        if (!empty($allowedTypes)) {
            $rules['file'] .= '|mimes:' . implode(',', $allowedTypes);
        }
        
        $validator = Validator::make($fileData, $rules);
        
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        return $validator->validated();
    }
    
    /**
     * Validate JSON structure
     */
    public function validateJsonStructure(string $json, array $requiredKeys = []): array
    {
        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            
            foreach ($requiredKeys as $key) {
                if (!array_key_exists($key, $data)) {
                    throw ValidationException::withMessages([
                        'json' => "Missing required key: {$key}"
                    ]);
                }
            }
            
            return $data;
        } catch (\JsonException $e) {
            throw ValidationException::withMessages([
                'json' => 'Invalid JSON format: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Kiểm tra project overlap
     */
    private function hasProjectOverlap(array $data, ?string $excludeProjectId = null): bool
    {
        // Implementation sẽ query database để kiểm tra overlap
        // Tạm thời return false
        return false;
    }
    
    /**
     * Kiểm tra circular dependency bằng DFS
     */
    private function hasCircularDependency(string $nodeId, string $targetId, array &$visited, array &$recursionStack): bool
    {
        // Check if we're already in the recursion stack (cycle detected)
        if (isset($recursionStack[$nodeId]) && $recursionStack[$nodeId]) {
            return true; // Cycle detected
        }

        // If already visited and not in recursion stack, no cycle from this path
        if (isset($visited[$nodeId])) {
            return false;
        }

        // Mark as visited and add to recursion stack
        $visited[$nodeId] = true;
        $recursionStack[$nodeId] = true;
        
        // Get dependencies của node hiện tại từ database
        $dependencies = $this->getTaskDependencies($nodeId);
        
        foreach ($dependencies as $depId) {
            if ($depId === $targetId) {
                return true; // Found circular dependency
            }
            
            if ($this->hasCircularDependency($depId, $targetId, $visited, $recursionStack)) {
                return true;
            }
        }
        
        // Remove from recursion stack when backtracking
        $recursionStack[$nodeId] = false;
        return false;
    }
    
    /**
     * Lấy dependencies của task từ database
     */
    private function getTaskDependencies(string $taskId): array
    {
        // Implementation sẽ query database
        // Tạm thời return empty array
        return [];
    }
}
