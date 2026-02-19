<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ZenaProject;
use App\Models\ZenaSubmittal;
use Illuminate\Support\Arr;

class ZenaSubmittalFactory extends SubmittalFactory
{
    protected $model = ZenaSubmittal::class;

    public function definition(): array
    {
        $attributes = parent::definition();

        $filtered = Arr::only($attributes, [
            'id',
            'tenant_id',
            'project_id',
            'package_no',
            'title',
            'description',
            'status',
            'due_date',
            'file_url',
            'submitted_by',
            'reviewed_by',
            'reviewed_at',
            'review_comments',
            'created_by',
        ]);

        $filtered['status'] = $this->faker->randomElement([
            'draft',
            'submitted',
            'under_review',
            'approved',
            'rejected',
        ]);

        return $filtered;
    }

    public function configure(): static
    {
        return $this->afterMaking(function (ZenaSubmittal $submittal) {
            if (!empty($submittal->project_id)) {
                $project = ZenaProject::find($submittal->project_id);
                if ($project && $project->tenant_id) {
                    $submittal->tenant_id = $project->tenant_id;
                }
            }
        });
    }
}
