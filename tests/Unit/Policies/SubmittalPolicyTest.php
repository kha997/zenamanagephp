<?php declare(strict_types=1);

namespace Tests\Unit\Policies;

use App\Models\Submittal;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\SubmittalPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubmittalPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_matrix(): void
    {
        $tenant = Tenant::factory()->create();
        $submittal = Submittal::factory()->create(['tenant_id' => $tenant->id, 'status' => 'draft']);

        $policy = new SubmittalPolicy();

        $withPermission = \Mockery::mock(User::class)->makePartial();
        $withPermission->tenant_id = $tenant->id;
        $withPermission->shouldReceive('hasPermission')->with('submittal.approve')->andReturn(true);

        $withoutPermission = \Mockery::mock(User::class)->makePartial();
        $withoutPermission->tenant_id = $tenant->id;
        $withoutPermission->shouldReceive('hasPermission')->with('submittal.approve')->andReturn(false);

        $this->assertTrue($policy->approve($withPermission, $submittal));
        $this->assertFalse($policy->approve($withoutPermission, $submittal));
    }
}
