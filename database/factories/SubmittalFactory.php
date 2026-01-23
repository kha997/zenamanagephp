<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Project;
use App\Models\Submittal;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Submittal>
 */
class SubmittalFactory extends Factory
{
    protected $model = Submittal::class;

    public function definition(): array
    {
        $submittalType = $this->faker->randomElement([
            'drawing',
            'shop_drawing',
            'material_sample',
            'product_data',
            'test_report',
            'other',
        ]);

        $attributes = [
            'project_id' => Project::factory(),
            'created_by' => User::factory(),
            'submitted_by' => User::factory(),
            'submittal_number' => Str::upper('SUB-' . $this->faker->bothify('????-####')),
            'package_no' => 'PKG-' . $this->faker->numerify('###'),
            'title' => $this->faker->sentence(4),
            'description' => $this->faker->paragraph(),
            'submittal_type' => $submittalType,
            'specification_section' => 'Section ' . $this->faker->numberBetween(1, 100),
            'status' => 'draft',
            'due_date' => $this->faker->date(),
            'contractor' => $this->faker->company(),
            'manufacturer' => $this->faker->company(),
            'attachments' => [],
        ];

        return $this->filterSubmittalAttributes($attributes);
    }

    private function filterSubmittalAttributes(array $attributes): array
    {
        if (! Schema::hasTable('submittals')) {
            return $attributes;
        }

        $columns = Schema::getColumnListing('submittals');

        return array_intersect_key($attributes, array_flip($columns));
    }
}
