<?php declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\User;
use App\Services\DatabaseOptimizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseOptimizationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyze_query_performance_is_restricted_to_super_admin_or_cli(): void
    {
        $service = new DatabaseOptimizationService();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query analysis is restricted');

        $service->analyzeQueryPerformance('SELECT 1');
    }

    public function test_super_admin_can_run_query_analysis(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($user);

        DB::shouldReceive('select')
            ->once()
            ->with('SELECT 1')
            ->andReturn([(object)['id' => 1]]);

        $service = new DatabaseOptimizationService();
        $result = $service->analyzeQueryPerformance('SELECT 1');

        $this->assertEquals(1, $result['result_count']);
        $this->assertArrayHasKey('execution_time', $result);
        $this->assertArrayHasKey('is_slow', $result);
    }
}
