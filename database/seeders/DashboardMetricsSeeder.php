<?php

namespace Database\Seeders;

use App\Models\DashboardMetric;
use App\Models\DashboardMetricValue;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DashboardMetricsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating dashboard metrics...');

        $tenants = Tenant::all();

        foreach ($tenants as $tenant) {
            $this->createDashboardMetrics($tenant);
        }

        $this->command->info('Dashboard metrics created successfully!');
    }

    protected function createDashboardMetrics(Tenant $tenant): void
    {
        // Page Load Time Metric
        $pageLoadMetric = DashboardMetric::create([
            'name' => 'Page Load Time',
            'description' => 'Average page load time in milliseconds',
            'unit' => 'ms',
            'category' => 'performance',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'config' => json_encode([
                'threshold' => 500,
                'warning_threshold' => 400,
                'critical_threshold' => 600,
            ]),
        ]);

        // API Response Time Metric
        $apiResponseMetric = DashboardMetric::create([
            'name' => 'API Response Time',
            'description' => 'Average API response time in milliseconds',
            'unit' => 'ms',
            'category' => 'performance',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'config' => json_encode([
                'threshold' => 300,
                'warning_threshold' => 200,
                'critical_threshold' => 400,
            ]),
        ]);

        // Memory Usage Metric
        $memoryMetric = DashboardMetric::create([
            'name' => 'Memory Usage',
            'description' => 'Current memory usage in megabytes',
            'unit' => 'MB',
            'category' => 'system',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'config' => json_encode([
                'threshold' => 100,
                'warning_threshold' => 80,
                'critical_threshold' => 120,
            ]),
        ]);

        // Database Query Time Metric
        $dbQueryMetric = DashboardMetric::create([
            'name' => 'Database Query Time',
            'description' => 'Average database query time in milliseconds',
            'unit' => 'ms',
            'category' => 'database',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'config' => json_encode([
                'threshold' => 100,
                'warning_threshold' => 50,
                'critical_threshold' => 150,
            ]),
        ]);

        // Cache Hit Rate Metric
        $cacheHitMetric = DashboardMetric::create([
            'name' => 'Cache Hit Rate',
            'description' => 'Cache hit rate percentage',
            'unit' => '%',
            'category' => 'cache',
            'tenant_id' => $tenant->id,
            'is_active' => true,
            'config' => json_encode([
                'threshold' => 80,
                'warning_threshold' => 70,
                'critical_threshold' => 60,
            ]),
        ]);

        // Create sample metric values
        $this->createSampleMetricValues($pageLoadMetric, $tenant);
        $this->createSampleMetricValues($apiResponseMetric, $tenant);
        $this->createSampleMetricValues($memoryMetric, $tenant);
        $this->createSampleMetricValues($dbQueryMetric, $tenant);
        $this->createSampleMetricValues($cacheHitMetric, $tenant);

        $this->command->info("Created 5 dashboard metrics for tenant: {$tenant->name}");
    }

    protected function createSampleMetricValues(DashboardMetric $metric, Tenant $tenant): void
    {
        $baseValue = $this->getBaseValueForMetric($metric->name);
        
        // Create 10 sample values over the last hour
        for ($i = 0; $i < 10; $i++) {
            $value = $baseValue + rand(-20, 20); // Add some variation
            $value = max(0, $value); // Ensure non-negative values
            
            DashboardMetricValue::create([
                'metric_id' => $metric->id,
                'tenant_id' => $tenant->id,
                'value' => $value,
                'metadata' => json_encode([
                    'source' => 'sample_data',
                    'timestamp' => now()->subMinutes($i * 6)->toISOString(),
                ]),
                'recorded_at' => now()->subMinutes($i * 6),
            ]);
        }
    }

    protected function getBaseValueForMetric(string $metricName): float
    {
        return match ($metricName) {
            'Page Load Time' => 749.0, // From UAT results
            'API Response Time' => 0.29, // From UAT results
            'Memory Usage' => 71.5, // From UAT results
            'Database Query Time' => 27.22, // From UAT results
            'Cache Hit Rate' => 85.5, // Mock value
            default => 0.0,
        };
    }
}
