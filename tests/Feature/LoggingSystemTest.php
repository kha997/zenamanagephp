<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ComprehensiveLoggingService;
use App\Services\LoggingConfigurationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

/**
 * Logging System Tests
 * 
 * Tests the comprehensive logging system including
 * structured logging, performance monitoring, and audit trails
 */
class LoggingSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure log directory exists
        $logPath = storage_path('logs');
        if (!File::exists($logPath)) {
            File::makeDirectory($logPath, 0755, true);
        }
    }

    protected function tearDown(): void
    {
        // Clean up log files after each test
        $logFiles = [
            'laravel.log',
            'structured.log',
            'audit.log',
            'performance.log',
            'security.log',
            'admin.log',
            'data.log',
            'api.log',
        ];
        
        foreach ($logFiles as $file) {
            $path = storage_path("logs/{$file}");
            if (File::exists($path)) {
                File::delete($path);
            }
        }
        
        parent::tearDown();
    }

    /**
     * Test authentication logging
     */
    public function test_authentication_logging(): void
    {
        ComprehensiveLoggingService::logAuth('login_attempt', [
            'email' => 'test@example.com',
            'ip_address' => '192.168.1.1',
        ]);

        $this->assertLogFileExists('security.log');
        $this->assertLogContains('security.log', 'login_attempt');
    }

    /**
     * Test audit logging
     */
    public function test_audit_logging(): void
    {
        ComprehensiveLoggingService::logAudit('user_created', 'User', 'user123', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertLogFileExists('audit.log');
        $this->assertLogContains('audit.log', 'user_created');
        $this->assertLogContains('audit.log', 'User');
        $this->assertLogContains('audit.log', 'user123');
    }

    /**
     * Test performance logging
     */
    public function test_performance_logging(): void
    {
        ComprehensiveLoggingService::logPerformance('database_query', 1.5, [
            'query_count' => 5,
            'memory_usage' => 1024000,
        ]);

        $this->assertLogFileExists('performance.log');
        $this->assertLogContains('performance.log', 'database_query');
        $this->assertLogContains('performance.log', '1500'); // duration in ms
    }

    /**
     * Test security logging
     */
    public function test_security_logging(): void
    {
        ComprehensiveLoggingService::logSecurity('suspicious_activity', [
            'ip_address' => '192.168.1.100',
            'user_agent' => 'Suspicious Bot',
            'attempts' => 10,
        ]);

        $this->assertLogFileExists('security.log');
        $this->assertLogContains('security.log', 'suspicious_activity');
    }

    /**
     * Test API logging
     */
    public function test_api_logging(): void
    {
        ComprehensiveLoggingService::logApi('api_request', [
            'endpoint' => '/api/users',
            'method' => 'GET',
            'status_code' => 200,
        ]);

        $this->assertLogFileExists('api.log');
        $this->assertLogContains('api.log', 'api_request');
    }

    /**
     * Test data access logging
     */
    public function test_data_access_logging(): void
    {
        ComprehensiveLoggingService::logDataAccess('data_read', 'Project', 'proj123', [
            'fields' => ['name', 'description'],
            'filters' => ['status' => 'active'],
        ]);

        $this->assertLogFileExists('data.log');
        $this->assertLogContains('data.log', 'data_read');
        $this->assertLogContains('data.log', 'Project');
    }

    /**
     * Test admin logging
     */
    public function test_admin_logging(): void
    {
        ComprehensiveLoggingService::logAdmin('system_configuration_changed', [
            'setting' => 'app.debug',
            'old_value' => 'true',
            'new_value' => 'false',
        ]);

        $this->assertLogFileExists('admin.log');
        $this->assertLogContains('admin.log', 'system_configuration_changed');
    }

    /**
     * Test business event logging
     */
    public function test_business_event_logging(): void
    {
        ComprehensiveLoggingService::logBusiness('project_completed', 'Project', 'proj456', [
            'completion_date' => '2025-10-06',
            'final_budget' => 50000,
        ]);

        $this->assertLogFileExists('structured.log');
        $this->assertLogContains('structured.log', 'project_completed');
    }

    /**
     * Test error logging
     */
    public function test_error_logging(): void
    {
        $exception = new \Exception('Test exception', 500);
        
        ComprehensiveLoggingService::logError($exception, [
            'context' => 'test_context',
            'additional_data' => 'test_data',
        ]);

        $this->assertLogFileExists('laravel.log');
        $this->assertLogContains('laravel.log', 'Test exception');
        $this->assertLogContains('laravel.log', 'test_context');
    }

    /**
     * Test user action logging
     */
    public function test_user_action_logging(): void
    {
        ComprehensiveLoggingService::logUserAction('project_created', 'Project', 'proj789', [
            'name' => 'New Project',
            'budget' => 100000,
        ]);

        // Should log to appropriate channel based on action
        $this->assertLogFileExists('audit.log');
        $this->assertLogContains('audit.log', 'project_created');
    }

    /**
     * Test logging configuration service
     */
    public function test_logging_configuration_service(): void
    {
        $configService = app(LoggingConfigurationService::class);
        
        // Test feature checking
        $this->assertTrue($configService->isFeatureEnabled('structured_logging'));
        $this->assertTrue($configService->isFeatureEnabled('audit_logging'));
        
        // Test feature toggling
        $configService->disableFeature('performance_tracking');
        $this->assertFalse($configService->isFeatureEnabled('performance_tracking'));
        
        $configService->enableFeature('performance_tracking');
        $this->assertTrue($configService->isFeatureEnabled('performance_tracking'));
        
        // Test log level
        $logLevel = $configService->getLogLevel();
        $this->assertContains($logLevel, ['debug', 'info', 'warning', 'error', 'critical']);
    }

    /**
     * Test logging statistics
     */
    public function test_logging_statistics(): void
    {
        // Create some log entries
        ComprehensiveLoggingService::logSystem('test_event', ['data' => 'test']);
        
        $configService = app(LoggingConfigurationService::class);
        $stats = $configService->getLoggingStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('laravel.log', $stats);
        $this->assertArrayHasKey('size', $stats['laravel.log']);
        $this->assertArrayHasKey('lines', $stats['laravel.log']);
    }

    /**
     * Test PII redaction
     */
    public function test_pii_redaction(): void
    {
        ComprehensiveLoggingService::logAuth('login_attempt', [
            'email' => 'test@example.com',
            'password' => 'secret123',
            'token' => 'abc123',
            'phone' => '+1234567890',
        ]);

        $this->assertLogFileExists('security.log');
        
        // Check that PII is redacted
        $logContent = File::get(storage_path('logs/security.log'));
        $this->assertStringNotContainsString('secret123', $logContent);
        $this->assertStringNotContainsString('abc123', $logContent);
        $this->assertStringContainsString('[REDACTED]', $logContent);
    }

    /**
     * Test structured logging format
     */
    public function test_structured_logging_format(): void
    {
        ComprehensiveLoggingService::logBusiness('test_event', 'TestEntity', 'test123', [
            'test_data' => 'test_value',
        ]);

        $this->assertLogFileExists('structured.log');
        
        $logContent = File::get(storage_path('logs/structured.log'));
        $logEntry = json_decode($logContent, true);
        
        $this->assertIsArray($logEntry);
        $this->assertArrayHasKey('message', $logEntry);
        $this->assertArrayHasKey('context', $logEntry);
        $this->assertArrayHasKey('extra', $logEntry);
        $this->assertEquals('test_event', $logEntry['context']['event']);
    }

    /**
     * Assert that a log file exists
     */
    private function assertLogFileExists(string $filename): void
    {
        $path = storage_path("logs/{$filename}");
        $this->assertTrue(File::exists($path), "Log file {$filename} does not exist");
    }

    /**
     * Assert that a log file contains specific content
     */
    private function assertLogContains(string $filename, string $content): void
    {
        $path = storage_path("logs/{$filename}");
        
        if (!File::exists($path)) {
            $this->fail("Log file {$filename} does not exist");
        }
        
        $logContent = File::get($path);
        $this->assertStringContainsString($content, $logContent, 
            "Log file {$filename} does not contain '{$content}'");
    }
}
