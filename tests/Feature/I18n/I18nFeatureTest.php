<?php declare(strict_types=1);

namespace Tests\Feature\I18n;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Session;
use Tests\TestCase;

class I18nFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'tenant_id' => 'test-tenant',
            'email' => 'i18n-test-' . uniqid() . '@example.com'
        ]);
    }

    /** @test */
    public function it_can_get_i18n_configuration()
    {
        $response = $this->getJson('/api/i18n/config');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'current_language',
                        'current_timezone',
                        'current_currency',
                        'supported_languages',
                        'supported_timezones',
                        'supported_currencies'
                    ]
                ]);
    }

    /** @test */
    public function it_can_set_language()
    {
        $response = $this->postJson('/api/i18n/language', [
            'language' => 'vi'
        ]);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Language updated successfully'
                ]);
    }

    /** @test */
    public function it_rejects_invalid_language()
    {
        $response = $this->postJson('/api/i18n/language', [
            'language' => 'invalid'
        ]);
        
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Invalid language'
                ]);
    }

    /** @test */
    public function it_can_set_timezone()
    {
        $response = $this->postJson('/api/i18n/timezone', [
            'timezone' => 'Asia/Ho_Chi_Minh'
        ]);
        
        $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Timezone updated successfully'
                ]);
    }

    /** @test */
    public function it_rejects_invalid_timezone()
    {
        $response = $this->postJson('/api/i18n/timezone', [
            'timezone' => 'Invalid/Timezone'
        ]);
        
        $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'error' => 'Invalid timezone'
                ]);
    }

    /** @test */
    public function it_can_format_date()
    {
        $response = $this->postJson('/api/i18n/format/date', [
            'date' => '2025-01-15'
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'original',
                        'formatted'
                    ]
                ]);
    }

    /** @test */
    public function it_can_format_time()
    {
        $response = $this->postJson('/api/i18n/format/time', [
            'time' => '2025-01-15 14:30:00'
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'original',
                        'formatted'
                    ]
                ]);
    }

    /** @test */
    public function it_can_format_datetime()
    {
        $response = $this->postJson('/api/i18n/format/datetime', [
            'datetime' => '2025-01-15 14:30:00'
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'original',
                        'formatted'
                    ]
                ]);
    }

    /** @test */
    public function it_can_format_number()
    {
        $response = $this->postJson('/api/i18n/format/number', [
            'number' => 1234.56
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'original',
                        'formatted'
                    ]
                ]);
    }

    /** @test */
    public function it_can_format_currency()
    {
        $response = $this->postJson('/api/i18n/format/currency', [
            'amount' => 1234.56,
            'currency' => 'USD'
        ]);
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'original',
                        'formatted',
                        'currency'
                    ]
                ]);
    }

    /** @test */
    public function it_can_get_current_locale()
    {
        $response = $this->getJson('/api/i18n/locale');
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data' => [
                        'language',
                        'timezone',
                        'currency'
                    ]
                ]);
    }

    /** @test */
    public function it_validates_date_format_input()
    {
        $response = $this->postJson('/api/i18n/format/date', [
            'date' => 'invalid-date'
        ]);
        
        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_time_format_input()
    {
        $response = $this->postJson('/api/i18n/format/time', [
            'time' => 'invalid-time'
        ]);
        
        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_number_format_input()
    {
        $response = $this->postJson('/api/i18n/format/number', [
            'number' => 'not-a-number'
        ]);
        
        $response->assertStatus(422);
    }

    /** @test */
    public function it_validates_currency_format_input()
    {
        $response = $this->postJson('/api/i18n/format/currency', [
            'amount' => 'not-a-number',
            'currency' => 'USD'
        ]);
        
        $response->assertStatus(422);
    }

    /** @test */
    public function it_handles_language_switching_workflow()
    {
        // Set initial language
        $response = $this->postJson('/api/i18n/language', [
            'language' => 'vi'
        ]);
        $response->assertStatus(200);

        // Verify language change
        $response = $this->getJson('/api/i18n/locale');
        $response->assertStatus(200)
                ->assertJsonPath('data.language', 'vi');

        // Format date in Vietnamese
        $response = $this->postJson('/api/i18n/format/date', [
            'date' => '2025-01-15'
        ]);
        $response->assertStatus(200);
        
        $formattedDate = $response->json('data.formatted');
        $this->assertStringContainsString('15', $formattedDate);
    }

    /** @test */
    public function it_handles_timezone_switching_workflow()
    {
        // Set initial timezone
        $response = $this->postJson('/api/i18n/timezone', [
            'timezone' => 'Asia/Ho_Chi_Minh'
        ]);
        $response->assertStatus(200);

        // Verify timezone change
        $response = $this->getJson('/api/i18n/locale');
        $response->assertStatus(200)
                ->assertJsonPath('data.timezone', 'Asia/Ho_Chi_Minh');

        // Format datetime with timezone
        $response = $this->postJson('/api/i18n/format/datetime', [
            'datetime' => '2025-01-15 12:00:00'
        ]);
        $response->assertStatus(200);
        
        $formattedDateTime = $response->json('data.formatted');
        $this->assertStringContainsString('7:00', $formattedDateTime); // UTC+7
    }
}
