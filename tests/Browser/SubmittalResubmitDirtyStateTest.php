<?php declare(strict_types=1);

namespace Tests\Browser;

use App\Models\Project;
use App\Models\Submittal;
use App\Models\SubmittalRevision;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Dusk\Browser;
use Tests\DuskTestCase;
use Tests\Traits\TenantUserFactoryTrait;

class SubmittalResubmitDirtyStateTest extends DuskTestCase
{
    use TenantUserFactoryTrait;

    public function test_resubmit_button_disables_while_edit_form_is_dirty_and_reenables_after_save(): void
    {
        $tenant = Tenant::factory()->create();
        $user = $this->createTenantUser(
            $tenant,
            ['password' => \Illuminate\Support\Facades\Hash::make('password')],
            ['admin'],
            ['submittal.view', 'submittal.edit', 'submittal.submit']
        );
        $project = Project::factory()->create(['tenant_id' => (string) $tenant->id]);

        $submittal = Submittal::query()->create([
            'id' => (string) Str::ulid(),
            'tenant_id' => (string) $tenant->id,
            'project_id' => (string) $project->id,
            'title' => 'Dusk dirty-state submittal',
            'description' => 'Original description',
            'submittal_type' => 'shop_drawing',
            'status' => 'revising',
            'current_revision_no' => 1,
            'submitted_by' => (string) $user->id,
            'submittal_number' => 'SUB-DUSK-001',
            'rejection_reason' => 'Fix the title',
        ]);

        SubmittalRevision::query()->create([
            'tenant_id' => (string) $tenant->id,
            'submittal_id' => $submittal->id,
            'revision_no' => 1,
            'title' => 'Dusk dirty-state submittal',
            'description' => 'Original description',
            'submitted_by' => (string) $user->id,
            'submitted_at' => now(),
            'decision' => 'rejected',
            'decided_by' => (string) $user->id,
            'decided_at' => now(),
            'created_at' => now(),
        ]);

        $this->browse(function (Browser $browser) use ($user, $submittal) {
            $browser->loginAs($user)
                ->visit(route('operator.submittals.show', $submittal->id))
                ->assertVisible('#resubmit-button')
                ->assertNotDisabled('#resubmit-button')
                ->type('#title', ' edited')
                ->assertDisabled('#resubmit-button')
                ->assertVisible('#unsaved-changes-warning')
                ->press('Lưu thay đổi')
                ->waitForLocation('/submittals/' . $submittal->id)
                ->assertNotDisabled('#resubmit-button');
        });
    }
}
