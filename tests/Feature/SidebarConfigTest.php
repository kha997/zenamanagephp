<?php

namespace Tests\Feature;

use App\Models\SidebarConfig;
use App\Models\User;
use App\Models\UserSidebarPreference;
use App\Services\SidebarService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class SidebarConfigTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected User $user;
    protected User $adminUser;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create([
            'email' => 'user@test.com',
            'role' => 'project_manager'
        ]);
        
        $this->adminUser = User::factory()->create([
            'email' => 'admin@test.com',
            'role' => 'super_admin'
        ]);
    }

    /** @test */
    public function it_can_create_sidebar_config()
    {
        $configData = [
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ]
                ]
            ],
            'is_enabled' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sidebar-configs', $configData);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Sidebar config created successfully'
            ]);

        $this->assertDatabaseHas('sidebar_configs', [
            'role_name' => 'project_manager',
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function it_can_get_sidebar_config_for_role()
    {
        SidebarConfig::create([
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ]
                ]
            ],
            'is_enabled' => true,
            'updated_by' => $this->adminUser->id,
        ]);

        $response = $this->actingAs($this->adminUser)
            ->getJson('/api/admin/sidebar-configs/role/project_manager');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'role_name' => 'project_manager',
                    'is_enabled' => true,
                ]
            ]);
    }

    /** @test */
    public function it_can_clone_sidebar_config()
    {
        SidebarConfig::create([
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ]
                ]
            ],
            'is_enabled' => true,
            'updated_by' => $this->adminUser->id,
        ]);

        $cloneData = [
            'from_role' => 'project_manager',
            'to_role' => 'designer',
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sidebar-configs/clone', $cloneData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Configuration cloned from project_manager to designer successfully'
            ]);

        $this->assertDatabaseHas('sidebar_configs', [
            'role_name' => 'designer',
        ]);
    }

    /** @test */
    public function it_validates_sidebar_config_structure()
    {
        $invalidConfig = [
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'invalid_type',
                        'label' => 'Dashboard',
                        'enabled' => true,
                        'order' => 10,
                    ]
                ]
            ],
            'is_enabled' => true,
        ];

        $response = $this->actingAs($this->adminUser)
            ->postJson('/api/admin/sidebar-configs', $invalidConfig);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Security validation failed'
            ]);
    }

    /** @test */
    public function it_prevents_unauthorized_access()
    {
        $configData = [
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ]
                ]
            ],
            'is_enabled' => true,
        ];

        $response = $this->actingAs($this->user)
            ->postJson('/api/admin/sidebar-configs', $configData);

        $response->assertStatus(403);
    }

    /** @test */
    public function it_can_manage_user_preferences()
    {
        $preferenceData = [
            'pinned_items' => ['dashboard'],
            'hidden_items' => ['reports'],
            'theme' => 'dark',
            'compact_mode' => true,
            'show_badges' => false,
            'auto_expand_groups' => true,
        ];

        $response = $this->actingAs($this->user)
            ->putJson('/api/user-preferences', $preferenceData);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Preferences updated successfully'
            ]);

        $this->assertDatabaseHas('user_sidebar_preferences', [
            'user_id' => $this->user->id,
            'is_enabled' => true,
        ]);
    }

    /** @test */
    public function it_can_pin_and_unpin_items()
    {
        // Pin item
        $response = $this->actingAs($this->user)
            ->postJson('/api/user-preferences/pin', [
                'item_id' => 'dashboard'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item pinned successfully'
            ]);

        // Unpin item
        $response = $this->actingAs($this->user)
            ->postJson('/api/user-preferences/unpin', [
                'item_id' => 'dashboard'
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Item unpinned successfully'
            ]);
    }

    /** @test */
    public function sidebar_service_integration_test()
    {
        // Create sidebar config
        SidebarConfig::create([
            'role_name' => 'project_manager',
            'config' => [
                'items' => [
                    [
                        'id' => 'dashboard',
                        'type' => 'link',
                        'label' => 'Dashboard',
                        'icon' => 'TachometerAlt',
                        'to' => '/dashboard',
                        'required_permissions' => [],
                        'enabled' => true,
                        'order' => 10,
                    ],
                    [
                        'id' => 'projects',
                        'type' => 'link',
                        'label' => 'Projects',
                        'icon' => 'Building',
                        'to' => '/projects',
                        'required_permissions' => ['project.read'],
                        'enabled' => true,
                        'order' => 20,
                    ]
                ]
            ],
            'is_enabled' => true,
            'updated_by' => $this->adminUser->id,
        ]);

        // Create user preferences
        UserSidebarPreference::create([
            'user_id' => $this->user->id,
            'preferences' => [
                'pinned_items' => ['projects'],
                'hidden_items' => [],
                'custom_order' => [],
                'theme' => 'light',
                'compact_mode' => false,
                'show_badges' => true,
                'auto_expand_groups' => false,
            ],
            'is_enabled' => true,
            'version' => 1,
        ]);

        $sidebarService = app(SidebarService::class);
        $sidebarConfig = $sidebarService->getSidebarForUser($this->user);

        $this->assertIsArray($sidebarConfig);
        $this->assertArrayHasKey('items', $sidebarConfig);
        $this->assertArrayHasKey('user_preferences', $sidebarConfig);
        
        // Check that projects is pinned (moved to top)
        $this->assertEquals('projects', $sidebarConfig['items'][0]['id']);
        $this->assertEquals('dashboard', $sidebarConfig['items'][1]['id']);
    }
}