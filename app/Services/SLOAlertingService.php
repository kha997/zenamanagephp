<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SLOAlertEmail;
use Carbon\Carbon;

/**
 * SLO Alerting Service
 * 
 * PR: SLO/SLA nội bộ
 * 
 * Monitors SLO compliance and sends alerts when violations occur.
 * Supports multiple alert channels (Email, Slack, In-App).
 */
class SLOAlertingService
{
    /**
     * SLO Targets (from performance-budgets.json)
     */
    private const SLO_TARGETS = [
        'api' => [
            '/api/v1/app/projects' => ['p95' => 300],
            '/api/v1/app/tasks' => ['p95' => 300],
            '/api/v1/app/tasks/{id}/move' => ['p95' => 200],
            '/api/v1/app/documents' => ['p95' => 300],
            '/api/v1/app/dashboard' => ['p95' => 500],
            '/api/v1/admin/*' => ['p95' => 500],
            '/api/v1/me' => ['p95' => 200],
            '/api/v1/me/nav' => ['p95' => 200],
        ],
        'pages' => [
            '/app/dashboard' => ['p95' => 500],
            '/app/projects' => ['p95' => 500],
            '/app/tasks' => ['p95' => 500],
            '/admin/dashboard' => ['p95' => 600],
        ],
        'websocket' => [
            'subscribe' => ['p95' => 200],
            'message_delivery' => ['p95' => 100],
            'connection_establishment' => ['p95' => 500],
        ],
        'cache' => [
            'hit_rate' => ['min' => 80], // percentage
            'freshness' => ['max' => 5000], // milliseconds
            'invalidation_latency' => ['p95' => 50],
        ],
        'database' => [
            'query_time' => ['p95' => 100],
            'slow_queries' => ['max' => 10], // per hour
        ],
        'error_rate' => [
            '4xx' => ['max' => 1.0], // percentage
            '5xx' => ['max' => 0.1], // percentage
        ],
        'availability' => [
            'uptime' => ['min' => 99.9], // percentage
        ],
    ];

    /**
     * Alert severity thresholds
     */
    private const SEVERITY_THRESHOLDS = [
        'critical' => 1.0, // 100% of target
        'warning' => 0.8,  // 80% of target
        'info' => 0.6,     // 60% of target
    ];

    /**
     * Alert cooldown periods (in seconds)
     */
    private const COOLDOWN_PERIODS = [
        'critical' => 0,      // No cooldown
        'warning' => 900,    // 15 minutes
        'info' => 3600,      // 1 hour
    ];

    /**
     * Check SLO compliance and send alerts
     */
    public function checkSLOCompliance(): array
    {
        $violations = [];
        $metrics = $this->getCurrentMetrics();

        // Check API performance
        $apiViolations = $this->checkAPIPerformance($metrics['api'] ?? []);
        $violations = array_merge($violations, $apiViolations);

        // Check page performance
        $pageViolations = $this->checkPagePerformance($metrics['pages'] ?? []);
        $violations = array_merge($violations, $pageViolations);

        // Check WebSocket performance
        $wsViolations = $this->checkWebSocketPerformance($metrics['websocket'] ?? []);
        $violations = array_merge($violations, $wsViolations);

        // Check cache performance
        $cacheViolations = $this->checkCachePerformance($metrics['cache'] ?? []);
        $violations = array_merge($violations, $cacheViolations);

        // Check database performance
        $dbViolations = $this->checkDatabasePerformance($metrics['database'] ?? []);
        $violations = array_merge($violations, $dbViolations);

        // Check error rates
        $errorViolations = $this->checkErrorRates($metrics['errors'] ?? []);
        $violations = array_merge($violations, $errorViolations);

        // Check availability
        $availabilityViolations = $this->checkAvailability($metrics['availability'] ?? []);
        $violations = array_merge($violations, $availabilityViolations);

        // Send alerts for violations
        foreach ($violations as $violation) {
            $this->sendAlert($violation);
        }

        return $violations;
    }

