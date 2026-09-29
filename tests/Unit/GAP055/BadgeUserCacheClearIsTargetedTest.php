<?php

namespace Tests\Unit\GAP055;

use App\Models\User;
use App\Services\BadgeService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * GAP-055: clearing one user's badge cache must not flush the shared store
 * (reachable by every role via DELETE /api/badges/cache).
 */
final class BadgeUserCacheClearIsTargetedTest extends TestCase
{
    public function test_clear_user_badge_cache_only_forgets_that_users_badge_keys(): void
    {
        $u1 = new User();
        $u1->id = 'gap055-user-1';
        $u2 = new User();
        $u2->id = 'gap055-user-2';

        Cache::put('badge_tasks_user_gap055-user-1', 3, 600);
        Cache::put('badge_invoices_user_gap055-user-1', 4, 600);
        Cache::put('badge_tasks_user_gap055-user-2', 7, 600);
        Cache::put('tenant:other:gap055-probe', 'kept', 600);
        $limiterKey = 'zena-login|victim@other-tenant.example.com';
        for ($i = 0; $i < 5; $i++) {
            RateLimiter::hit($limiterKey, 60);
        }

        // Same call shape as BadgeController::clearUserBadgeCache(): no argument,
        // user resolved from Auth.
        Auth::setUser($u1);
        app(BadgeService::class)->clearUserBadgeCache();

        $this->assertNull(Cache::get('badge_tasks_user_gap055-user-1'));
        $this->assertNull(Cache::get('badge_invoices_user_gap055-user-1'));
        $this->assertSame(7, Cache::get('badge_tasks_user_gap055-user-2'));
        $this->assertSame('kept', Cache::get('tenant:other:gap055-probe'));
        $this->assertSame(5, RateLimiter::attempts($limiterKey));
    }

    public function test_whole_store_item_badge_clear_no_longer_exists(): void
    {
        $this->assertFalse(method_exists(BadgeService::class, 'clearItemBadgeCache'));
    }
}
