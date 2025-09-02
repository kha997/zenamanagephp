<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\Permission;

/**
 * Factory cho Permission model
 * 
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\RBAC\Models\Permission>
 */
class PermissionFactory extends Factory
{
    /**
     * Model được tạo bởi factory này
     */
    protected $model = Permission::class;

    /**
     * Định nghĩa trạng thái mặc định của model
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $modules = ['project', 'task', 'document', 'user', 'role', 'component', 'template'];
        $actions = ['create', 'read', 'update', 'delete', 'list', 'approve', 'reject'];
        
        $module = $this->faker->randomElement($modules);
        $action = $this->faker->randomElement($actions);
        
        return [
            'module' => $module,
            'action' => $action,
            'code' => strtolower($module) . '.' . strtolower($action),
            'description' => $this->faker->sentence(6),
        ];
    }

    /**
     * Tạo permission cho module cụ thể
     */
    public function forModule(string $module): static
    {
        return $this->state(function (array $attributes) use ($module) {
            $action = $attributes['action'] ?? 'read';
            return [
                'module' => $module,
                'code' => strtolower($module) . '.' . strtolower($action),
            ];
        });
    }

    /**
     * Tạo permission cho action cụ thể
     */
    public function forAction(string $action): static
    {
        return $this->state(function (array $attributes) use ($action) {
            $module = $attributes['module'] ?? 'general';
            return [
                'action' => $action,
                'code' => strtolower($module) . '.' . strtolower($action),
            ];
        });
    }

    /**
     * Tạo permission với code cụ thể
     */
    public function withCode(string $code): static
    {
        $parts = explode('.', $code);
        $module = $parts[0] ?? 'general';
        $action = $parts[1] ?? 'read';
        
        return $this->state([
            'module' => $module,
            'action' => $action,
            'code' => $code,
        ]);
    }

    /**
     * Tạo các permission cơ bản cho CRUD
     */
    public function crudPermissions(string $module): array
    {
        $actions = ['create', 'read', 'update', 'delete'];
        $permissions = [];
        
        foreach ($actions as $action) {
            $permissions[] = $this->forModule($module)->forAction($action)->make();
        }
        
        return $permissions;
    }
}