    /**
     * Check API performance SLO
     */
    private function checkAPIPerformance(array $apiMetrics): array
    {
        $violations = [];

        foreach (self::SLO_TARGETS['api'] as $endpoint => $targets) {
            $endpointMetrics = $apiMetrics[$endpoint] ?? null;
            if (!$endpointMetrics || !isset($endpointMetrics['p95'])) {
                continue;
            }

            $p95 = $endpointMetrics['p95'];
            $target = $targets['p95'];

            $severity = $this->calculateSeverity($p95, $target);
            if ($severity) {
                $violations[] = [
                    'category' => 'api',
                    'metric' => $endpoint,
                    'type' => 'response_time',
                    'value' => $p95,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($p95 / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Check page performance SLO
     */
    private function checkPagePerformance(array $pageMetrics): array
    {
        $violations = [];

        foreach (self::SLO_TARGETS['pages'] as $route => $targets) {
            $routeMetrics = $pageMetrics[$route] ?? null;
            if (!$routeMetrics || !isset($routeMetrics['p95'])) {
                continue;
            }

            $p95 = $routeMetrics['p95'];
            $target = $targets['p95'];

            $severity = $this->calculateSeverity($p95, $target);
            if ($severity) {
                $violations[] = [
                    'category' => 'pages',
                    'metric' => $route,
                    'type' => 'load_time',
                    'value' => $p95,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($p95 / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Check WebSocket performance SLO
     */
    private function checkWebSocketPerformance(array $wsMetrics): array
    {
        $violations = [];

        foreach (self::SLO_TARGETS['websocket'] as $metric => $targets) {
            $metricValue = $wsMetrics[$metric] ?? null;
            if (!$metricValue || !isset($metricValue['p95'])) {
                continue;
            }

            $p95 = $metricValue['p95'];
            $target = $targets['p95'];

            $severity = $this->calculateSeverity($p95, $target);
            if ($severity) {
                $violations[] = [
                    'category' => 'websocket',
                    'metric' => $metric,
                    'type' => 'latency',
                    'value' => $p95,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($p95 / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Check cache performance SLO
     */
    private function checkCachePerformance(array $cacheMetrics): array
    {
        $violations = [];

        // Check hit rate
        if (isset($cacheMetrics['hit_rate'])) {
            $hitRate = $cacheMetrics['hit_rate'];
            $target = self::SLO_TARGETS['cache']['hit_rate']['min'];

            if ($hitRate < $target) {
                $severity = $hitRate < ($target * 0.75) ? 'critical' : 'warning';
                $violations[] = [
                    'category' => 'cache',
                    'metric' => 'hit_rate',
                    'type' => 'hit_rate',
                    'value' => $hitRate,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($hitRate / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        // Check freshness
        if (isset($cacheMetrics['freshness'])) {
            $freshness = $cacheMetrics['freshness'];
            $target = self::SLO_TARGETS['cache']['freshness']['max'];

            if ($freshness > $target) {
                $severity = $freshness > ($target * 2) ? 'critical' : 'warning';
                $violations[] = [
                    'category' => 'cache',
                    'metric' => 'freshness',
                    'type' => 'freshness',
                    'value' => $freshness,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($freshness / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Check database performance SLO
     */
    private function checkDatabasePerformance(array $dbMetrics): array
    {
        $violations = [];

        // Check query time
        if (isset($dbMetrics['query_time']['p95'])) {
            $p95 = $dbMetrics['query_time']['p95'];
            $target = self::SLO_TARGETS['database']['query_time']['p95'];

            $severity = $this->calculateSeverity($p95, $target);
            if ($severity) {
                $violations[] = [
                    'category' => 'database',
                    'metric' => 'query_time',
                    'type' => 'query_time',
                    'value' => $p95,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($p95 / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        // Check slow queries
        if (isset($dbMetrics['slow_queries']['count'])) {
            $slowQueries = $dbMetrics['slow_queries']['count'];
            $target = self::SLO_TARGETS['database']['slow_queries']['max'];

            if ($slowQueries > $target) {
                $severity = $slowQueries > ($target * 2) ? 'critical' : 'warning';
                $violations[] = [
                    'category' => 'database',
                    'metric' => 'slow_queries',
                    'type' => 'slow_queries',
                    'value' => $slowQueries,
                    'target' => $target,
                    'severity' => $severity,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Check error rates SLO
     */
    private function checkErrorRates(array $errorMetrics): array
    {
        $violations = [];

        // Check 4xx errors
        if (isset($errorMetrics['4xx_rate'])) {
            $rate = $errorMetrics['4xx_rate'];
            $target = self::SLO_TARGETS['error_rate']['4xx']['max'];

            if ($rate > $target) {
                $severity = $rate > ($target * 1.5) ? 'critical' : 'warning';
                $violations[] = [
                    'category' => 'errors',
                    'metric' => '4xx_rate',
                    'type' => 'error_rate',
                    'value' => $rate,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($rate / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        // Check 5xx errors
        if (isset($errorMetrics['5xx_rate'])) {
            $rate = $errorMetrics['5xx_rate'];
            $target = self::SLO_TARGETS['error_rate']['5xx']['max'];

            if ($rate > $target) {
                $severity = $rate > ($target * 2) ? 'critical' : 'warning';
                $violations[] = [
                    'category' => 'errors',
                    'metric' => '5xx_rate',
                    'type' => 'error_rate',
                    'value' => $rate,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($rate / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Check availability SLO
     */
    private function checkAvailability(array $availabilityMetrics): array
    {
        $violations = [];

        if (isset($availabilityMetrics['uptime'])) {
            $uptime = $availabilityMetrics['uptime'];
            $target = self::SLO_TARGETS['availability']['uptime']['min'];

            if ($uptime < $target) {
                $severity = $uptime < 99.0 ? 'critical' : 'warning';
                $violations[] = [
                    'category' => 'availability',
                    'metric' => 'uptime',
                    'type' => 'availability',
                    'value' => $uptime,
                    'target' => $target,
                    'severity' => $severity,
                    'percentage' => ($uptime / $target) * 100,
                    'timestamp' => now()->toISOString(),
                ];
            }
        }

        return $violations;
    }

    /**
     * Calculate severity based on value vs target
     */
    private function calculateSeverity(float $value, float $target): ?string
    {
        $ratio = $value / $target;

        if ($ratio >= self::SEVERITY_THRESHOLDS['critical']) {
            return 'critical';
        } elseif ($ratio >= self::SEVERITY_THRESHOLDS['warning']) {
            return 'warning';
        } elseif ($ratio >= self::SEVERITY_THRESHOLDS['info']) {
            return 'info';
        }

        return null;
    }

    /**
     * Send alert for violation
     */
    private function sendAlert(array $violation): void
    {
        // Check cooldown
        if ($this->isInCooldown($violation)) {
            return;
        }

        // Log alert
        Log::warning('SLO Violation', $violation);

        // Store in cache for dashboard
        $this->storeAlert($violation);

        // Send notifications based on severity
        if ($violation['severity'] === 'critical') {
            $this->sendEmailAlert($violation);
            $this->sendSlackAlert($violation);
        } elseif ($violation['severity'] === 'warning') {
            $this->sendSlackAlert($violation);
        }

        // Set cooldown
        $this->setCooldown($violation);
    }

    /**
     * Check if alert is in cooldown period
     */
    private function isInCooldown(array $violation): bool
    {
        $key = $this->getCooldownKey($violation);
        $cooldownUntil = Cache::get($key);

        return $cooldownUntil && now()->timestamp < $cooldownUntil;
    }

    /**
     * Set cooldown for alert
     */
    private function setCooldown(array $violation): void
    {
        $key = $this->getCooldownKey($violation);
        $cooldownPeriod = self::COOLDOWN_PERIODS[$violation['severity']] ?? 0;

        if ($cooldownPeriod > 0) {
            Cache::put($key, now()->timestamp + $cooldownPeriod, $cooldownPeriod);
        }
    }

    /**
     * Get cooldown cache key
     */
    private function getCooldownKey(array $violation): string
    {
        return "slo_alert_cooldown:{$violation['category']}:{$violation['metric']}:{$violation['severity']}";
    }

    /**
     * Store alert in cache for dashboard
     */
    private function storeAlert(array $violation): void
    {
        $alerts = Cache::get('slo_alerts', []);
        $alerts[] = $violation;

        // Keep only last 100 alerts
        $alerts = array_slice($alerts, -100);
        Cache::put('slo_alerts', $alerts, 86400); // 24 hours
    }

    /**
     * Send email alert
     */
    private function sendEmailAlert(array $violation): void
    {
        try {
            $recipients = config('slo.alert_recipients', []);
            
            foreach ($recipients as $email) {
                Mail::to($email)->send(new SLOAlertEmail($violation));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send SLO email alert', [
                'violation' => $violation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send Slack alert
     */
    private function sendSlackAlert(array $violation): void
    {
        try {
            $webhookUrl = config('slo.slack_webhook_url');
            
            if (!$webhookUrl) {
                return;
            }

            $message = $this->formatSlackMessage($violation);
            
            // Send to Slack webhook
            // In production, use a proper Slack client library
            $ch = curl_init($webhookUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_exec($ch);
            curl_close($ch);
        } catch (\Exception $e) {
            Log::error('Failed to send SLO Slack alert', [
                'violation' => $violation,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Format Slack message
     */
    private function formatSlackMessage(array $violation): array
    {
        $severityEmoji = [
            'critical' => '🔴',
            'warning' => '🟡',
            'info' => '🔵',
        ];

        $emoji = $severityEmoji[$violation['severity']] ?? '⚪';
        $title = "{$emoji} SLO Violation: {$violation['category']}/{$violation['metric']}";

        return [
            'text' => $title,
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => $title,
                    ],
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Category:*\n{$violation['category']}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Metric:*\n{$violation['metric']}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Value:*\n{$violation['value']}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Target:*\n{$violation['target']}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Severity:*\n{$violation['severity']}",
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*Percentage:*\n" . number_format($violation['percentage'], 1) . '%',
                        ],
                    ],
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "Timestamp: {$violation['timestamp']}",
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Get current metrics from cache or service
     */
    private function getCurrentMetrics(): array
    {
        // Try to get from cache (stored by metrics collection)
        $metrics = Cache::get('performance_metrics', []);

        // If not in cache, try to get from PerformanceMetricsService or MetricsCollector
        if (empty($metrics)) {
            try {
                // Try PerformanceMetricsService first
                if (class_exists(\App\Services\PerformanceMetricsService::class)) {
                    $metricsService = app(\App\Services\PerformanceMetricsService::class);
                    $metrics = $metricsService->collectMetrics();
                } elseif (class_exists(\App\Services\MetricsCollector::class)) {
                    // Fallback to MetricsCollector
                    $metricsCollector = app(\App\Services\MetricsCollector::class);
                    $metrics = $metricsCollector->collectAll();
                }
            } catch (\Exception $e) {
                Log::warning('Failed to get metrics from metrics service', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $metrics;
    }

    /**
     * Get recent SLO violations
     */
    public function getRecentViolations(int $limit = 50): array
    {
        return Cache::get('slo_alerts', []);
    }
}

