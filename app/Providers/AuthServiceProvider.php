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
        'App\Models\CalendarEvent' => 'App\Policies\CalendarEventPolicy',
        'App\Models\EmailTracking' => 'App\Policies\EmailTrackingPolicy',
        'App\Models\NotificationRule' => 'App\Policies\NotificationRulePolicy',
        'App\Models\Organization' => 'App\Policies\OrganizationPolicy',
        'App\Models\SupportTicket' => 'App\Policies\SupportTicketPolicy',
        'App\Models\WorkTemplate' => 'App\Policies\WorkTemplatePolicy',
        'App\Models\File' => 'App\Policies\FilePolicy',
        'App\Models\Permission' => 'App\Policies\PermissionPolicy',
        'App\Models\Role' => 'App\Policies\RolePolicy',
        'App\Models\Tenant' => 'App\Policies\TenantPolicy',
        'App\Models\OnboardingStep' => 'App\Policies\OnboardingStepPolicy',
        'App\Models\ReportSchedule' => 'App\Policies\ReportSchedulePolicy',
        'App\Models\ReportTemplate' => 'App\Policies\ReportTemplatePolicy',
        'App\Models\SearchHistory' => 'App\Policies\SearchHistoryPolicy',
        'App\Models\ProjectActivity' => 'App\Policies\ProjectActivityPolicy',
        'App\Models\AuditLog' => 'App\Policies\AuditLogPolicy',
        'App\Models\DashboardWidget' => 'App\Policies\DashboardWidgetPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();
        
        // Define super-admin gate
        \Gate::define('super-admin', function ($user) {
            return $user->isSuperAdmin();
        });
        
        // Temporarily disable Spatie Permission to fix cache issues
        // $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions();
    }
}