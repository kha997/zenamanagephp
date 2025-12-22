<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * MobileAPIService - Service cho mobile-optimized APIs
 */
class MobileAPIService
{
    private array $mobileConfig;

    public function __construct()
    {
        $this->mobileConfig = [
            'enabled' => config('mobile.enabled', true),
            'default_page_size' => config('mobile.default_page_size', 20),
            'max_page_size' => config('mobile.max_page_size', 100),
            'cache_ttl' => config('mobile.cache_ttl', 300), // 5 minutes
            'image_optimization' => config('mobile.image_optimization', true),
            'compression' => config('mobile.compression', true),
            'offline_support' => config('mobile.offline_support', true),
            'push_notifications' => config('mobile.push_notifications', true)
        ];
    }

    /**
     * Optimize data for mobile consumption
     */
    public function optimizeForMobile(array $data, array $options = []): array
    {
        $options = array_merge([
            'compress' => $this->mobileConfig['compression'],
            'optimize_images' => $this->mobileConfig['image_optimization'],
            'include_metadata' => true,
            'exclude_fields' => [],
            'include_relations' => []
        ], $options);

        // Remove unnecessary fields
        if (!empty($options['exclude_fields'])) {
            $data = $this->excludeFields($data, $options['exclude_fields']);
        }

        // Optimize images
        if ($options['optimize_images']) {
            $data = $this->optimizeImages($data);
        }

        // Compress data
        if ($options['compress']) {
            $data = $this->compressData($data);
        }

        // Add mobile metadata
        if ($options['include_metadata']) {
            $data = $this->addMobileMetadata($data);
        }

        return $data;
    }

    /**
     * Get paginated data optimized for mobile
     */
    public function getPaginatedData(callable $callback, int $page = 1, int $perPage = null, array $options = []): array
    {
        $perPage = $perPage ?? $this->mobileConfig['default_page_size'];
        $perPage = min($perPage, $this->mobileConfig['max_page_size']);

        $cacheKey = 'mobile_paginated_' . md5(serialize(func_get_args()));
        
        return Cache::remember($cacheKey, $this->mobileConfig['cache_ttl'], function () use ($callback, $page, $perPage, $options) {
            $result = $callback($page, $perPage, $options);

            $total = $result['total'] ?? 0;

            return [
                'data' => $this->optimizeForMobile($result['data'] ?? [], $options),
                'pagination' => [
                    'current_page' => $page,
                    'per_page' => $perPage,
                    'total' => $total,
                    'last_page' => $perPage > 0 ? ceil($total / $perPage) : 0,
                    'has_more' => $perPage > 0 ? $page < ceil($total / $perPage) : false
                ],
                'mobile_optimized' => true,
                'cached_at' => now()->toISOString()
            ];
        });
    }

    /**
     * Get mobile dashboard data
     */
    public function getMobileDashboard(string $userId): array
    {
        $cacheKey = "mobile_dashboard_{$userId}";
        
        return Cache::remember($cacheKey, $this->mobileConfig['cache_ttl'], function () use ($userId) {
            return [
                'widgets' => [],
                'last_updated' => now()->toISOString(),
                'user_id' => $userId
            ];
        });
    }

    /**
     * Get offline data for mobile
     */
    public function getOfflineData(string $userId, array $types = []): array
    {
        if (!$this->mobileConfig['offline_support']) {
            return ['error' => 'Offline support not enabled'];
        }

        $defaultTypes = ['projects', 'tasks', 'users', 'calendar'];
        $types = empty($types) ? $defaultTypes : $types;
        
        $offlineData = [];
        
        foreach ($types as $type) {
            $offlineData[$type] = $this->getOfflineDataByType($userId, $type);
        }
        
        return [
            'offline_data' => $offlineData,
            'generated_at' => now()->toISOString(),
            'expires_at' => now()->addHours(24)->toISOString(),
            'version' => '1.0'
        ];
    }

