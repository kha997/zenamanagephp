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

    public function test_auth_helper_debug()
    {
        // Tạo user và tenant
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        
        // Test 1: Sử dụng Auth facade thay vì auth() helper
        try {
            } catch (\Exception $e) {
            }
        
        // Test 2: Kiểm tra Auth facade
        try {
            } catch (\Exception $e) {
            }
        
        // Test 3: Kiểm tra middleware auth:api
        $response = $this->getJson('/api/v1/templates');
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
            
            if ($response->getStatusCode() !== 200) {
                }
        } else {
            }
        
        $this->assertTrue(true); // Placeholder assertion
    }
}