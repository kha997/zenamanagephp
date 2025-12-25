public function test_simple_auth_api_route()
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    
    // Test route chỉ có auth:api middleware
    $response = $this->getJson('/api/user');
    $response->assertUnauthorized();
    
    $this->assertTrue(true);
}