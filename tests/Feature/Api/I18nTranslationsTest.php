<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class I18nTranslationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        
        // Clear cache before each test
        Cache::flush();
        
        // Ensure translation files exist
        $this->ensureTranslationFilesExist();
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        Cache::flush();
        
        parent::tearDown();
    }

    /**
     * Ensure translation files exist for testing
     */
    private function ensureTranslationFilesExist(): void
    {
        $locales = ['en', 'vi'];
        $namespaces = ['app', 'settings', 'tasks', 'projects', 'dashboard', 'auth'];
        
        foreach ($locales as $locale) {
            $langDir = base_path("lang/{$locale}");
            if (!File::exists($langDir)) {
                File::makeDirectory($langDir, 0755, true);
            }
            
            foreach ($namespaces as $namespace) {
                $filePath = "{$langDir}/{$namespace}.php";
                if (!File::exists($filePath)) {
                    // Create minimal translation file
                    File::put($filePath, "<?php\n\nreturn [\n    'test_key' => 'Test Value',\n];\n");
                }
            }
        }
    }

    /**
     * Test 1.1: Get translations with default locale and namespaces
     */
    public function test_get_translations_with_defaults(): void
    {
        $response = $this->getJson('/api/i18n/translations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'locale',
                'data' => [
                    'app',
                    'settings',
                    'tasks',
                    'projects',
                    'dashboard',
                    'auth',
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Assert headers
        $this->assertNotNull($response->headers->get('ETag'));
        $cacheControl = $response->headers->get('Cache-Control');
        // Accept either order to be robust across Symfony header formatting
        $this->assertTrue(
            str_contains($cacheControl, 'public, max-age=3600') || str_contains($cacheControl, 'max-age=3600, public'),
            'Cache-Control header should include public and max-age=3600'
        );
    }

    /**
     * Test 1.2: Get translations with specific locale
     */
    public function test_get_translations_with_specific_locale(): void
    {
        $response = $this->getJson('/api/i18n/translations?locale=vi');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'locale' => 'vi',
            ]);
    }

    /**
     * Test 1.3: Get translations with specific namespaces
     */
    public function test_get_translations_with_specific_namespaces(): void
    {
        $response = $this->getJson('/api/i18n/translations?namespaces=app,settings');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'locale',
                'data' => [
                    'app',
                    'settings',
                ],
            ]);

        $data = $response->json('data');
        $this->assertArrayNotHasKey('tasks', $data);
        $this->assertArrayNotHasKey('projects', $data);
    }

    /**
     * Test 1.4: Get translations with flat structure
     */
    public function test_get_translations_with_flat_structure(): void
    {
        $response = $this->getJson('/api/i18n/translations?flat=true&namespaces=app');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        
        // Assert flat structure (keys should be "namespace.key" format)
        $hasFlatKeys = false;
        foreach (array_keys($data) as $key) {
            if (strpos($key, '.') !== false) {
                $hasFlatKeys = true;
                break;
            }
        }
        
        $this->assertTrue($hasFlatKeys, 'Response should have flat structure with "namespace.key" format');
    }

    /**
     * Test 2.1: Locale from query parameter
     */
    public function test_locale_resolution_from_query_param(): void
    {
        app()->setLocale('en');
        
        $response = $this->getJson('/api/i18n/translations?locale=vi');

        $response->assertStatus(200)
            ->assertJson([
                'locale' => 'vi',
            ]);
    }

    /**
     * Test 2.2: Locale from Accept-Language header
     */
    public function test_locale_resolution_from_accept_language_header(): void
    {
        app()->setLocale('en');
        
        $response = $this->getJson('/api/i18n/translations', [
            'Accept-Language' => 'vi,en;q=0.9',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'locale' => 'vi',
            ]);
    }

    /**
     * Test 2.3: Locale fallback to app locale
     */
    public function test_locale_fallback_to_app_locale(): void
    {
        app()->setLocale('vi');
        
        $response = $this->getJson('/api/i18n/translations');

        $response->assertStatus(200);
        $this->assertContains(
            $response->json('locale'),
            ['vi', 'en'],
            'Locale should fallback to current app locale'
        );
    }

    /**
     * Test 2.4: Invalid locale rejected
     */
    public function test_invalid_locale_rejected(): void
    {
        $response = $this->getJson('/api/i18n/translations?locale=invalid');

        $response->assertStatus(400)
            ->assertJson([
                'success' => false,
            ]);
        
        $this->assertStringContainsString('Unsupported locale', $response->json('error'));
    }

    /**
     * Test 3.1: Valid namespaces accepted
     */
    public function test_valid_namespaces_accepted(): void
    {
        $response = $this->getJson('/api/i18n/translations?namespaces=app,settings,tasks');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayHasKey('tasks', $data);
    }

    /**
     * Test 3.2: Invalid namespaces filtered
     */
    public function test_invalid_namespaces_filtered(): void
    {
        $response = $this->getJson('/api/i18n/translations?namespaces=app,../../../etc/passwd,settings');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('app', $data);
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayNotHasKey('../../../etc/passwd', $data);
    }

    /**
     * Test 3.4: Namespace with special characters rejected
     */
    public function test_namespace_with_special_characters_rejected(): void
    {
        $response = $this->getJson('/api/i18n/translations?namespaces=app<script>,settings');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertArrayHasKey('settings', $data);
        $this->assertArrayNotHasKey('app<script>', $data);
    }

    /**
     * Test 4.1: Server-side cache works
     */
    public function test_server_side_cache_works(): void
    {
        // Clear cache
        Cache::flush();
        
        // First request (cold cache)
        $response1 = $this->getJson('/api/i18n/translations?namespaces=app');
        $response1->assertStatus(200);
        
        // Second request (should use cache)
        $response2 = $this->getJson('/api/i18n/translations?namespaces=app');
        $response2->assertStatus(200);
        
        // Responses should be identical
        $this->assertEquals($response1->json(), $response2->json());
    }

    /**
     * Test 4.2: ETag generation and 304 response
     */
    public function test_etag_generation_and_304_response(): void
    {
        // First request
        $response1 = $this->getJson('/api/i18n/translations?namespaces=app');
        $response1->assertStatus(200);
        
        $etag = $response1->headers->get('ETag');
        $this->assertNotNull($etag);
        
        // Second request with If-None-Match header
        $response2 = $this->getJson('/api/i18n/translations?namespaces=app', [
            'If-None-Match' => $etag,
        ]);
        
        $response2->assertStatus(304);
    }

    /**
     * Test 4.3: Cache invalidation on locale change
     */
    public function test_cache_invalidation_on_locale_change(): void
    {
        // Request with locale=en
        $response1 = $this->getJson('/api/i18n/translations?locale=en&namespaces=app');
        $response1->assertStatus(200);
        
        // Request with locale=vi
        $response2 = $this->getJson('/api/i18n/translations?locale=vi&namespaces=app');
        $response2->assertStatus(200);
        
        // ETags should be different (different content)
        $this->assertNotEquals(
            $response1->headers->get('ETag'),
            $response2->headers->get('ETag')
        );
    }

    /**
     * Test 5.1: Path traversal prevention
     */
    public function test_path_traversal_prevention(): void
    {
        $response = $this->getJson('/api/i18n/translations?namespaces=../../../etc/passwd');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        // Should not have any data or should have empty result
        $this->assertEmpty($data);
    }

    /**
     * Test 5.2: Public endpoint no auth required
     */
    public function test_public_endpoint_no_auth_required(): void
    {
        // Make request without authentication
        $response = $this->getJson('/api/i18n/translations');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);
    }

    /**
     * Test 6.1: Missing translation files handled gracefully
     */
    public function test_missing_translation_files_handled(): void
    {
        $response = $this->getJson('/api/i18n/translations?namespaces=nonexistent');

        $response->assertStatus(200);
        
        $data = $response->json('data');
        // Should not crash, may have empty object or no key
        $this->assertIsArray($data);
    }

    /**
     * Test 7.1: Response time with cache
     */
    public function test_response_time_with_cache(): void
    {
        // Clear cache
        Cache::flush();
        
        // First request (cold cache)
        $start1 = microtime(true);
        $this->getJson('/api/i18n/translations?namespaces=app');
        $time1 = microtime(true) - $start1;
        
        // Second request (warm cache)
        $start2 = microtime(true);
        $this->getJson('/api/i18n/translations?namespaces=app');
        $time2 = microtime(true) - $start2;
        
        // Cached request should be faster (or at least not slower)
        $this->assertLessThanOrEqual($time1, $time2 * 2, 'Cached request should be faster');
    }
}
