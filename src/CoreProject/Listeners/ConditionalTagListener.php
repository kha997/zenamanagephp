<?php declare(strict_types=1);

namespace Src\CoreProject\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Src\CoreProject\Models\Project;
use Src\CoreProject\Services\ConditionalTagService;
use Illuminate\Support\Facades\Log;

/**
 * Listener xử lý conditional tags khi project thay đổi
 */
class ConditionalTagListener
{
    private ConditionalTagService $conditionalTagService;

    public function __construct(ConditionalTagService $conditionalTagService)
    {
        $this->conditionalTagService = $conditionalTagService;
    }

    /**
     * Xử lý sự kiện ProjectCreated
     *
     * @param array $payload
     * @return void
     */
    public function handleProjectCreated(array $payload): void
    {
        try {
            $projectId = $payload['entityId'];
            
            // Xử lý conditional tags cho tất cả tasks trong project
            $this->conditionalTagService->processProjectConditionalTags($projectId);
            
            Log::info("Conditional tags processed for new project", [
                'project_id' => $projectId
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error processing conditional tags for new project", [
                'project_id' => $payload['entityId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Xử lý sự kiện ProjectTagsUpdated
     *
     * @param array $payload
     * @return void
     */
    public function handleProjectTagsUpdated(array $payload): void
    {
        try {
            $projectId = $payload['entityId'];
            
            // Xử lý lại conditional tags khi project tags thay đổi
            $this->conditionalTagService->processProjectConditionalTags($projectId);
            
            Log::info("Conditional tags reprocessed after project tags update", [
                'project_id' => $projectId
            ]);
            
        } catch (\Exception $e) {
            Log::error("Error reprocessing conditional tags", [
                'project_id' => $payload['entityId'] ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }
}