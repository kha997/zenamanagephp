<?php declare(strict_types=1);

namespace Tests\Unit\Listeners;

use App\Events\ProjectHealthPortfolioGenerated;
use App\Listeners\LogProjectHealthPortfolioGeneration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for LogProjectHealthPortfolioGeneration listener
 * 
 * Round 83: Project Health Observability & Perf Baseline
 * 
 * Tests that the listener correctly logs events based on configuration.
 * 
 * @group unit
 * @group listeners
 * @group monitoring
 */
class LogProjectHealthPortfolioGenerationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that listener logs when monitoring is enabled
     */
    public function test_listener_logs_when_monitoring_enabled(): void
    {
        // Set monitoring enabled with sample_rate = 1.0 (always log)
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.log_channel', null);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', false);

        // Create event
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 5,
            durationMs: 123.45,
        );

        // Mock Log facade
        Log::shouldReceive('info')
            ->once()
            ->with(
                'project_health.portfolio_generated',
                [
                    'tenant_id' => 1,
                    'projects' => 5,
                    'duration_ms' => 123.45,
                ]
            );

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener does not log when monitoring is disabled
     */
    public function test_listener_does_not_log_when_monitoring_disabled(): void
    {
        // Set monitoring disabled
        Config::set('reports.project_health.monitoring_enabled', false);

        // Create event
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 5,
            durationMs: 123.45,
        );

        // Mock Log facade - should not be called
        Log::shouldReceive('info')->never();
        Log::shouldReceive('channel')->never();

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener uses custom log channel when configured
     */
    public function test_listener_uses_custom_log_channel(): void
    {
        // Set monitoring enabled with custom channel
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.log_channel', 'custom');
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', false);

        // Create event
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 5,
            durationMs: 123.45,
        );

        // Mock Log facade with channel
        Log::shouldReceive('channel')
            ->once()
            ->with('custom')
            ->andReturnSelf();

        Log::shouldReceive('info')
            ->once()
            ->with(
                'project_health.portfolio_generated',
                [
                    'tenant_id' => 1,
                    'projects' => 5,
                    'duration_ms' => 123.45,
                ]
            );

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener uses default channel when log_channel is null
     */
    public function test_listener_uses_default_channel_when_log_channel_is_null(): void
    {
        // Set monitoring enabled with null channel (default)
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.log_channel', null);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', false);

        // Create event
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 5,
            durationMs: 123.45,
        );

        // Mock Log facade - should use default, not channel()
        Log::shouldReceive('info')
            ->once()
            ->with(
                'project_health.portfolio_generated',
                [
                    'tenant_id' => 1,
                    'projects' => 5,
                    'duration_ms' => 123.45,
                ]
            );

        Log::shouldNotReceive('channel');

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener does not log when projectCount is 0 and log_when_empty is false
     */
    public function test_listener_does_not_log_when_empty_and_log_when_empty_is_false(): void
    {
        // Set monitoring enabled but log_when_empty = false
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', false);

        // Create event with zero projects
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 0,
            durationMs: 123.45,
        );

        // Mock Log facade - should not be called
        Log::shouldReceive('info')->never();
        Log::shouldReceive('channel')->never();

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener logs when projectCount is 0 and log_when_empty is true
     */
    public function test_listener_logs_when_empty_and_log_when_empty_is_true(): void
    {
        // Set monitoring enabled with log_when_empty = true
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.log_channel', null);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', true);

        // Create event with zero projects
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 0,
            durationMs: 123.45,
        );

        // Mock Log facade - should be called
        Log::shouldReceive('info')
            ->once()
            ->with(
                'project_health.portfolio_generated',
                [
                    'tenant_id' => 1,
                    'projects' => 0,
                    'duration_ms' => 123.45,
                ]
            );

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener does not log when sample_rate is 0.0
     */
    public function test_listener_does_not_log_when_sample_rate_is_zero(): void
    {
        // Set monitoring enabled but sample_rate = 0.0
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.sample_rate', 0.0);
        Config::set('reports.project_health.log_when_empty', true);

        // Create event with projects
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 5,
            durationMs: 123.45,
        );

        // Mock Log facade - should not be called
        Log::shouldReceive('info')->never();
        Log::shouldReceive('channel')->never();

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }

    /**
     * Test that listener logs when sample_rate is 1.0 (always log)
     */
    public function test_listener_logs_when_sample_rate_is_one(): void
    {
        // Set monitoring enabled with sample_rate = 1.0
        Config::set('reports.project_health.monitoring_enabled', true);
        Config::set('reports.project_health.log_channel', null);
        Config::set('reports.project_health.sample_rate', 1.0);
        Config::set('reports.project_health.log_when_empty', false);

        // Create event
        $event = new ProjectHealthPortfolioGenerated(
            tenantId: 1,
            projectCount: 5,
            durationMs: 123.45,
        );

        // Mock Log facade - should be called
        Log::shouldReceive('info')
            ->once()
            ->with(
                'project_health.portfolio_generated',
                [
                    'tenant_id' => 1,
                    'projects' => 5,
                    'duration_ms' => 123.45,
                ]
            );

        // Create and handle listener
        $listener = new LogProjectHealthPortfolioGeneration();
        $listener->handle($event);
    }
}

