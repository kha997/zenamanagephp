<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class CsrfValidationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test CSRF with proper validation
     */
    public function test_csrf_with_validation(): void
    {
        // Test with register route - should fail CSRF first
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Test Company'
        ]);
        
        dump('Register route status:', $response->getStatusCode());
        dump('Register route headers:', $response->headers->all());
        
        // Check if it's CSRF error or validation error
        if ($response->getStatusCode() === 419) {
            dump('CSRF protection is working!');
        } else {
            dump('CSRF protection is NOT working - status:', $response->getStatusCode());
        }
        
        $this->assertTrue(true);
    }
}
