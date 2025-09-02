<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Tenant;

class AuthTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    private Tenant $tenant;

    /**
     * Setup method để tạo tenant mặc định
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant mặc định cho test
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Company',
            'domain' => 'test.com'
        ]);
    }

    /**
     * Test user registration.
     *
     * @return void
     */
    public function test_user_can_register()
    {
        $userData = [
            'name' => $this->faker->name,
            'email' => $this->faker->unique()->safeEmail,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => $this->faker->company,
        ];
    
        $response = $this->postJson('/api/v1/auth/register', $userData);
    
        // Debug: In ra response body nếu có lỗi
        if ($response->getStatusCode() !== 201) {
            dump('Response Status: ' . $response->getStatusCode());
            dump('Response Body: ' . $response->getContent());
            dump('Request Data: ', $userData);
        }
    
        $response->assertStatus(201)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'user' => [
                             'id',
                             'name',
                             'email',
                             'created_at',
                             'updated_at',
                         ],
                         'token', // AuthController trả về 'token' thay vì 'access_token'
                         'token_type',
                         'expires_in',
                     ],
                 ]);
    
        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
        ]);
    }

    /**
     * Test user login.
     *
     * @return void
     */
    public function test_user_can_login()
    {
        $password = 'password123';
        $user = User::factory()->forTenant($this->tenant->id)->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'status',
                     'data' => [
                         'user' => [
                             'id',
                             'name',
                             'email',
                         ],
                         'token', // AuthController trả về 'token' thay vì 'access_token'
                         'token_type',
                         'expires_in',
                     ],
                 ]);
    }

    /**
     * Test user cannot login with invalid credentials.
     *
     * @return void
     */
    public function test_user_cannot_login_with_invalid_credentials()
    {
        $user = User::factory()->forTenant($this->tenant->id)->create([
            'password' => Hash::make('correct_password'),
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'wrong_password',
        ]);

        $response->assertStatus(401)
                 ->assertJson([
                     'status' => 'fail', // AuthController trả về 'fail' thay vì 'error'
                     'data' => [
                         'message' => 'Email hoặc mật khẩu không đúng'
                     ]
                 ]);
    }

    /**
     * Test user can get profile with valid token.
     *
     * @return void
     */
    public function test_user_can_get_profile_with_valid_token()
    {
        $password = 'password123';
        $user = User::factory()->forTenant($this->tenant->id)->create([
            'password' => Hash::make($password),
        ]);

        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $token = $loginResponse->json('data.token'); // Sử dụng 'token' thay vì 'access_token'

        $profileResponse = $this->withHeaders([
            'Authorization' => 'Bearer ' . $token,
        ])->getJson('/api/v1/auth/me');

        $profileResponse->assertStatus(200)
                        ->assertJson([
                            'status' => 'success',
                            'data' => [
                                'user' => [
                                    'id' => $user->id,
                                    'name' => $user->name,
                                    'email' => $user->email,
                                ],
                            ],
                        ]);
    }
}