    /**
     * Send push notification
     */
    public function sendPushNotification(string $userId, array $notification): array
    {
        if (!$this->mobileConfig['push_notifications']) {
            return ['error' => 'Push notifications not enabled'];
        }

        try {
            // Get user's device tokens
            $deviceTokens = $this->getUserDeviceTokens($userId);
            
            if (empty($deviceTokens)) {
                return ['error' => 'No device tokens found'];
            }

            $results = [];
            foreach ($deviceTokens as $token) {
                $result = $this->sendToDevice($token, $notification);
                $results[] = $result;
            }

            return [
                'success' => true,
                'sent_to' => count($deviceTokens),
                'results' => $results
            ];

        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'user_id' => $userId,
                'notification' => $notification,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get mobile search results
     */
    public function getMobileSearch(string $query, string $userId, array $filters = []): array
    {
        $cacheKey = "mobile_search_" . md5($query . $userId . serialize($filters));
        
        return Cache::remember($cacheKey, 300, function () use ($query, $filters) {
            $results = $this->performMobileSearch($query, $filters);

            return [
                'query' => $query,
                'results' => $results,
                'total_results' => array_sum(array_map('count', $results)),
                'mobile_optimized' => true,
                'searched_at' => now()->toISOString()
            ];
        });
    }

    /**
     * Get mobile analytics
     */
    public function getMobileAnalytics(string $userId, string $period = 'week'): array
    {
        $cacheKey = "mobile_analytics_{$userId}_{$period}";
        
        return Cache::remember($cacheKey, 3600, function () use ($userId, $period) {
            return [
                'user_id' => $userId,
                'period' => $period,
                'metrics' => [],
                'generated_at' => now()->toISOString()
            ];
        });
    }

    /**
     * Perform mobile search (stubbed).
     */
    private function performMobileSearch(string $query, array $filters): array
    {
        return [];
    }

    /**
     * Helper Methods
     */
    private function excludeFields(array $data, array $fields): array
    {
            if (isset($data['data']) && is_array($data['data'])) {
                $data['data'] = array_map(function ($item) use ($fields) {
                    return array_diff_key($item, array_flip($fields));
                }, $data['data']);
            } else {
                $data = array_diff_key($data, array_flip($fields));
            }
        
        return $data;
    }

    private function optimizeImages(array $data): array
    {
        // Optimize image URLs for mobile
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = array_map(function ($item) {
                if (isset($item['avatar']) && $item['avatar']) {
                    $item['avatar'] = $this->optimizeImageUrl($item['avatar']);
                }
                if (isset($item['image']) && $item['image']) {
                    $item['image'] = $this->optimizeImageUrl($item['image']);
                }
                return $item;
            }, $data['data']);
        }
        
        return $data;
    }

    private function optimizeImageUrl(string $url): string
    {
        // Add mobile optimization parameters
        $params = [
            'w' => 300,  // width
            'h' => 300,  // height
            'q' => 80,   // quality
            'f' => 'webp' // format
        ];
        
        $separator = strpos($url, '?') !== false ? '&' : '?';
        return $url . $separator . http_build_query($params);
    }

    private function compressData(array $data): array
    {
        // Remove null values and empty arrays
        $data = array_filter($data, function ($value) {
            return $value !== null && $value !== '';
        });
        
        return $data;
    }

    private function addMobileMetadata(array $data): array
    {
        return array_merge($data, [
            'mobile_optimized' => true,
            'optimized_at' => now()->toISOString(),
            'version' => '1.0'
        ]);
    }

    private function getUserSummary(string $userId): array
    {
        // Implementation for user summary
        return [
            'id' => $userId,
            'name' => 'User Name',
            'avatar' => null,
            'role' => 'user',
            'last_active' => now()->toISOString()
        ];
    }

    private function getProjectsSummary(string $userId): array
    {
        // Implementation for projects summary
        return [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'recent' => []
        ];
    }

    private function getTasksSummary(string $userId): array
    {
        // Implementation for tasks summary
        return [
            'total' => 0,
            'pending' => 0,
            'in_progress' => 0,
            'completed' => 0,
            'overdue' => 0
        ];
    }

    private function getNotificationsSummary(string $userId): array
    {
        // Implementation for notifications summary
        return [
            'unread' => 0,
            'recent' => []
        ];
    }

    private function getCalendarSummary(string $userId): array
    {
        // Implementation for calendar summary
        return [
            'today_events' => [],
            'upcoming_events' => []
        ];
    }

    private function getQuickActions(string $userId): array
    {
        return [
            ['action' => 'create_project', 'label' => 'New Project', 'icon' => 'plus'],
            ['action' => 'create_task', 'label' => 'New Task', 'icon' => 'check'],
            ['action' => 'upload_document', 'label' => 'Upload File', 'icon' => 'upload'],
            ['action' => 'view_calendar', 'label' => 'Calendar', 'icon' => 'calendar']
        ];
    }

    private function getMobileStats(string $userId): array
    {
        return [
            'projects_created' => 0,
            'tasks_completed' => 0,
            'documents_uploaded' => 0,
            'hours_logged' => 0
        ];
    }

    private function getOfflineDataByType(string $userId, string $type): array
    {
        // Implementation for offline data by type
        return [];
    }

    private function getUserDeviceTokens(string $userId): array
    {
        // Implementation for getting user device tokens
        return [];
    }

    private function sendToDevice(string $token, array $notification): array
    {
        // Implementation for sending to device
        return ['success' => true, 'token' => $token];
    }

    private function searchProjects(string $query, string $userId, array $filters): array
    {
        // Implementation for project search
        return [];
    }

    private function searchTasks(string $query, string $userId, array $filters): array
    {
        // Implementation for task search
        return [];
    }

    private function searchUsers(string $query, string $userId, array $filters): array
    {
        // Implementation for user search
        return [];
    }

    private function searchDocuments(string $query, string $userId, array $filters): array
    {
        // Implementation for document search
        return [];
    }

    private function getUserActivity(string $userId, string $period): array
    {
        // Implementation for user activity
        return [];
    }

    private function getProjectProgress(string $userId, string $period): array
    {
        // Implementation for project progress
        return [];
    }

    private function getTaskCompletion(string $userId, string $period): array
    {
        // Implementation for task completion
        return [];
    }

    private function getTimeTracking(string $userId, string $period): array
    {
        // Implementation for time tracking
        return [];
    }

    private function getProductivityScore(string $userId, string $period): int
    {
        // Implementation for productivity score
        return 85;
    }
}
