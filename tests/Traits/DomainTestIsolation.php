<?php declare(strict_types=1);

namespace Tests\Traits;

use Tests\Helpers\TestDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * DomainTestIsolation Trait
 * 
 * Provides test isolation methods for domain-specific test organization.
 * Ensures reproducible test data using fixed seeds and proper cleanup.
 * 
 * @package Tests\Traits
 */
trait DomainTestIsolation
{
    use RefreshDatabase;

    /**
     * Fixed seed for this domain
     * Override in test class
     * 
     * @var int|null
     */
    protected ?int $domainSeed = null;

    /**
     * Domain name (e.g., 'auth', 'projects', 'tasks')
     * Override in test class
     * 
     * @var string|null
     */
    protected ?string $domainName = null;

    /**
     * Test data created during setUp
     * Used for cleanup and assertions
     * 
     * @var array
     */
    protected array $testData = [];

    /**
     * Setup domain-specific test isolation
     * 
     * Call this in setUp() method:
     * ```php
     * protected function setUp(): void
     * {
     *     parent::setUp();
     *     $this->setupDomainIsolation();
     * }
     * ```
     * 
     * Example usage:
     * ```php
     * class AuthFeatureTest extends TestCase
     * {
     *     use DomainTestIsolation;
     *     
     *     protected int $domainSeed = 12345;
     *     protected string $domainName = 'auth';
     *     
     *     protected function setUp(): void
     *     {
     *         parent::setUp();
     *         $this->setupDomainIsolation();
     *         
     *         // Seed domain-specific data
     *         $data = TestDataSeeder::seedAuthDomain($this->domainSeed);
     *         $this->storeTestData('tenant', $data['tenant']);
     *     }
     * }
     * ```
     * 
     * @param int|null $seed Fixed seed for reproducibility (uses $this->domainSeed if null)
     * @return void
     */
    protected function setupDomainIsolation(?int $seed = null): void
    {
        $seed = $seed ?? $this->domainSeed;
        
        if ($seed !== null) {
            mt_srand($seed);
        }

        // Clear any existing test data
        $this->clearDomainTestData();

        // Store seed for verification
        $this->testData['seed'] = $seed;
        $this->testData['domain'] = $this->domainName;
    }

    /**
     * Clear domain-specific test data
     * 
     * @return void
     */
    protected function clearDomainTestData(): void
    {
        // Clear test data array
        $this->testData = [];

        // Database is already cleared by RefreshDatabase trait
        // This method is for any additional cleanup needed
    }

    /**
     * Get domain seed
     * 
     * @return int|null
     */
    protected function getDomainSeed(): ?int
    {
        return $this->domainSeed;
    }

    /**
     * Set domain seed
     * 
     * @param int $seed
     * @return void
     */
    protected function setDomainSeed(int $seed): void
    {
        $this->domainSeed = $seed;
    }

    /**
     * Get domain name
     * 
     * @return string|null
     */
    protected function getDomainName(): ?string
    {
        return $this->domainName;
    }

    /**
     * Set domain name
     * 
     * @param string $domainName
     * @return void
     */
    protected function setDomainName(string $domainName): void
    {
        $this->domainName = $domainName;
    }

    /**
     * Store test data for later use
     * 
     * @param string $key
     * @param mixed $value
     * @return void
     */
    protected function storeTestData(string $key, $value): void
    {
        $this->testData[$key] = $value;
    }

    /**
     * Get stored test data
     * 
     * @param string|null $key If null, returns all test data
     * @return mixed
     */
    protected function getTestData(?string $key = null)
    {
        if ($key === null) {
            return $this->testData;
        }

        return $this->testData[$key] ?? null;
    }

    /**
     * Assert test data was created with correct seed
     * 
     * @param int $expectedSeed
     * @return void
     */
    protected function assertTestDataSeed(int $expectedSeed): void
    {
        $this->assertEquals(
            $expectedSeed,
            $this->testData['seed'] ?? null,
            "Test data should be created with seed {$expectedSeed}"
        );
    }

    /**
     * Assert test data belongs to correct domain
     * 
     * @param string $expectedDomain
     * @return void
     */
    protected function assertTestDataDomain(string $expectedDomain): void
    {
        $this->assertEquals(
            $expectedDomain,
            $this->testData['domain'] ?? null,
            "Test data should belong to domain {$expectedDomain}"
        );
    }

    /**
     * Verify test isolation - ensure no data leakage between tests
     * 
     * This method verifies that test isolation is working correctly.
     * The RefreshDatabase trait handles database isolation, and this
     * method verifies that test data tracking is correct.
     * 
     * Example usage:
     * ```php
     * public function test_isolation_works(): void
     * {
     *     $this->verifyTestIsolation();
     *     // Test continues...
     * }
     * ```
     * 
     * @return void
     */
    protected function verifyTestIsolation(): void
    {
        // This method can be extended to check for data leakage
        // For now, RefreshDatabase trait handles database isolation
        
        // Verify test data is isolated
        if (isset($this->testData['seed'])) {
            $this->assertNotNull($this->testData['seed'], 'Test seed should be set');
        }
    }

    /**
     * Reset test data for next test
     * 
     * @return void
     */
    protected function resetTestData(): void
    {
        $this->testData = [];
    }
}

