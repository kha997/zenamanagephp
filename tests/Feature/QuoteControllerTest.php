<?php declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Project;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Quote Controller Test
 * 
 * Tests the QuoteController functionality including:
 * - Quote CRUD operations
 * - Quote status management
 * - Quote filtering and search
 * - Quote statistics
 * - Quote to project conversion
 */
class QuoteControllerTest extends TestCase
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
    public function it_can_display_quotes_index()
    {
        // Create some quotes
        Quote::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quotes.index'));

        $response->assertStatus(200);
        $response->assertViewIs('app.quotes.index');
        $response->assertViewHas(['quotes', 'stats', 'clients']);
    }

    /** @test */
    public function it_can_display_create_quote_form()
    {
        $response = $this->actingAs($this->user)
            ->get(route('quotes.create'));

        $response->assertStatus(200);
        $response->assertViewIs('app.quotes.create');
        $response->assertViewHas(['clients', 'projects']);
    }

    /** @test */
    public function it_can_create_a_new_quote()
    {
        $quoteData = [
            'client_id' => $this->client->id,
            'type' => 'design',
            'title' => $this->faker->sentence,
            'description' => $this->faker->paragraph,
            'total_amount' => 1000.00,
            'tax_rate' => 10.0,
            'discount_amount' => 50.00,
            'valid_until' => now()->addDays(30)->format('Y-m-d'),
            'line_items' => [
                [
                    'description' => 'Design work',
                    'quantity' => 1,
                    'unit_price' => 1000.00,
                ],
            ],
        ];

        $response = $this->actingAs($this->user)
            ->post(route('quotes.store'), $quoteData);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quote created successfully.');
        
        $this->assertDatabaseHas('quotes', [
            'client_id' => $this->client->id,
            'title' => $quoteData['title'],
            'tenant_id' => $this->tenant->id,
            'status' => 'draft',
        ]);
    }

    /** @test */
    public function it_can_display_quote_details()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quotes.show', $quote));

        $response->assertStatus(200);
        $response->assertViewIs('app.quotes.show');
        $response->assertViewHas(['quote', 'relatedQuotes']);
    }

    /** @test */
    public function it_can_send_quote_to_client()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'draft',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('quotes.send', $quote));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quote sent successfully to client.');
        
        $quote->refresh();
        $this->assertEquals('sent', $quote->status);
        $this->assertNotNull($quote->sent_at);
    }

    /** @test */
    public function it_can_accept_quote_and_create_project()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'sent',
            'final_amount' => 1000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('quotes.accept', $quote));

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quote accepted successfully. Project created.');
        
        $quote->refresh();
        $this->assertEquals('accepted', $quote->status);
        $this->assertNotNull($quote->accepted_at);
        $this->assertNotNull($quote->project_id);
        
        // Check that project was created
        $this->assertDatabaseHas('projects', [
            'client_id' => $this->client->id,
            'budget' => 1000.00,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /** @test */
    public function it_can_reject_quote()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'sent',
        ]);

        $rejectionReason = 'Price too high';

        $response = $this->actingAs($this->user)
            ->post(route('quotes.reject', $quote), [
                'rejection_reason' => $rejectionReason,
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('success', 'Quote rejected successfully.');
        
        $quote->refresh();
        $this->assertEquals('rejected', $quote->status);
        $this->assertNotNull($quote->rejected_at);
        $this->assertEquals($rejectionReason, $quote->rejection_reason);
    }

    /** @test */
    public function it_can_filter_quotes_by_status()
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

        $response = $this->actingAs($this->user)
            ->get(route('quotes.index', ['status' => 'draft']));

        $response->assertStatus(200);
        $response->assertViewHas('quotes');
        
        $quotes = $response->viewData('quotes');
        $this->assertCount(1, $quotes->items());
        $this->assertEquals('draft', $quotes->items()[0]->status);
    }

    /** @test */
    public function it_can_filter_quotes_by_type()
    {
        // Create quotes with different types
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'type' => 'design',
        ]);
        Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'type' => 'construction',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quotes.index', ['type' => 'design']));

        $response->assertStatus(200);
        $response->assertViewHas('quotes');
        
        $quotes = $response->viewData('quotes');
        $this->assertCount(1, $quotes->items());
        $this->assertEquals('design', $quotes->items()[0]->type);
    }

    /** @test */
    public function it_can_search_quotes()
    {
        $quote1 = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'title' => 'Design Project Alpha',
        ]);
        
        $quote2 = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'title' => 'Construction Project Beta',
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quotes.index', ['search' => 'Alpha']));

        $response->assertStatus(200);
        $response->assertViewHas('quotes');
        
        $quotes = $response->viewData('quotes');
        $this->assertCount(1, $quotes->items());
        $this->assertEquals('Design Project Alpha', $quotes->items()[0]->title);
    }

    /** @test */
    public function it_can_delete_a_quote()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
        ]);

        $response = $this->actingAs($this->user)
            ->delete(route('quotes.destroy', $quote));

        $response->assertRedirect(route('quotes.index'));
        $response->assertSessionHas('success', 'Quote deleted successfully.');
        
        $this->assertSoftDeleted('quotes', [
            'id' => $quote->id,
        ]);
    }

    /** @test */
    public function it_validates_quote_creation_data()
    {
        $response = $this->actingAs($this->user)
            ->post(route('quotes.store'), []);

        $response->assertSessionHasErrors(['client_id', 'type', 'title', 'total_amount', 'valid_until']);
    }

    /** @test */
    public function it_enforces_tenant_isolation()
    {
        // Create quote for different tenant
        $otherTenant = Tenant::factory()->create();
        $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherQuote = Quote::factory()->create([
            'tenant_id' => $otherTenant->id,
            'client_id' => $otherClient->id,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quotes.show', $otherQuote));

        $response->assertStatus(404);
    }

    /** @test */
    public function it_calculates_quote_statistics()
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
            'status' => 'accepted',
            'final_amount' => 1000.00,
        ]);

        $response = $this->actingAs($this->user)
            ->get(route('quotes.index'));

        $response->assertStatus(200);
        $response->assertViewHas('stats');
        
        $stats = $response->viewData('stats');
        $this->assertEquals(2, $stats['total']);
        $this->assertEquals(1, $stats['draft']);
        $this->assertEquals(1, $stats['accepted']);
        $this->assertEquals(1000.00, $stats['total_value']);
    }

    /** @test */
    public function it_calculates_final_amount_correctly()
    {
        $quote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'total_amount' => 1000.00,
            'tax_rate' => 10.0,
            'discount_amount' => 50.00,
        ]);

        $expectedFinalAmount = (1000.00 - 50.00) * 1.10; // 1045.00

        $this->assertEquals($expectedFinalAmount, $quote->final_amount);
    }

    /** @test */
    public function it_can_detect_expired_quotes()
    {
        $expiredQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'valid_until' => now()->subDays(1),
        ]);

        $this->assertTrue($expiredQuote->isExpired());
    }

    /** @test */
    public function it_can_detect_quotes_expiring_soon()
    {
        $expiringQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'valid_until' => now()->addDays(3),
        ]);

        $this->assertTrue($expiringQuote->expiringSoon());
    }

    /** @test */
    public function it_cannot_send_expired_quote()
    {
        $expiredQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'draft',
            'valid_until' => now()->subDays(1),
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('quotes.send', $expiredQuote));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Quote cannot be sent in its current status.');
    }

    /** @test */
    public function it_cannot_accept_rejected_quote()
    {
        $rejectedQuote = Quote::factory()->create([
            'tenant_id' => $this->tenant->id,
            'client_id' => $this->client->id,
            'status' => 'rejected',
        ]);

        $response = $this->actingAs($this->user)
            ->post(route('quotes.accept', $rejectedQuote));

        $response->assertRedirect();
        $response->assertSessionHas('error', 'Quote cannot be accepted in its current status.');
    }
}
