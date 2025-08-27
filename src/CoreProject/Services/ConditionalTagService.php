<?php declare(strict_types=1);

namespace Src\CoreProject\Services;

use Src\CoreProject\Models\Project;
use Src\CoreProject\Models\Task;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Service xử lý logic cho Conditional Tags
 * 
 * Conditional tags được sử dụng để điều khiển visibility của tasks
 * dựa trên các điều kiện cụ thể của project hoặc business logic
 */
class ConditionalTagService
{
    /**
     * Cache key prefix cho conditional tags
     */
    private const CACHE_PREFIX = 'conditional_tag:';
    
    /**
     * Cache TTL (seconds) - 1 hour
     */
    private const CACHE_TTL = 3600;
    
    /**
     * Các tag được định nghĩa sẵn trong hệ thống
     */
    private const PREDEFINED_TAGS = [
        'design_phase' => 'Giai đoạn thiết kế',
        'construction_phase' => 'Giai đoạn thi công',
        'qc_phase' => 'Giai đoạn kiểm tra chất lượng',
        'inspection_phase' => 'Giai đoạn nghiệm thu',
        'has_basement' => 'Có tầng hầm',
        'has_elevator' => 'Có thang máy',
        'luxury_finish' => 'Hoàn thiện cao cấp',
        'standard_finish' => 'Hoàn thiện tiêu chuẩn',
        'budget_project' => 'Dự án tiết kiệm',
        'premium_project' => 'Dự án cao cấp',
        'residential' => 'Nhà ở',
        'commercial' => 'Thương mại',
        'industrial' => 'Công nghiệp',
        'renovation' => 'Cải tạo',
        'new_construction' => 'Xây mới'
    ];
    
    /**
     * Kiểm tra tag có active không
     * 
     * @param string $tag Tên tag cần kiểm tra
     * @param int $projectId ID của project
     * @return bool True nếu tag active, False nếu không
     */
    public function isTagActive(string $tag, int $projectId): bool
    {
        // Kiểm tra cache trước
        $cacheKey = self::CACHE_PREFIX . $projectId . ':' . $tag;
        $cached = Cache::get($cacheKey);
        
        if ($cached !== null) {
            return (bool) $cached;
        }
        
        // Tính toán trạng thái tag
        $isActive = $this->calculateTagStatus($tag, $projectId);
        
        // Cache kết quả
        Cache::put($cacheKey, $isActive, self::CACHE_TTL);
        
        return $isActive;
    }
    
    /**
     * Lấy danh sách tất cả tags active cho project
     * 
     * @param int $projectId ID của project
     * @return array Mảng các tag names đang active
     */
    public function getActiveTagsForProject(int $projectId): array
    {
        $project = Project::findOrFail($projectId);
        $activeTags = [];
        
        // Kiểm tra từng predefined tag
        foreach (array_keys(self::PREDEFINED_TAGS) as $tag) {
            if ($this->isTagActive($tag, $projectId)) {
                $activeTags[] = $tag;
            }
        }
        
        // Thêm custom tags từ project settings nếu có
        $customTags = $this->getCustomTagsForProject($projectId);
        $activeTags = array_merge($activeTags, $customTags);
        
        return array_unique($activeTags);
    }
    
    /**
     * Cập nhật visibility của tất cả tasks có conditional tags trong project
     * 
     * @param int $projectId ID của project
     * @return int Số lượng tasks đã được cập nhật
     */
    public function updateTaskVisibilityForProject(int $projectId): int
    {
        $tasks = Task::forProject($projectId)
                    ->whereNotNull('conditional_tag')
                    ->get();
        
        $updatedCount = 0;
        
        foreach ($tasks as $task) {
            $shouldBeVisible = $this->isTagActive($task->conditional_tag, $projectId);
            $currentlyHidden = $task->is_hidden;
            
            // Cập nhật nếu trạng thái thay đổi
            if ($shouldBeVisible && $currentlyHidden) {
                $task->update(['is_hidden' => false]);
                $updatedCount++;
                
                Log::info("Task {$task->ulid} được hiển thị do tag '{$task->conditional_tag}' active");
            } elseif (!$shouldBeVisible && !$currentlyHidden) {
                $task->update(['is_hidden' => true]);
                $updatedCount++;
                
                Log::info("Task {$task->ulid} được ẩn do tag '{$task->conditional_tag}' không active");
            }
        }
        
        return $updatedCount;
    }
    
    /**
     * Xóa cache cho project
     * 
     * @param int $projectId ID của project
     * @return void
     */
    public function clearCacheForProject(int $projectId): void
    {
        $pattern = self::CACHE_PREFIX . $projectId . ':*';
        
        // Laravel không có built-in cache pattern deletion
        // Nên ta sẽ clear từng tag đã biết
        foreach (array_keys(self::PREDEFINED_TAGS) as $tag) {
            $cacheKey = self::CACHE_PREFIX . $projectId . ':' . $tag;
            Cache::forget($cacheKey);
        }
        
        Log::info("Cleared conditional tag cache for project {$projectId}");
    }
    
    /**
     * Lấy danh sách tất cả predefined tags với mô tả
     * 
     * @return array Mảng associative [tag_name => description]
     */
    public function getPredefinedTags(): array
    {
        return self::PREDEFINED_TAGS;
    }
    
