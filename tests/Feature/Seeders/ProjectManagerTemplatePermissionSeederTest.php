<?php declare(strict_types=1);

namespace Tests\Feature\Seeders;

use App\Models\Role;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Database\Seeders\ZenaPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagerTemplatePermissionSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_manager_gets_template_view_and_apply_in_correct_seed_order(): void
    {
        // Chạy đúng thứ tự thật của DatabaseSeeder: RoleSeeder -> PermissionSeeder -> ZenaPermissionsSeeder.
        // Đây là test bắt buộc phải seed-từ-đầu (không dựa vào DB có sẵn) để bắt đúng
        // hazard thứ tự: template.view/template.apply chỉ được ZenaPermissionsSeeder tạo,
        // seeder này chạy SAU PermissionSeeder.
        $this->seed(RoleSeeder::class);
        $this->seed(PermissionSeeder::class);
        $this->seed(ZenaPermissionsSeeder::class);

        $pm = Role::where('name', 'Project Manager')->firstOrFail();

        $this->assertTrue(
            $pm->permissions()->where('name', 'template.view')->exists(),
            'Project Manager phải có permission template.view (cột name, dùng bởi User::hasPermission())'
        );
        $this->assertTrue(
            $pm->permissions()->where('name', 'template.apply')->exists(),
            'Project Manager phải có permission template.apply'
        );
        $this->assertFalse(
            $pm->permissions()->where('name', 'template.edit_draft')->exists(),
            'Project Manager KHÔNG được cấp template.edit_draft (chỉ System Admin soạn thảo template)'
        );
    }
}
