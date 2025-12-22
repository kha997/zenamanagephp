<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Pagination Service
 * 
 * Provides cursor-based pagination for large datasets.
 * Uses indexes (tenant_id, created_at) or (tenant_id, id) for efficient pagination.
 * 
 * Cursor format: base64 encoded JSON with {id, created_at} or {id}
 */
class PaginationService
{
    /**
     * Paginate query using cursor-based pagination
     * 
     * @param Builder $query Eloquent query builder
     * @param int $limit Number of items per page (max 100)
     * @param string|null $cursor Cursor from previous request (base64 encoded)
     * @param string $sortBy Column to sort by (default: 'created_at')
     * @param string $sortDirection 'asc' or 'desc' (default: 'desc')
     * @return array{data: \Illuminate\Database\Eloquent\Collection, next_cursor: string|null, has_more: bool}
     */
    public function paginateCursor(
        Builder $query,
        int $limit = 15,
        ?string $cursor = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): array {
        // Limit max items per page
        $limit = min($limit, 100);
        
        // Decode cursor if provided
        $cursorData = null;
        if ($cursor) {
            try {
                $decoded = base64_decode($cursor, true);
                if ($decoded !== false) {
                    $cursorData = json_decode($decoded, true);
                }
            } catch (\Exception $e) {
                Log::warning('Invalid cursor provided', [
                    'cursor' => $cursor,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Apply cursor filter if provided
        if ($cursorData && isset($cursorData['id']) && isset($cursorData[$sortBy])) {
            if ($sortDirection === 'desc') {
                $query->where(function ($q) use ($sortBy, $cursorData) {
                    $q->where($sortBy, '<', $cursorData[$sortBy])
                      ->orWhere(function ($q2) use ($sortBy, $cursorData) {
                          $q2->where($sortBy, '=', $cursorData[$sortBy])
                             ->where('id', '<', $cursorData['id']);
                      });
                });
            } else {
                $query->where(function ($q) use ($sortBy, $cursorData) {
                    $q->where($sortBy, '>', $cursorData[$sortBy])
                      ->orWhere(function ($q2) use ($sortBy, $cursorData) {
                          $q2->where($sortBy, '=', $cursorData[$sortBy])
                             ->where('id', '>', $cursorData['id']);
                      });
                });
            }
        }

        // Ensure proper ordering for cursor pagination
        // Always include 'id' as secondary sort for deterministic ordering
        $query->orderBy($sortBy, $sortDirection)
              ->orderBy('id', $sortDirection);

        // Fetch one extra item to check if there's more
        $items = $query->limit($limit + 1)->get();
        
        $hasMore = $items->count() > $limit;
        
        if ($hasMore) {
            $items = $items->take($limit);
        }

        // Generate next cursor from last item
        $nextCursor = null;
        if ($hasMore && $items->isNotEmpty()) {
            $lastItem = $items->last();
            $nextCursor = base64_encode(json_encode([
                'id' => $lastItem->id,
                $sortBy => $lastItem->{$sortBy}?->toISOString() ?? $lastItem->{$sortBy},
            ]));
        }

        return [
            'data' => $items,
            'next_cursor' => $nextCursor,
            'has_more' => $hasMore,
        ];
    }

    /**
     * Paginate query using cursor with tenant_id index
     * 
     * Optimized for queries that filter by tenant_id.
     * Uses index (tenant_id, created_at) or (tenant_id, id).
     * 
     * @param Builder $query Eloquent query builder (should already have tenant_id filter)
     * @param string $tenantId Tenant ID (for logging/validation)
     * @param int $limit Number of items per page
     * @param string|null $cursor Cursor from previous request
     * @param string $sortBy Column to sort by
     * @param string $sortDirection 'asc' or 'desc'
     * @return array{data: \Illuminate\Database\Eloquent\Collection, next_cursor: string|null, has_more: bool}
     */
    public function paginateCursorWithTenant(
        Builder $query,
        string $tenantId,
        int $limit = 15,
        ?string $cursor = null,
        string $sortBy = 'created_at',
        string $sortDirection = 'desc'
    ): array {
        // Validate tenant_id is in query
        // Note: Global Scope should already filter by tenant_id, but we log for safety
        Log::debug('Cursor pagination with tenant', [
            'tenant_id' => $tenantId,
            'sort_by' => $sortBy,
            'limit' => $limit,
        ]);

        return $this->paginateCursor($query, $limit, $cursor, $sortBy, $sortDirection);
    }

    /**
     * Validate query uses proper indexes for cursor pagination
     * 
     * Checks that query has tenant_id filter and proper ordering.
     * 
     * @param Builder $query
     * @param string $sortBy
     * @return bool
     */
    public function validateQueryForCursorPagination(Builder $query, string $sortBy = 'created_at'): bool
    {
        $bindings = $query->getBindings();
        $sql = $query->toSql();
        
        // Check if tenant_id is in WHERE clause
        $hasTenantFilter = str_contains($sql, 'tenant_id');
        
        // Check if sort column is in ORDER BY
        $hasSortOrder = str_contains($sql, "ORDER BY") && 
                       (str_contains($sql, $sortBy) || str_contains($sql, '`' . $sortBy . '`'));
        
        if (!$hasTenantFilter) {
            Log::warning('Cursor pagination query missing tenant_id filter', [
                'sql' => $sql,
                'sort_by' => $sortBy,
            ]);
        }
        
        if (!$hasSortOrder) {
            Log::warning('Cursor pagination query missing sort order', [
                'sql' => $sql,
                'sort_by' => $sortBy,
            ]);
        }
        
        return $hasTenantFilter && $hasSortOrder;
    }
}
