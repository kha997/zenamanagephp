<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class SecurityHeadersTest extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'security:test-headers 
                            {--url=http://localhost:8000 : Base URL to test}
                            {--detailed : Show detailed header analysis}
                            {--all : Test all endpoints}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test security headers implementation';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $baseUrl = $this->option('url');
        $detailed = $this->option('detailed');
        $testAll = $this->option('all');

        $this->info('Security Headers Test');
        $this->info('=====================');
        $this->newLine();

        // Test endpoints
        $endpoints = [
            'Home Page' => '/',
            'Login Page' => '/login',
            'Admin Dashboard' => '/admin',
            'App Dashboard' => '/app/dashboard',
            'API Health Check' => '/api/v1/public/health',
            'API Docs' => '/api-docs',
        ];

        if ($testAll) {
            $endpoints = array_merge($endpoints, [
                'Users Page' => '/users',
                'Projects Page' => '/projects',
                'Tasks Page' => '/tasks',
                'Documents Page' => '/documents',
                'API Admin Health' => '/api/v1/admin/health/comprehensive',
            ]);
        }

        $results = [];
        $securityHeaders = $this->getSecurityHeadersList();

        foreach ($endpoints as $name => $endpoint) {
            $this->info("Testing: {$name}");
            $this->line("URL: {$baseUrl}{$endpoint}");

            try {
                $response = Http::timeout(10)->get($baseUrl . $endpoint);
                $headers = $response->headers();
                $statusCode = $response->status();

                $result = [
                    'name' => $name,
                    'url' => $endpoint,
                    'status' => $statusCode,
                    'headers' => $headers,
                    'security_score' => $this->calculateSecurityScore($headers, $securityHeaders),
                ];

                $results[] = $result;

                $this->line("Status: {$statusCode}");
                $this->line("Security Score: {$result['security_score']}/100");

                if ($detailed) {
                    $this->showDetailedHeaders($headers, $securityHeaders);
                }

            } catch (\Exception $e) {
                $this->error("Error: " . $e->getMessage());
                $results[] = [
                    'name' => $name,
                    'url' => $endpoint,
                    'status' => 'ERROR',
                    'error' => $e->getMessage(),
                    'security_score' => 0,
                ];
            }

            $this->newLine();
        }

        // Summary
        $this->showSummary($results, $securityHeaders);
    }

    /**
     * Get list of security headers to check
     */
    protected function getSecurityHeadersList(): array
    {
        return [
            'Strict-Transport-Security' => 20,
            'Content-Security-Policy' => 20,
            'X-Content-Type-Options' => 10,
            'X-Frame-Options' => 10,
            'X-XSS-Protection' => 10,
            'Referrer-Policy' => 10,
            'Permissions-Policy' => 10,
            'Cross-Origin-Embedder-Policy' => 5,
            'Cross-Origin-Opener-Policy' => 5,
            'Cross-Origin-Resource-Policy' => 5,
            'X-Permitted-Cross-Domain-Policies' => 5,
            'X-Download-Options' => 5,
            'X-DNS-Prefetch-Control' => 5,
        ];
    }

    /**
     * Calculate security score based on headers
     */
    protected function calculateSecurityScore(array $headers, array $securityHeaders): int
    {
        $score = 0;
        $totalWeight = array_sum($securityHeaders);

        foreach ($securityHeaders as $header => $weight) {
            if (isset($headers[$header]) && !empty($headers[$header])) {
                $score += $weight;
            }
        }

        return round(($score / $totalWeight) * 100);
    }

    /**
     * Show detailed header analysis
     */
    protected function showDetailedHeaders(array $headers, array $securityHeaders): void
    {
        $this->line('Security Headers Analysis:');
        
        foreach ($securityHeaders as $header => $weight) {
            $status = isset($headers[$header]) && !empty($headers[$header]) ? '✓' : '✗';
            $value = $headers[$header] ?? 'Not Set';
            
            // Handle array values
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            
            $this->line("  {$status} {$header}: {$value}");
        }
    }

    /**
     * Show test summary
     */
    protected function showSummary(array $results, array $securityHeaders): void
    {
        $this->info('Security Headers Test Summary');
        $this->info('============================');
        $this->newLine();

        $totalTests = count($results);
        $successfulTests = count(array_filter($results, fn($r) => $r['status'] !== 'ERROR'));
        $averageScore = $successfulTests > 0 ? 
            round(array_sum(array_column($results, 'security_score')) / $successfulTests) : 0;

        $this->line("Total Tests: {$totalTests}");
        $this->line("Successful Tests: {$successfulTests}");
        $this->line("Average Security Score: {$averageScore}/100");
        $this->newLine();

        // Security recommendations
        $this->info('Security Recommendations:');
        
        if ($averageScore >= 90) {
            $this->line('✓ Excellent security headers implementation');
        } elseif ($averageScore >= 70) {
            $this->line('⚠ Good security headers, some improvements needed');
        } else {
            $this->line('✗ Security headers need significant improvement');
        }

        // Missing headers analysis
        $missingHeaders = [];
        foreach ($results as $result) {
            if (isset($result['headers'])) {
                foreach ($securityHeaders as $header => $weight) {
                    if (!isset($result['headers'][$header]) || empty($result['headers'][$header])) {
                        $missingHeaders[$header] = ($missingHeaders[$header] ?? 0) + 1;
                    }
                }
            }
        }

        if (!empty($missingHeaders)) {
            $this->newLine();
            $this->warn('Most Missing Headers:');
            arsort($missingHeaders);
            foreach (array_slice($missingHeaders, 0, 5, true) as $header => $count) {
                $this->line("  • {$header}: Missing in {$count} endpoints");
            }
        }

        $this->newLine();
        $this->info('Security headers test completed!');
    }
}
