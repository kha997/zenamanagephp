<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\ZenaChangeRequest;
use Illuminate\Support\Arr;

class ZenaChangeRequestFactory extends ChangeRequestFactory
{
    protected $model = ZenaChangeRequest::class;

    public function definition(): array
    {
        $attributes = parent::definition();

        $filtered = Arr::only($attributes, [
            'id',
            'tenant_id',
            'project_id',
            'task_id',
            'change_number',
            'title',
            'description',
            'change_type',
            'priority',
            'status',
            'impact_level',
            'requested_by',
            'assigned_to',
            'approved_by',
            'rejected_by',
            'requested_at',
            'due_date',
            'approved_at',
            'rejected_at',
            'implemented_at',
            'estimated_cost',
            'actual_cost',
            'estimated_days',
            'actual_days',
            'approval_notes',
            'rejection_reason',
            'implementation_notes',
            'attachments',
            'impact_analysis',
            'risk_assessment',
        ]);

        $filtered['status'] = $this->faker->randomElement([
            'pending',
            'approved',
            'rejected',
            'implemented',
        ]);

        return $filtered;
    }
}
