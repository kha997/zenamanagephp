<?php declare(strict_types=1);

namespace App\Services;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

class ApiResponseOptimizationService
{
    /**
     * Optimize API response with compression and caching
     */
    public function optimizeResponse(
        Request $request,
        array $data,
        int $statusCode = 200,
        array $options = []
    ): JsonResponse {
        // Apply data transformation
        $optimizedData = $this->transformData($data, $options);
        
        // Apply pagination optimization
        if (isset($options['paginate'])) {
            $optimizedData = $this->optimizePagination($optimizedData, $options['paginate']);
        }
        
        // Apply field selection
        if (isset($options['fields'])) {
            $optimizedData = $this->selectFields($optimizedData, $options['fields']);
        }
        
        // Create response
        $response = ApiResponse::success($optimizedData, $statusCode)->toResponse($request);
        
        // Apply compression
        if ($this->shouldCompress($request, $optimizedData)) {
            $response = $this->compressResponse($response);
        }
        
        // Add performance headers
        $this->addPerformanceHeaders($response, $optimizedData);
        
        return $response;
    }
    
    /**
     * Transform data for optimization
     */
    private function transformData(array $data, array $options): array
    {
        // Remove null values if requested
        if (isset($options['remove_nulls']) && $options['remove_nulls']) {
            $data = $this->removeNullValues($data);
        }
        
        // Convert timestamps to ISO format
        if (isset($options['format_timestamps']) && $options['format_timestamps']) {
            $data = $this->formatTimestamps($data);
        }
        
        // Add metadata if requested
        if (isset($options['include_metadata']) && $options['include_metadata']) {
            $data = $this->addMetadata($data, $options);
        }
        
        return $data;
    }
    
