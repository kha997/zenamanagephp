<?php declare(strict_types=1);

namespace Tests\Unit\Traits;

use Tests\TestCase;
use Tests\Traits\DomainTestIsolation;
use Illuminate\Foundation\Testing\RefreshDatabase;

/**
 * Test DomainTestIsolation Trait
 * 
 * Validates that the DomainTestIsolation trait works correctly
 * for test isolation and reproducibility.
 */
class DomainTestIsolationTest extends TestCase
{
    use DomainTestIsolation;

    protected function setUp(): void
    {
        parent::setUp();
        // Set domain seed and name before calling setupDomainIsolation
        $this->domainSeed = 12345;
        $this->domainName = 'test';
        $this->setupDomainIsolation();
    }

    /**
     * Test that setupDomainIsolation sets seed correctly
     */
    public function test_setup_domain_isolation_sets_seed(): void
    {
        $this->assertNotNull($this->getTestData('seed'));
        $this->assertEquals(12345, $this->getTestData('seed'));
        $this->assertEquals(12345, $this->getDomainSeed());
    }

    /**
     * Test that setupDomainIsolation sets domain name correctly
     */
    public function test_setup_domain_isolation_sets_domain(): void
    {
        $this->assertNotNull($this->getTestData('domain'));
        $this->assertEquals('test', $this->getTestData('domain'));
        $this->assertEquals('test', $this->getDomainName());
    }

    /**
     * Test that clearDomainTestData clears test data
     */
    public function test_clear_domain_test_data(): void
    {
        $this->storeTestData('test_key', 'test_value');
        $this->assertEquals('test_value', $this->getTestData('test_key'));
        
        $this->clearDomainTestData();
        
        $this->assertNull($this->getTestData('test_key'));
    }

    /**
     * Test that verifyTestIsolation verifies isolation
     */
    public function test_verify_test_isolation(): void
    {
        $this->verifyTestIsolation();
        
        // Should not throw exception
        $this->assertTrue(true);
    }

    /**
     * Test seed reproducibility - same seed should produce same results
     */
    public function test_seed_reproducibility(): void
    {
        // Set seed
        $this->setDomainSeed(99999);
        $this->setupDomainIsolation(99999);
        
        // Generate some "random" data
        $random1 = mt_rand();
        
        // Reset and use same seed
        mt_srand(99999);
        $random2 = mt_rand();
        
        // Should be the same with same seed
        $this->assertEquals($random1, $random2);
    }

    /**
     * Test domain name tracking
     */
    public function test_domain_name_tracking(): void
    {
        $this->setDomainName('auth');
        $this->setupDomainIsolation();
        
        $this->assertEquals('auth', $this->getDomainName());
        $this->assertEquals('auth', $this->getTestData('domain'));
    }

    /**
     * Test store and retrieve test data
     */
    public function test_store_and_retrieve_test_data(): void
    {
        $this->storeTestData('user_id', 123);
        $this->storeTestData('tenant_id', 456);
        
        $this->assertEquals(123, $this->getTestData('user_id'));
        $this->assertEquals(456, $this->getTestData('tenant_id'));
        
        $allData = $this->getTestData();
        $this->assertArrayHasKey('user_id', $allData);
        $this->assertArrayHasKey('tenant_id', $allData);
    }

    /**
     * Test assertTestDataSeed assertion
     */
    public function test_assert_test_data_seed(): void
    {
        $this->setupDomainIsolation(12345);
        $this->assertTestDataSeed(12345);
    }

    /**
     * Test assertTestDataDomain assertion
     */
    public function test_assert_test_data_domain(): void
    {
        $this->setDomainName('projects');
        $this->setupDomainIsolation();
        $this->assertTestDataDomain('projects');
    }

    /**
     * Test resetTestData method
     */
    public function test_reset_test_data(): void
    {
        $this->storeTestData('key1', 'value1');
        $this->storeTestData('key2', 'value2');
        
        $this->assertNotNull($this->getTestData('key1'));
        $this->assertNotNull($this->getTestData('key2'));
        
        $this->resetTestData();
        
        $this->assertNull($this->getTestData('key1'));
        $this->assertNull($this->getTestData('key2'));
    }
}

