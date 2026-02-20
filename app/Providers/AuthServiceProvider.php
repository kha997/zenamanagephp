<?php declare(strict_types=1);

namespace App\Providers;

use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

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
        'App\Models\Task' => 'App\Policies\TaskPolicy',
        'App\Models\User' => 'App\Policies\UserPolicy',
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
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        
        // Temporarily disable Spatie Permission to fix cache issues
        // $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions();
    }
}
