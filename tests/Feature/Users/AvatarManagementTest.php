<?php declare(strict_types=1);

namespace Tests\Feature\Users;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\Helpers\AuthHelper;
use Tests\TestCase;

/**
 * Avatar Management Test
 * 
 * Tests for user avatar upload and deletion functionality
 */
class AvatarManagementTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected Tenant $tenant;
    protected array $authHeaders = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    protected function createUser(): void
    {
        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'email' => $this->faker->unique()->safeEmail,
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    /**
     * Test upload avatar successfully
     */
    public function test_user_can_upload_avatar()
    {
        $this->createUser();
        
        $file = UploadedFile::fake()->image('avatar.jpg', 500, 500);

        $response = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'message',
                    'user' => [
                        'avatar',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ]);

        // Verify avatar was stored
        $this->user->refresh();
        $this->assertNotNull($this->user->avatar);
        $this->assertStringContainsString('avatars', $this->user->avatar);
    }

    /**
     * Test upload avatar requires authentication
     */
    public function test_upload_avatar_requires_authentication()
    {
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->postJson('/api/users/profile/avatar', [
            'avatar' => $file,
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test upload avatar validation
     */
    public function test_upload_avatar_validation()
    {
        $this->createUser();

        // Missing avatar file
        $response = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);

        // Invalid file type
        $file = UploadedFile::fake()->create('document.pdf', 100);
        $response = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);

        // File too large
        $file = UploadedFile::fake()->image('avatar.jpg')->size(3000); // 3MB
        $response = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['avatar']);
    }

    /**
     * Test delete avatar successfully
     */
    public function test_user_can_delete_avatar()
    {
        $this->createUser();
        
        // First upload an avatar
        $file = UploadedFile::fake()->image('avatar.jpg');
        $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file,
            ]);

        $this->user->refresh();
        $this->assertNotNull($this->user->avatar);

        // Now delete it
        $response = $this->withHeaders($this->authHeaders)
            ->deleteJson('/api/users/profile/avatar');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        // Verify avatar was deleted
        $this->user->refresh();
        $this->assertNull($this->user->avatar);
    }

    /**
     * Test delete avatar when no avatar exists
     */
    public function test_delete_avatar_when_no_avatar_exists()
    {
        $this->createUser();

        $response = $this->withHeaders($this->authHeaders)
            ->deleteJson('/api/users/profile/avatar');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
            ]);
    }

    /**
     * Test delete avatar requires authentication
     */
    public function test_delete_avatar_requires_authentication()
    {
        $response = $this->deleteJson('/api/users/profile/avatar');

        $response->assertStatus(401);
    }

    /**
     * Test upload avatar replaces existing avatar
     */
    public function test_upload_avatar_replaces_existing_avatar()
    {
        $this->createUser();
        
        // Upload first avatar
        $file1 = UploadedFile::fake()->image('avatar1.jpg');
        $response1 = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file1,
            ]);
        
        $response1->assertStatus(200);
        $this->user->refresh();
        $firstAvatarUrl = $this->user->avatar;

        // Upload second avatar
        $file2 = UploadedFile::fake()->image('avatar2.jpg');
        $response2 = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file2,
            ]);

        $response2->assertStatus(200);
        $this->user->refresh();
        $secondAvatarUrl = $this->user->avatar;

        // Verify avatar URL changed
        $this->assertNotEquals($firstAvatarUrl, $secondAvatarUrl);
    }

    /**
     * Test multi-tenant isolation for avatar
     */
    public function test_avatar_respects_tenant_isolation()
    {
        $this->createUser();
        
        $file = UploadedFile::fake()->image('avatar.jpg');

        $response = $this->withHeaders($this->authHeaders)
            ->postJson('/api/users/profile/avatar', [
                'avatar' => $file,
            ]);

        $response->assertStatus(200);

        // Verify avatar path includes tenant_id
        $this->user->refresh();
        $this->assertStringContainsString((string) $this->tenant->id, $this->user->avatar);
    }
}

