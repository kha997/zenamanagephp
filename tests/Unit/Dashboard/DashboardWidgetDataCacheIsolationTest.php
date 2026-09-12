<?php

namespace Tests\Unit\Dashboard;

use App\Models\DashboardWidgetDataCache;
use PHPUnit\Framework\TestCase;

class DashboardWidgetDataCacheIsolationTest extends TestCase
{
    public function test_existing_cache_key_separates_user_and_project_contexts(): void
    {
        self::assertNotSame(
            DashboardWidgetDataCache::generateCacheKey('widget-1', 'user-a', 'project-a'),
            DashboardWidgetDataCache::generateCacheKey('widget-1', 'user-b', 'project-a'),
        );
        self::assertNotSame(
            DashboardWidgetDataCache::generateCacheKey('widget-1', 'user-a', 'project-a'),
            DashboardWidgetDataCache::generateCacheKey('widget-1', 'user-a', 'project-b'),
        );
    }
}
