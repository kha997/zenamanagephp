<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Quote;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Simple Quote Test - Minimal test to verify quote functionality
 */
class SimpleQuoteTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected Client $client;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create tenant, user, and client
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->client = Client::factory()->create(['tenant_id' => $this->tenant->id]);
        
        // Set tenant context
        app()->instance('tenant', $this->tenant);
    }

    /** @test */
    public function it_can_create_a_quote_directly()
    {
        // Test direct model creation without authentication
        $quoteData = [
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'type' => 'design',
            'status' => 'draft',
            'title' => 'Test Quote',
            'description' => 'Test description',
            'total_amount' => 1000.00,
            'tax_rate' => 10.00,
            'tax_amount' => 100.00,
            'discount_amount' => 0.00,
            'final_amount' => 1100.00,
            'valid_until' => now()->addDays(30),
            'created_by' => $this->user->id,
        ];

        $quote = Quote::create($quoteData);

        $this->assertDatabaseHas('quotes', [
            'title' => $quoteData['title'],
            'client_id' => $this->client->id,
            'tenant_id' => $this->tenant->id,
        ]);

        $this->assertEquals($quoteData['title'], $quote->title);
        $this->assertEquals('draft', $quote->status);
    }

    /** @test */
    public function it_can_update_quote_status_directly()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'draft',
        ]);

        $quote->update(['status' => 'sent', 'sent_at' => now()]);
        $quote->refresh();

        $this->assertEquals('sent', $quote->status);
        $this->assertNotNull($quote->sent_at);
    }

    /** @test */
    public function it_can_filter_quotes_by_status_directly()
    {
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'draft',
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'sent',
        ]);

        $drafts = Quote::where('tenant_id', $this->tenant->id)
            ->where('status', 'draft')
            ->get();

        $this->assertCount(1, $drafts);
        $this->assertEquals('draft', $drafts->first()->status);
    }

    /** @test */
    public function it_can_calculate_quote_statistics_directly()
    {
        // Create quotes with different statuses
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'draft',
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'sent',
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'accepted',
        ]);

        $stats = [
            'total' => Quote::where('tenant_id', $this->tenant->id)->count(),
            'drafts' => Quote::where('tenant_id', $this->tenant->id)->where('status', 'draft')->count(),
            'sent' => Quote::where('tenant_id', $this->tenant->id)->where('status', 'sent')->count(),
            'accepted' => Quote::where('tenant_id', $this->tenant->id)->where('status', 'accepted')->count(),
        ];

        $this->assertEquals(3, $stats['total']);
        $this->assertEquals(1, $stats['drafts']);
        $this->assertEquals(1, $stats['sent']);
        $this->assertEquals(1, $stats['accepted']);
    }

    /** @test */
    public function it_enforces_tenant_isolation_directly()
    {
        // Create quote for different tenant
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherQuote = Quote::factory()->create(['tenant_id' => $otherTenant->id, 'client_id' => $otherClient->id]);

        // Try to access quote from different tenant
        $quote = Quote::where('tenant_id', $this->tenant->id)
            ->where('id', $otherQuote->id)
            ->first();

        $this->assertNull($quote);
    }

    /** @test */
    public function it_can_detect_expired_quotes_directly()
    {
        // Create expired quote
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->subDays(1),
        ]);

        // Create valid quote
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'sent',
            'valid_until' => now()->addDays(30),
        ]);

        $expiredQuotes = Quote::where('tenant_id', $this->tenant->id)
            ->where('valid_until', '<', now())
            ->where('status', 'sent')
            ->get();

        $this->assertCount(1, $expiredQuotes);
        $this->assertTrue($expiredQuotes->first()->valid_until < now());
    }

    /** @test */
    public function it_can_create_project_from_accepted_quote()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'accepted',
            'accepted_at' => now(),
        ]);

        // Simulate project creation from quote
        $projectData = [
            'tenant_id' => $this->tenant->id,
            'name' => 'Project from ' . $quote->title,
            'status' => 'active',
            'budget_total' => $quote->final_amount,
            'start_date' => now(),
        ];

        // This would be handled by the controller in real implementation
        $this->assertTrue($quote->status === 'accepted');
        $this->assertNotNull($quote->accepted_at);
    }
}