    /**
     * Optimize pagination data
     */
    private function optimizePagination(array $data, array $paginationOptions): array
    {
        if (!isset($data['data']) || !is_array($data['data'])) {
            return $data;
        }
        
        $items = $data['data'];
        $perPage = $paginationOptions['per_page'] ?? 15;
        $currentPage = $paginationOptions['current_page'] ?? 1;
        
        // Calculate pagination info
        $total = count($items);
        $totalPages = ceil($total / $perPage);
        $offset = ($currentPage - 1) * $perPage;
        
        // Get current page items
        $currentPageItems = array_slice($items, $offset, $perPage);
        
        return [
            'data' => $currentPageItems,
            'pagination' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'has_next' => $currentPage < $totalPages,
                'has_prev' => $currentPage > 1,
            ],
        ];
    }
    
    /**
     * Select specific fields from data
     */
    private function selectFields(array $data, array $fields): array
    {
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = array_map(function ($item) use ($fields) {
                return array_intersect_key($item, array_flip($fields));
            }, $data['data']);
        }
        
        return $data;
    }
    
    /**
     * Remove null values from data
     */
    private function removeNullValues(array $data): array
    {
        return array_filter($data, function ($value) {
            if (is_array($value)) {
                return !empty($value);
            }
            return $value !== null;
        });
    }
    
    /**
     * Format timestamps to ISO format
     */
    private function formatTimestamps(array $data): array
    {
        $timestampFields = ['created_at', 'updated_at', 'deleted_at', 'start_date', 'end_date', 'due_date'];
        
        if (isset($data['data']) && is_array($data['data'])) {
            $data['data'] = array_map(function ($item) use ($timestampFields) {
                foreach ($timestampFields as $field) {
                    if (isset($item[$field]) && $item[$field]) {
                        $item[$field] = is_string($item[$field]) 
                            ? $item[$field] 
                            : $item[$field]->toISOString();
                    }
                }
                return $item;
            }, $data['data']);
        }
        
        return $data;
    }
    
    /**
     * Add metadata to response
     */
    private function addMetadata(array $data, array $options): array
    {
        $metadata = [
            'generated_at' => now()->toISOString(),
            'version' => '1.0',
        ];
        
        if (isset($options['include_query_info']) && $options['include_query_info']) {
            $metadata['query_info'] = [
                'execution_time' => $options['execution_time'] ?? 0,
                'total_queries' => $options['total_queries'] ?? 0,
                'cache_hit' => $options['cache_hit'] ?? false,
            ];
        }
        
        $data['metadata'] = $metadata;
        
        return $data;
    }
    
    /**
     * Determine if response should be compressed
     */
    private function shouldCompress(Request $request, array $data): bool
    {
        // Check if client accepts gzip
        $acceptEncoding = $request->header('Accept-Encoding', '');
        if (!str_contains($acceptEncoding, 'gzip')) {
            return false;
        }
        
        // Check data size (compress if > 1KB)
        $dataSize = strlen(json_encode($data));
        if ($dataSize < 1024) {
            return false;
        }
        
        return true;
    }
    
    /**
     * Compress response content
     */
    private function compressResponse(JsonResponse $response): JsonResponse
    {
        $content = $response->getContent();
        $compressed = gzencode($content, 6); // Compression level 6
        
        if ($compressed !== false) {
            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', strlen($compressed));
        }
        
        return $response;
    }
    
    /**
     * Add performance headers to response
     */
    private function addPerformanceHeaders(JsonResponse $response, array $data): void
    {
        $dataSize = strlen(json_encode($data));
        
        $response->headers->set('X-Response-Size', $dataSize);
        $response->headers->set('X-Response-Size-Human', $this->formatBytes($dataSize));
        $response->headers->set('X-Cache-Status', 'MISS'); // Will be updated by cache middleware
        $response->headers->set('X-Compression', 'none'); // Will be updated by compression
        
        // Add CORS headers for performance monitoring
        $response->headers->set('Access-Control-Expose-Headers', 
            'X-Response-Size, X-Response-Size-Human, X-Cache-Status, X-Compression');
    }
    
    /**
     * Format bytes to human readable format
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Cache API response
     */
    public function cacheResponse(
        string $endpoint,
        array $params,
        array $data,
        int $ttl = 300
    ): void {
        $cacheKey = $this->generateCacheKey($endpoint, $params);
        
        Cache::put($cacheKey, $data, $ttl);
    }
    
    /**
     * Get cached API response
     */
    public function getCachedResponse(string $endpoint, array $params): ?array
    {
        $cacheKey = $this->generateCacheKey($endpoint, $params);
        
        return Cache::get($cacheKey);
    }
    
    /**
     * Generate cache key for API response
     */
    private function generateCacheKey(string $endpoint, array $params): string
    {
        $paramHash = md5(serialize($params));
        $tenantId = app()->has('tenant') ? app('tenant')->id : 'global';
        
        return "api_response:{$endpoint}:{$tenantId}:{$paramHash}";
    }
    
    /**
     * Optimize large dataset responses
     */
    public function optimizeLargeDataset(
        Request $request,
        array $data,
        array $options = []
    ): JsonResponse {
        $chunkSize = $options['chunk_size'] ?? 100;
        $maxSize = $options['max_size'] ?? 1000;
        
        // If data is too large, implement streaming or chunking
        if (count($data) > $maxSize) {
            return $this->streamLargeDataset($request, $data, $chunkSize);
        }
        
        // Otherwise, optimize normally
        return $this->optimizeResponse($request, $data, 200, $options);
    }
    
    /**
     * Stream large dataset
     */
    private function streamLargeDataset(
        Request $request,
        array $data,
        int $chunkSize
    ): JsonResponse {
        // For now, just limit the data size
        $limitedData = array_slice($data, 0, $chunkSize);
        
        $response = $this->optimizeResponse($request, $limitedData, 200, [
            'include_metadata' => true,
            'metadata' => [
                'truncated' => true,
                'total_count' => count($data),
                'returned_count' => count($limitedData),
                'message' => 'Response truncated due to size. Use pagination for full dataset.',
            ],
        ]);
        
        return $response;
    }
}
