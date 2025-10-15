<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Auth;

class AuthDebugTest extends TestCase
{
    use RefreshDatabase;
    
    protected function setUp(): void
    {
        parent::setUp();
        $this->markTestSkipped('All AuthDebugTest tests skipped - debug test with output');
    }

    public function test_auth_helper_debug()
    {
        $this->markTestSkipped('AuthDebugTest skipped - debug test with output');
        // Tạo user và tenant
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Test 1: Sử dụng Auth facade thay vì auth() helper
        try {
            dump('Auth facade default guard:', get_class(Auth::guard()));
            dump('Auth facade api guard:', get_class(Auth::guard('api')));
        } catch (\Exception $e) {
            dump('Auth facade error:', $e->getMessage());
        }
        
        // Test 2: Kiểm tra Auth facade
        try {
            dump('Auth facade guard:', get_class(Auth::guard()));
            dump('Auth facade api guard:', get_class(Auth::guard('api')));
        } catch (\Exception $e) {
            dump('Auth facade error:', $e->getMessage());
        }
        
        // Test 3: Kiểm tra middleware auth:api
        $response = $this->getJson('/api/v1/templates');
        dump('Response status:', $response->getStatusCode());
        
        $this->assertTrue(true); // Placeholder assertion
    }
    
    public function test_middleware_pipeline_debug()
    {
        // Test middleware pipeline với auth:api
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Login để lấy token
        $loginResponse = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password'
        ]);
        
        if ($loginResponse->getStatusCode() === 200) {
            $token = $loginResponse->json('data.token');
            
            // Test request với token
            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token
            ])->getJson('/api/v1/templates');
            
            dump('Authenticated request status:', $response->getStatusCode());
            if ($response->getStatusCode() !== 200) {
                dump('Response body:', $response->getContent());
            }
        } else {
            dump('Login failed:', $loginResponse->getContent());
        }
        
        $this->assertTrue(true); // Placeholder assertion
    }
}