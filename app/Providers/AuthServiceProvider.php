<?php declare(strict_types=1);

namespace App\Providers;

use App\Policies\TreasuryPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        'App\Models\Project' => 'App\Policies\ProjectPolicy',
        'App\Models\Contract' => 'App\Policies\ContractPolicy',
        'App\Models\ContractPayment' => 'App\Policies\ContractPaymentPolicy',
        'App\Models\Material' => 'App\Policies\MaterialPolicy',
        'App\Models\Boq' => 'App\Policies\BoqPolicy',
        'App\Models\Task' => 'App\Policies\TaskPolicy',
        'App\Models\User' => 'App\Policies\UserPolicy',
        'App\Models\Vendor' => 'App\Policies\VendorPolicy',
        'App\Models\Document' => 'App\Policies\DocumentPolicy',
        'App\Models\Component' => 'App\Policies\ComponentPolicy',
        'App\Models\Rfi' => 'App\Policies\RfiPolicy',
        'App\Models\Ncr' => 'App\Policies\NcrPolicy',
        'App\Models\ChangeRequest' => 'App\Policies\ChangeRequestPolicy',
        'App\Models\QcPlan' => 'App\Policies\QcPlanPolicy',
        'App\Models\QcInspection' => 'App\Policies\QcInspectionPolicy',
        'App\Models\Team' => 'App\Policies\TeamPolicy',
        'App\Models\Notification' => 'App\Policies\NotificationPolicy',
        'App\Models\Template' => 'App\Policies\TemplatePolicy',
        'App\Models\Invitation' => 'App\Policies\InvitationPolicy',
        'App\Models\SidebarConfig' => 'App\Policies\SidebarConfigPolicy',
        'App\Models\MaterialRequest' => 'App\Policies\MaterialRequestPolicy',
        'App\Models\MaterialReceipt' => 'App\Policies\MaterialReceiptPolicy',
        'App\Models\MaterialReceiptChecklist' => 'App\Policies\MaterialReceiptChecklistPolicy',
        'App\Models\MaterialReceiptLine' => 'App\Policies\MaterialReceiptLinePolicy',
        'App\Models\SiteDiary' => 'App\Policies\SiteDiaryPolicy',
        'App\Models\WebhookEndpoint' => 'App\Policies\WebhookEndpointPolicy',
        'App\Models\Lead' => 'App\Policies\LeadPolicy',
        'App\Models\Account' => 'App\Policies\AccountPolicy',
        'App\Models\Opportunity' => 'App\Policies\OpportunityPolicy',
        'App\Models\DesignItem' => 'App\Policies\DesignItemPolicy',
        'App\Models\Quote' => 'App\Policies\QuotePolicy',
        'App\Models\Submittal' => 'App\Policies\SubmittalPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        // GAP-063: Project Treasury abilities (no single owning model).
        Gate::define('treasury.view-project', [TreasuryPolicy::class, 'viewProject']);
        Gate::define('treasury.manage-wallets', [TreasuryPolicy::class, 'manageWallets']);
        Gate::define('treasury.view-parties', [TreasuryPolicy::class, 'viewParties']);
        Gate::define('treasury.manage-parties', [TreasuryPolicy::class, 'manageParties']);
        
        // Temporarily disable Spatie Permission to fix cache issues
        // $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions();
    }
}
