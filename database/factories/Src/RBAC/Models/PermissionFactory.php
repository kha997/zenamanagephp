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
        $table = (new Permission())->getTable();

        $base = [
            'code' => 'perm.' . $this->faker->unique()->slug(2),
            'description' => $this->faker->sentence(),
            'module' => $this->faker->randomElement(['task', 'project', 'document', 'contract', 'finance']),
            'action' => $this->faker->randomElement(['view', 'create', 'update', 'delete', 'approve']),
            'is_active' => true,
        ];

        if (Schema::hasColumn($table, 'tenant_id')) {
            $base['tenant_id'] = null;
        }

        if (Schema::hasColumn($table, 'name')) {
            $base['name'] = $this->faker->words(2, true);
        } elseif (Schema::hasColumn($table, 'label')) {
            $base['label'] = $this->faker->words(2, true);
        } elseif (Schema::hasColumn($table, 'display_name')) {
            $base['display_name'] = $this->faker->words(2, true);
        }

        return $base;
    }
}
