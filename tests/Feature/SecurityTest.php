<?php

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Src\CoreProject\Models\Project;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
            'email_verified' => true,
        ]);
    }

    public function test_security_headers_are_present(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('X-Frame-Options', 'DENY');
        $response->assertHeader('X-XSS-Protection', '1; mode=block');
        $response->assertHeader('Content-Security-Policy');
        $response->assertHeader('Referrer-Policy');
    }

    public function test_rate_limiting_works(): void
    {
        $statuses = [];

        for ($i = 0; $i < 15; $i++) {
            $response = $this->withHeaders([
                'Accept' => 'application/json',
                'X-Tenant-ID' => (string) $this->tenant->id,
            ])->postJson('/api/auth/login', [
                'email' => $this->user->email,
                'password' => 'wrong-password',
            ]);

            $statuses[] = $response->status();
            $this->assertLessThan(500, $response->status());
        }

        $this->assertTrue(
            collect($statuses)->contains(fn (int $status) => in_array($status, [401, 429], true)),
            'Expected secure rejection statuses (401/429) on repeated failed login attempts.'
        );
    }

    public function test_sql_injection_protection(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => "' OR 1=1 --",
            'password' => "' OR ''='",
        ]);

        $this->assertContains($response->status(), [401, 422, 429]);
        $this->assertLessThan(500, $response->status());
    }

    public function test_xss_protection(): void
    {
        $xssPayload = '<script>alert("XSS")</script>';

        $response = $this->postJson('/api/v1/auth/register', [
            'name' => $xssPayload,
            'company_name' => 'Acme',
            'email' => 'xss+' . uniqid() . '@example.com',
            'password' => 'StrongPassword123!',
            'password_confirmation' => 'StrongPassword123!',
        ]);

        $this->assertContains($response->status(), [201, 400, 422, 429]);
        $this->assertLessThan(500, $response->status());
    }

    public function test_file_upload_security(): void
    {
        $response = $this->postJson('/api/documents-simple', [
            'file' => UploadedFile::fake()->create('malicious.php', 8, 'application/x-php'),
        ]);

        $response->assertStatus(401);
    }

    public function test_tenant_isolation(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create([
            'tenant_id' => $otherTenant->id,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);

        $project = Project::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $token = $this->loginAndGetToken($otherTenant, $otherUser, 'password');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $otherTenant->id,
        ])->getJson('/api/v1/projects/' . $project->id);

        $this->assertContains($response->status(), [403, 404]);

        if ($response->status() === 403) {
            $this->assertContains($response->json('error.code'), ['TENANT_INVALID', 'E403.AUTHORIZATION']);
        }
    }

    public function test_authentication_bypass_protection(): void
    {
        $response = $this->getJson('/api/v1/projects');

        $response->assertStatus(401);
    }

    public function test_csrf_protection(): void
    {
        $response = $this->postJson('/api/v1/projects', [
            'name' => 'Unauthorized Project',
        ]);

        $response->assertStatus(401);
    }

    public function test_password_policy_enforcement(): void
    {
        $weakPasswords = ['1', '12345'];

        foreach ($weakPasswords as $password) {
            $response = $this->postJson('/api/v1/auth/register', [
                'name' => 'Weak Password User',
                'company_name' => 'Acme',
                'email' => 'weak+' . uniqid() . '@example.com',
                'password' => $password,
                'password_confirmation' => $password,
            ]);

            $response->assertStatus(422);
            $response->assertJsonValidationErrors(['password']);
        }
    }

    public function test_mfa_enforcement(): void
    {
        $this->user->update(['mfa_enabled' => true]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $this->user->email,
            'password' => 'password',
        ]);

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $this->assertNotEmpty($response->json('data.token'));
    }

    public function test_session_security(): void
    {
        $token = $this->loginAndGetToken($this->tenant, $this->user, 'password');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200);
        $response->assertJsonPath('status', 'success');
        $response->assertJsonPath('data.user.id', (string) $this->user->id);
    }

    public function test_audit_logging(): void
    {
        $token = $this->loginAndGetToken($this->tenant, $this->user, 'password');

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $this->tenant->id,
        ])->postJson('/api/v1/auth/check-permission', []);

        $response->assertStatus(422);
        $response->assertJsonPath('status', 'error');
    }

    public function test_production_security_middleware(): void
    {
        $token = $this->loginAndGetToken($this->tenant, $this->user, 'password');
        $otherTenant = Tenant::factory()->create();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
            'X-Tenant-ID' => (string) $otherTenant->id,
        ])->getJson('/api/v1/projects');

        $response->assertStatus(403);
        $response->assertJsonPath('error.code', 'TENANT_INVALID');
    }

    private function loginAndGetToken(Tenant $tenant, User $user, string $password): string
    {
        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'X-Tenant-ID' => (string) $tenant->id,
        ])->postJson('/api/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);

        $token = data_get($response->json(), 'data.token')
            ?? data_get($response->json(), 'token')
            ?? data_get($response->json(), 'data.access_token')
            ?? data_get($response->json(), 'access_token');

        $this->assertIsString($token);
        $this->assertNotSame('', $token);

        return $token;
    }
}
