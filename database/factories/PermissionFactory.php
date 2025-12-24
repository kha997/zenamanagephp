<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;

class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    public function definition(): array
    {
        $base = [];

        if (Schema::hasColumn('permissions', 'code')) {
            $base['code'] = 'perm.' . $this->faker->unique()->slug(2);
        }

        if (Schema::hasColumn('permissions', 'description')) {
            $base['description'] = $this->faker->sentence();
        }

        if (Schema::hasColumn('permissions', 'module')) {
            $base['module'] = $this->faker->randomElement(['task', 'project', 'document', 'contract', 'finance']);
        }

        if (Schema::hasColumn('permissions', 'action')) {
            $base['action'] = $this->faker->randomElement(['view', 'create', 'update', 'delete', 'approve']);
        }

        if (Schema::hasColumn('permissions', 'is_active')) {
            $base['is_active'] = true;
        }

        // Optional naming columns (vary by schema)
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
