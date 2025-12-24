<?php declare(strict_types=1);

namespace Database\Factories\Src\RBAC\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Src\RBAC\Models\Permission;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $base = [
            'code' => 'perm.' . $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'module' => $this->faker->randomElement(['task', 'project', 'document', 'contract', 'finance']),
            'action' => $this->faker->randomElement(['view', 'create', 'update', 'delete', 'approve']),
            'is_active' => true,
            'tenant_id' => null,
        ];

        if (Schema::hasColumn('permissions', 'name')) {
            $base['name'] = $this->faker->words(2, true);
        } elseif (Schema::hasColumn('permissions', 'label')) {
            $base['label'] = $this->faker->words(2, true);
        } elseif (Schema::hasColumn('permissions', 'display_name')) {
            $base['display_name'] = $this->faker->words(2, true);
        }

        return $base;
    }
}
