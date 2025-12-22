<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\EnhancedValidationService;
use App\Services\ValidationConfigurationService;
use App\Services\InputSanitizationService;
use App\Http\Requests\ValidationRules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Enhanced Validation System Tests
 * 
 * Tests the comprehensive validation system including
 * input validation, sanitization, and security checks
 */
class EnhancedValidationSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Ensure storage directory exists
        Storage::fake('local');
    }

    /**
     * Test basic validation functionality
     */
    public function test_basic_validation_functionality(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'age' => 'required|integer|min:18|max:100',
        ];
        
        $result = $validationService->validateAndSanitize($data, $rules);
        
        $this->assertIsArray($result);
        $this->assertEquals('John Doe', $result['name']);
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertEquals(30, $result['age']);
    }

    /**
     * Test validation failure
     */
    public function test_validation_failure(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => 'J', // Too short
            'email' => 'invalid-email', // Invalid email
            'age' => 'not-a-number', // Not a number
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'age' => 'required|integer|min:18|max:100',
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Validation failed');
        
        $validationService->validateAndSanitize($data, $rules);
    }

    /**
     * Test security validation
     */
    public function test_security_validation(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => 'John Doe',
            'comment' => 'SELECT * FROM users WHERE id = 1', // SQL injection attempt
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'comment' => 'required|string|max:1000',
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Suspicious input detected');
        
        $validationService->validateAndSanitize($data, $rules);
    }

    /**
     * Test XSS prevention
     */
    public function test_xss_prevention(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => 'John Doe',
            'content' => '<script>alert("XSS")</script>', // XSS attempt
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'content' => 'required|string|max:1000',
        ];
        
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Suspicious input detected');
        
        $validationService->validateAndSanitize($data, $rules);
    }

    /**
     * Test input sanitization
     */
    public function test_input_sanitization(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => '  John Doe  ', // Extra whitespace
            'email' => 'john@example.com',
            'description' => 'A <b>bold</b> description', // HTML tags
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
            'description' => 'required|string|max:1000',
        ];
        
        $result = $validationService->validateAndSanitize($data, $rules);
        
        $this->assertEquals('John Doe', $result['name']); // Trimmed
        $this->assertEquals('john@example.com', $result['email']);
        $this->assertStringNotContainsString('<b>', $result['description']); // HTML escaped
    }

    /**
     * Test file upload validation
     */
    public function test_file_upload_validation(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $file = UploadedFile::fake()->create('test.jpg', 1000, 'image/jpeg');
        
        $result = $validationService->validateFileUpload($file, ['jpg', 'png'], 2048);
        
        $this->assertTrue($result['valid']);
        $this->assertNotNull($result['file']);
    }

    /**
     * Test file upload validation failure
     */
    public function test_file_upload_validation_failure(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $file = UploadedFile::fake()->create('test.txt', 1000, 'text/plain');
        
        $result = $validationService->validateFileUpload($file, ['jpg', 'png'], 2048);
        
        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('File type not allowed', $result['error']);
    }

    /**
     * Test bulk operation validation
     */
    public function test_bulk_operation_validation(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'User 2', 'email' => 'user2@example.com'],
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
        ];
        
        $result = $validationService->validateBulkOperation($data, $rules, 100);
        
        $this->assertTrue($result['valid']);
        $this->assertEquals(2, $result['valid_count']);
        $this->assertEquals(2, $result['total_items']);
    }

    /**
     * Test bulk operation validation failure
     */
    public function test_bulk_operation_validation_failure(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            ['name' => 'User 1', 'email' => 'user1@example.com'],
            ['name' => 'U', 'email' => 'invalid-email'], // Invalid data
        ];
        
        $rules = [
            'name' => 'required|string|min:2|max:255',
            'email' => 'required|email|max:255',
        ];
        
        $result = $validationService->validateBulkOperation($data, $rules, 100);
        
        $this->assertFalse($result['valid']);
        $this->assertNotEmpty($result['errors']);
        $this->assertEquals(1, $result['valid_count']);
        $this->assertEquals(2, $result['total_items']);
    }

    /**
     * Test validation configuration service
     */
    public function test_validation_configuration_service(): void
    {
        $configService = app(ValidationConfigurationService::class);
        
        // Test feature checking
        $this->assertTrue($configService->isFeatureEnabled('enable_security_validation'));
        $this->assertTrue($configService->isFeatureEnabled('enable_input_sanitization'));
        
        // Test feature toggling
        $configService->disableFeature('enable_security_validation');
        $this->assertFalse($configService->isFeatureEnabled('enable_security_validation'));
        
        $configService->enableFeature('enable_security_validation');
        $this->assertTrue($configService->isFeatureEnabled('enable_security_validation'));
        
        // Test settings
        $this->assertEquals(1000, $configService->getSetting('max_bulk_items'));
        $this->assertEquals(10240, $configService->getSetting('max_file_size_kb'));
    }

    /**
     * Test validation rules integration
     */
    public function test_validation_rules_integration(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'website' => 'https://example.com',
            'description' => 'This is a safe description',
            'phone' => '+1-555-123-4567',
            'search' => 'test query',
            'json_data' => '{"key": "value"}',
            'is_active' => true,
            'tags' => ['tag1', 'tag2'],
        ];
        
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email',
            'age' => 'required|integer|min:18|max:100',
            'website' => 'nullable|url',
            'description' => 'nullable|string',
            'phone' => 'nullable|string',
            'search' => 'nullable|string',
            'json_data' => 'nullable|json',
            'is_active' => 'boolean',
            'tags' => 'array',
        ];
        
        $result = $validationService->validateAndSanitize($data, $rules);
        
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('data', $result);
        $this->assertArrayHasKey('sanitized_data', $result);
        
        // Verify sanitization worked
        $this->assertEquals('This is a safe description', $result['sanitized_data']['description']);
    }

    /**
     * Test API request validation
     */
    public function test_api_request_validation(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'name' => 'Test Project',
            'description' => 'A test project description',
            'status' => 'active',
        ];
        
        $result = $validationService->validateApiRequest($data, 'projects', 'POST');
        
        $this->assertIsArray($result);
        $this->assertEquals('Test Project', $result['name']);
        $this->assertEquals('A test project description', $result['description']);
    }

    /**
     * Test validation statistics
     */
    public function test_validation_statistics(): void
    {
        $validationService = app(EnhancedValidationService::class);
        $stats = $validationService->getValidationStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('cache_size', $stats);
        $this->assertArrayHasKey('security_patterns', $stats);
        $this->assertArrayHasKey('pattern_categories', $stats);
    }

    /**
     * Test validation configuration statistics
     */
    public function test_validation_configuration_statistics(): void
    {
        $configService = app(ValidationConfigurationService::class);
        $stats = $configService->getValidationStats();
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('settings', $stats);
        $this->assertArrayHasKey('custom_rules_count', $stats);
        $this->assertArrayHasKey('cached_rules_count', $stats);
    }

    /**
     * Test validation configuration summary
     */
    public function test_validation_configuration_summary(): void
    {
        $configService = app(ValidationConfigurationService::class);
        $summary = $configService->getConfigurationSummary();
        
        $this->assertIsArray($summary);
        $this->assertArrayHasKey('settings', $summary);
        $this->assertArrayHasKey('file_settings', $summary);
        $this->assertArrayHasKey('bulk_settings', $summary);
        $this->assertArrayHasKey('security_settings', $summary);
    }

    /**
     * Test validation cache clearing
     */
    public function test_validation_cache_clearing(): void
    {
        $validationService = app(EnhancedValidationService::class);
        $configService = app(ValidationConfigurationService::class);
        
        // Clear caches
        $validationService->clearCache();
        $configService->clearCache();
        
        // Verify caches are cleared
        $stats = $validationService->getValidationStats();
        $this->assertEquals(0, $stats['cache_size']);
    }

    /**
     * Test input sanitization service
     */
    public function test_input_sanitization_service(): void
    {
        $sanitizationService = app(InputSanitizationService::class);
        
        // Test string sanitization
        $result = $sanitizationService->sanitizeString('  Test String  ');
        $this->assertEquals('Test String', $result);
        
        // Test email sanitization
        $result = $sanitizationService->sanitizeEmail('test@example.com');
        $this->assertEquals('test@example.com', $result);
        
        // Test URL sanitization
        $result = $sanitizationService->sanitizeUrl('https://example.com');
        $this->assertEquals('https://example.com', $result);
        
        // Test integer sanitization
        $result = $sanitizationService->sanitizeInteger('123abc');
        $this->assertEquals(123, $result);
        
        // Test boolean sanitization
        $result = $sanitizationService->sanitizeBoolean('true');
        $this->assertTrue($result);
    }

    /**
     * Test validation with different data types
     */
    public function test_validation_with_different_data_types(): void
    {
        $validationService = app(EnhancedValidationService::class);
        
        $data = [
            'string_field' => 'Test String',
            'integer_field' => 123,
            'float_field' => 123.45,
            'boolean_field' => true,
            'array_field' => ['item1', 'item2'],
            'null_field' => null,
        ];
        
        $rules = [
            'string_field' => 'required|string',
            'integer_field' => 'required|integer',
            'float_field' => 'required|numeric',
            'boolean_field' => 'required|boolean',
            'array_field' => 'required|array',
            'null_field' => 'nullable|string',
        ];
        
        $result = $validationService->validateAndSanitize($data, $rules);
        
        $this->assertTrue($result['valid']);
        $this->assertArrayHasKey('sanitized_data', $result);
        
        $sanitized = $result['sanitized_data'];
        $this->assertIsString($sanitized['string_field']);
        $this->assertIsInt($sanitized['integer_field']);
        $this->assertIsFloat($sanitized['float_field']);
        $this->assertIsBool($sanitized['boolean_field']);
        $this->assertIsArray($sanitized['array_field']);
        $this->assertNull($sanitized['null_field']);
    }
}
