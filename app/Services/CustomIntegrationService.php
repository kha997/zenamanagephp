<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CustomIntegrationService - Service cho custom integrations
 */
class CustomIntegrationService
{
    private array $integrationConfig;

    public function __construct()
    {
        $this->integrationConfig = [
            'enabled' => config('integrations.custom.enabled', true),
            'max_integrations' => config('integrations.custom.max_integrations', 10),
            'timeout' => config('integrations.custom.timeout', 30),
            'retry_attempts' => config('integrations.custom.retry_attempts', 3),
            'cache_ttl' => config('integrations.custom.cache_ttl', 300),
            'webhook_secret' => config('integrations.custom.webhook_secret'),
            'allowed_domains' => config('integrations.custom.allowed_domains', []),
            'rate_limit' => [
                'requests_per_minute' => 60,
                'requests_per_hour' => 1000
            ]
        ];
    }

    /**
     * Create custom integration
     */
    public function createIntegration(array $config): array
    {
        try {
            $integration = [
                'id' => uniqid('integration_'),
                'name' => $config['name'],
                'type' => $config['type'],
                'config' => $config['config'],
                'webhook_url' => $config['webhook_url'] ?? null,
                'api_key' => $this->generateApiKey(),
                'secret' => $this->generateSecret(),
                'status' => 'active',
                'created_at' => now()->toISOString(),
                'created_by' => $config['user_id'] ?? null
            ];

            // Store integration
            $this->storeIntegration($integration);

            return [
                'success' => true,
                'integration' => $integration
            ];

        } catch (\Exception $e) {
            Log::error('Failed to create custom integration', [
                'config' => $config,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Update custom integration
     */
    public function updateIntegration(string $integrationId, array $updates): array
    {
        try {
            $integration = $this->getIntegration($integrationId);
            
            if (!$integration) {
                return ['success' => false, 'error' => 'Integration not found'];
            }

            $integration = array_merge($integration, $updates);
            $integration['updated_at'] = now()->toISOString();

            $this->storeIntegration($integration);

            return [
                'success' => true,
                'integration' => $integration
            ];

        } catch (\Exception $e) {
            Log::error('Failed to update custom integration', [
                'integration_id' => $integrationId,
                'updates' => $updates,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete custom integration
     */
    public function deleteIntegration(string $integrationId): array
    {
        try {
            $integration = $this->getIntegration($integrationId);
            
            if (!$integration) {
                return ['success' => false, 'error' => 'Integration not found'];
            }

            $this->removeIntegration($integrationId);

            return [
                'success' => true,
                'message' => 'Integration deleted successfully'
            ];

        } catch (\Exception $e) {
            Log::error('Failed to delete custom integration', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get integration by ID
     */
    public function getIntegration(string $integrationId): ?array
    {
        $cacheKey = "integration_{$integrationId}";
        return Cache::get($cacheKey);
    }

    /**
     * Get all integrations
     */
    public function getAllIntegrations(string $userId = null): array
    {
        $cacheKey = 'all_integrations' . ($userId ? "_{$userId}" : '');
        
        return Cache::remember($cacheKey, $this->integrationConfig['cache_ttl'], function () 
        });
    }

    /**
     * Test integration
     */
    public function testIntegration(string $integrationId): array
    {
        try {
            $integration = $this->getIntegration($integrationId);
            
            if (!$integration) {
                return ['success' => false, 'error' => 'Integration not found'];
            }

            $testResult = $this->performIntegrationTest($integration);

            return [
                'success' => true,
                'test_result' => $testResult,
                'tested_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to test integration', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Execute integration
     */
    public function executeIntegration(string $integrationId, array $data): array
    {
        try {
            $integration = $this->getIntegration($integrationId);
            
            if (!$integration) {
                return ['success' => false, 'error' => 'Integration not found'];
            }

            if ($integration['status'] !== 'active') {
                return ['success' => false, 'error' => 'Integration is not active'];
            }

            $result = $this->performIntegrationExecution($integration, $data);

            return [
                'success' => true,
                'result' => $result,
                'executed_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to execute integration', [
                'integration_id' => $integrationId,
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Handle webhook
     */
    public function handleWebhook(string $integrationId, array $payload, array $headers = []): array
    {
        try {
            $integration = $this->getIntegration($integrationId);
            
            if (!$integration) {
                return ['success' => false, 'error' => 'Integration not found'];
            }

            // Verify webhook signature
            if (!$this->verifyWebhookSignature($integration, $payload, $headers)) {
                return ['success' => false, 'error' => 'Invalid webhook signature'];
            }

            $result = $this->processWebhookPayload($integration, $payload);

            return [
                'success' => true,
                'result' => $result,
                'processed_at' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            Log::error('Failed to handle webhook', [
                'integration_id' => $integrationId,
                'payload' => $payload,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get integration logs
     */
    public function getIntegrationLogs(string $integrationId, int $limit = 50): array
    {
        try {
            $logs = $this->fetchIntegrationLogs($integrationId, $limit);

            return [
                'success' => true,
                'logs' => $logs,
                'total' => count($logs)
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get integration logs', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get integration statistics
     */
    public function getIntegrationStatistics(string $integrationId): array
    {
        try {
            $stats = $this->fetchIntegrationStatistics($integrationId);

            return [
                'success' => true,
                'statistics' => $stats
            ];

        } catch (\Exception $e) {
            Log::error('Failed to get integration statistics', [
                'integration_id' => $integrationId,
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Helper Methods
     */
    private function generateApiKey(): string
    {
        return 'integration_' . bin2hex(random_bytes(16));
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function storeIntegration(array $integration): void
    {
        $cacheKey = "integration_{$integration['id']}";
        Cache::put($cacheKey, $integration, 86400); // 24 hours
    }

    private function removeIntegration(string $integrationId): void
    {
        $cacheKey = "integration_{$integrationId}";
        Cache::forget($cacheKey);
    }

    private function fetchIntegrations(string $userId = null): array
    {
        // Implementation for fetching integrations from database
        return [];
    }

    private function performIntegrationTest(array $integration): array
    {
        switch ($integration['type']) {
            case 'webhook':
                return $this->testWebhookIntegration($integration);
            case 'api':
                return $this->testApiIntegration($integration);
            case 'database':
                return $this->testDatabaseIntegration($integration);
            default:
                return ['status' => 'unknown', 'message' => 'Unknown integration type'];
        }
    }

    private function testWebhookIntegration(array $integration): array
    {
        try {
            $response = Http::timeout($this->integrationConfig['timeout'])
                ->post($integration['webhook_url'], [
                    'test' => true,
                    'timestamp' => now()->toISOString()
                ]);

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                'message' => $response->successful() ? 'Webhook test successful' : 'Webhook test failed'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    private function testApiIntegration(array $integration): array
    {
        try {
            $config = $integration['config'];
            $response = Http::timeout($this->integrationConfig['timeout'])
                ->withHeaders($config['headers'] ?? [])
                ->get($config['base_url'] . '/test');

            return [
                'status' => $response->successful() ? 'success' : 'failed',
                'response_code' => $response->status(),
                'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                'message' => $response->successful() ? 'API test successful' : 'API test failed'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    private function testDatabaseIntegration(array $integration): array
    {
        try {
            $config = $integration['config'];
            // Test database connection
            $connection = new \PDO(
                $config['dsn'],
                $config['username'],
                $config['password']
            );

            return [
                'status' => 'success',
                'message' => 'Database connection successful'
            ];

        } catch (\Exception $e) {
            return [
                'status' => 'failed',
                'message' => $e->getMessage()
            ];
        }
    }

    private function performIntegrationExecution(array $integration, array $data): array
    {
        switch ($integration['type']) {
            case 'webhook':
                return $this->executeWebhookIntegration($integration, $data);
            case 'api':
                return $this->executeApiIntegration($integration, $data);
            case 'database':
                return $this->executeDatabaseIntegration($integration, $data);
            default:
                return ['error' => 'Unknown integration type'];
        }
    }

    private function executeWebhookIntegration(array $integration, array $data): array
    {
        try {
            $response = Http::timeout($this->integrationConfig['timeout'])
                ->post($integration['webhook_url'], $data);

            return [
                'success' => $response->successful(),
                'response_code' => $response->status(),
                'response_data' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function executeApiIntegration(array $integration, array $data): array
    {
        try {
            $config = $integration['config'];
            $response = Http::timeout($this->integrationConfig['timeout'])
                ->withHeaders($config['headers'] ?? [])
                ->post($config['base_url'] . $config['endpoint'], $data);

            return [
                'success' => $response->successful(),
                'response_code' => $response->status(),
                'response_data' => $response->json()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function executeDatabaseIntegration(array $integration, array $data): array
    {
        try {
            $config = $integration['config'];
            $connection = new \PDO(
                $config['dsn'],
                $config['username'],
                $config['password']
            );

            $stmt = $connection->prepare($config['query']);
            $result = $stmt->execute($data);

            return [
                'success' => $result,
                'affected_rows' => $stmt->rowCount()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function verifyWebhookSignature(array $integration, array $payload, array $headers): bool
    {
        $signature = $headers['X-Signature'] ?? $headers['x-signature'] ?? '';
        $expectedSignature = hash_hmac('sha256', json_encode($payload), $integration['secret']);
        
        return hash_equals($expectedSignature, $signature);
    }

    private function processWebhookPayload(array $integration, array $payload): array
    {
        // Process webhook payload based on integration configuration
        return [
            'processed' => true,
            'payload_size' => strlen(json_encode($payload)),
            'integration_id' => $integration['id']
        ];
    }

    private function fetchIntegrationLogs(string $integrationId, int $limit): array
    {
        // Implementation for fetching integration logs
        return [];
    }

    private function fetchIntegrationStatistics(string $integrationId): array
    {
        return [
            'total_executions' => 0,
            'successful_executions' => 0,
            'failed_executions' => 0,
            'average_response_time' => 0,
            'last_execution' => null
        ];
    }
}