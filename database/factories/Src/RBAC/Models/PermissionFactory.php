<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Src\RBAC\Models\Permission;

/**
 * Factory cho Permission model
 * 
 * Tạo test data cho RBAC permissions với các modules và actions
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state
     */
    public function definition(): array
    {
        $modules = ['project', 'task', 'component', 'document', 'notification', 'user'];
        $actions = ['create', 'read', 'update', 'delete', 'manage'];
        
        $module = $this->faker->randomElement($modules);
        $action = $this->faker->randomElement($actions);
        
        return [
            'code' => "{$module}.{$action}",
            'module' => $module,
            'action' => $action,
            'description' => "Permission to {$action} {$module} resources",
            'created_at' => now(),
            'updated_at' => now()
        ];
    }

    /**
     * Project module permissions
     */
    public function projectModule(): static
    {
        return $this->state(function (array $attributes) {
            $action = $this->faker->randomElement(['create', 'read', 'update', 'delete', 'manage']);
            return [
                'code' => "project.{$action}",
                'module' => 'project',
                'action' => $action,
                'description' => "Permission to {$action} project resources"
            ];
        });
    }

    /**
     * Task module permissions
     */
    public function taskModule(): static
    {
        return $this->state(function (array $attributes) {
            $action = $this->faker->randomElement(['create', 'read', 'update', 'delete', 'assign']);
            return [
                'code' => "task.{$action}",
                'module' => 'task',
                'action' => $action,
                'description' => "Permission to {$action} task resources"
            ];
        });
    }
}