    /**
     * Kiểm tra tag có hợp lệ không
     * 
     * @param string $tag Tên tag
     * @return bool True nếu tag hợp lệ
     */
    public function isValidTag(string $tag): bool
    {
        // Tag hợp lệ nếu:
        // 1. Là predefined tag
        // 2. Hoặc match pattern cho custom tags
        return isset(self::PREDEFINED_TAGS[$tag]) || 
               $this->isValidCustomTag($tag);
    }
    
    /**
     * Tính toán trạng thái thực tế của tag cho project
     * 
     * @param string $tag Tên tag
     * @param int $projectId ID của project
     * @return bool True nếu tag active
     */
    private function calculateTagStatus(string $tag, int $projectId): bool
    {
        $project = Project::findOrFail($projectId);
        
        // Logic kiểm tra dựa trên từng loại tag
        switch ($tag) {
            // Phase-based tags - kiểm tra dựa trên project status
            case 'design_phase':
                return in_array($project->status, ['planning', 'design', 'in_progress']);
                
            case 'construction_phase':
                return in_array($project->status, ['in_progress', 'construction']);
                
            case 'qc_phase':
                return in_array($project->status, ['in_progress', 'qc', 'testing']);
                
            case 'inspection_phase':
                return in_array($project->status, ['qc', 'testing', 'completed']);
            
            // Feature-based tags - kiểm tra project metadata hoặc components
            case 'has_basement':
                return $this->projectHasComponent($projectId, 'basement');
                
            case 'has_elevator':
                return $this->projectHasComponent($projectId, 'elevator');
            
            // Budget-based tags - kiểm tra dựa trên planned cost
            case 'budget_project':
                return $this->getProjectPlannedCost($projectId) < 1000000; // < 1M
                
            case 'premium_project':
                return $this->getProjectPlannedCost($projectId) > 5000000; // > 5M
            
            // Finish-based tags - có thể dựa trên project tags hoặc components
            case 'luxury_finish':
                return $this->projectHasTag($projectId, 'luxury') || 
                       $this->getProjectPlannedCost($projectId) > 3000000;
                       
            case 'standard_finish':
                return !$this->projectHasTag($projectId, 'luxury') && 
                       $this->getProjectPlannedCost($projectId) <= 3000000;
            
            // Project type tags - có thể dựa trên project category hoặc tags
            case 'residential':
                return $this->projectHasTag($projectId, 'residential');
                
            case 'commercial':
                return $this->projectHasTag($projectId, 'commercial');
                
            case 'industrial':
                return $this->projectHasTag($projectId, 'industrial');
                
            case 'renovation':
                return $this->projectHasTag($projectId, 'renovation');
                
            case 'new_construction':
                return $this->projectHasTag($projectId, 'new_construction');
            
            // Custom tags - kiểm tra trong project settings
            default:
                return $this->isCustomTagActive($tag, $projectId);
        }
    }
    
    /**
     * Kiểm tra project có component cụ thể không
     * 
     * @param int $projectId ID của project
     * @param string $componentName Tên component
     * @return bool True nếu có component
     */
    private function projectHasComponent(int $projectId, string $componentName): bool
    {
        return \Src\CoreProject\Models\Component::forProject($projectId)
                    ->where('name', 'LIKE', '%' . $componentName . '%')
                    ->exists();
    }
    
    /**
     * Lấy tổng planned cost của project
     * 
     * @param int $projectId ID của project
     * @return float Tổng planned cost
     */
    private function getProjectPlannedCost(int $projectId): float
    {
        return \Src\CoreProject\Models\Component::forProject($projectId)
                    ->whereNull('parent_component_id') // Chỉ root components
                    ->sum('planned_cost');
    }
    
    /**
     * Kiểm tra project có tag cụ thể không
     * 
     * @param int $projectId ID của project
     * @param string $tagName Tên tag
     * @return bool True nếu có tag
     */
    private function projectHasTag(int $projectId, string $tagName): bool
    {
        // Giả định project có trường tags (JSON)
        // Nếu chưa có, có thể implement qua project_tags table
        $project = Project::find($projectId);
        
        if (!$project || !isset($project->tags)) {
            return false;
        }
        
        $tags = is_array($project->tags) ? $project->tags : json_decode($project->tags, true);
        
        return in_array($tagName, $tags ?? []);
    }
    
    /**
     * Kiểm tra custom tag có active không
     * 
     * @param string $tag Tên tag
     * @param int $projectId ID của project
     * @return bool True nếu active
     */
    private function isCustomTagActive(string $tag, int $projectId): bool
    {
        // TODO: Implement custom tag logic
        // Có thể lưu trong project_settings table hoặc project metadata
        
        // Hiện tại return false cho custom tags chưa được định nghĩa
        return false;
    }
    
    /**
     * Lấy danh sách custom tags active cho project
     * 
     * @param int $projectId ID của project
     * @return array Mảng custom tag names
     */
    private function getCustomTagsForProject(int $projectId): array
    {
        // TODO: Implement custom tags retrieval
        // Có thể từ project_settings hoặc project metadata
        
        return [];
    }
    
    /**
     * Kiểm tra custom tag có hợp lệ không
     * 
     * @param string $tag Tên tag
     * @return bool True nếu hợp lệ
     */
    private function isValidCustomTag(string $tag): bool
    {
        // Custom tag rules:
        // - Chỉ chứa chữ cái, số, underscore, dash
        // - Độ dài từ 3-50 ký tự
        // - Không bắt đầu bằng số
        
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{2,49}$/', $tag) === 1;
    }
}