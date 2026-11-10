<?php

declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Models\Project;
use App\Models\ProjectActivity;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-119 (Gate 2 A-1, E-1, P-1) — dead code removed, guarded against coming back.
 *
 * - A-1: the four api/analytics routes of Api\ProjectAnalyticsController always returned HTTP 500 (their controller
 *   methods never existed) and ProjectAnalyticsService was never called (and failed on Project::isOverdue()). Project
 *   health now lives in ProjectHealthReadService (GAP-103). api/analytics/dashboard belongs to AnalyticsController and stays.
 * - E-1: three project events were never dispatched (and lacked their imports), LogProjectActivity was never registered,
 *   RealTimeNotificationService had no caller; the ProjectActivity helpers only they used are gone too.
 * - P-1: Project::calculateProgress() (progress by milestone count) had no caller; recalculateProgress() stays.
 *
 * See docs/audits/2026-11-10-gap-119-project-analytics-cleanup-evidence.md.
 */
class DeadProjectAnalyticsAndEventCodeRemovedTest extends TestCase
{
    public function test_dead_project_analytics_routes_are_removed_and_dashboard_route_remains(): void
    {
        // The four removed routes (projects/{project}, filter-options, search-suggestions, search) all targeted this
        // controller; no route may target it again.
        $dashboardFound = false;
        foreach (Route::getRoutes() as $route) {
            $action = (string) $route->getActionName();
            $this->assertStringNotContainsString('ProjectAnalyticsController', $action, "Route {$route->uri()} must not point to the removed ProjectAnalyticsController.");
            if ($route->uri() === 'api/analytics/dashboard') {
                $dashboardFound = true;
            }
        }
        $this->assertTrue($dashboardFound, 'The analytics dashboard route of AnalyticsController must stay.');
    }

    public function test_dead_classes_are_removed(): void
    {
        foreach ([
            'App\\Http\\Controllers\\Api\\ProjectAnalyticsController',
            'App\\Services\\ProjectAnalyticsService',
            'App\\Events\\ProjectMilestoneCompleted',
            'App\\Events\\ProjectTaskUpdated',
            'App\\Events\\ProjectTeamMemberJoined',
            'App\\Listeners\\LogProjectActivity',
            'App\\Services\\RealTimeNotificationService',
        ] as $class) {
            $this->assertFalse(class_exists($class), "{$class} must stay removed (GAP-119).");
        }
    }

    public function test_project_activity_and_project_keep_their_live_methods_only(): void
    {
        foreach (['logMilestoneCompleted', 'logTaskUpdated', 'logTeamMemberJoined'] as $method) {
            $this->assertFalse(method_exists(ProjectActivity::class, $method), "ProjectActivity::{$method} must stay removed (GAP-119 E-1).");
        }
        $this->assertTrue(method_exists(ProjectActivity::class, 'log'));
        $this->assertTrue(method_exists(ProjectActivity::class, 'logProjectCreated'));

        $this->assertFalse(method_exists(Project::class, 'calculateProgress'), 'Project::calculateProgress must stay removed (GAP-119 P-1).');
        $this->assertTrue(method_exists(Project::class, 'recalculateProgress'));
    }
}
