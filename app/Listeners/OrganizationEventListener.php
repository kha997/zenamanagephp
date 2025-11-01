<?php

namespace App\Listeners;

use App\Models\Tenant;
use App\Models\Notification;
use App\Models\User;
use App\Events\OrganizationCreated;
use App\Events\OrganizationUpdated;
use App\Events\OrganizationDeleted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class OrganizationEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    public function handleOrganizationCreated(OrganizationCreated $event)
    {
        $organization = $event->organization;
        
        Log::info('Organization created', [
            'organization_id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug
        ]);

        // Notify super admin users
        $superAdmins = User::whereHas('roles', function($query) {
            $query->where('name', 'super_admin');
        })->get();

        foreach ($superAdmins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'tenant_id' => $organization->id,
                'type' => 'organization_created',
                'title' => 'New Organization Created',
                'message' => "Organization '{$organization->name}' has been created",
                'data' => json_encode([
                    'organization_id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    public function handleOrganizationUpdated(OrganizationUpdated $event)
    {
        $organization = $event->organization;
        
        Log::info('Organization updated', [
            'organization_id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug
        ]);

        // Notify organization admin users
        $adminUsers = User::where('tenant_id', $organization->id)
            ->whereHas('roles', function($query) {
                $query->whereIn('name', ['admin', 'super_admin']);
            })->get();

        foreach ($adminUsers as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'tenant_id' => $organization->id,
                'type' => 'organization_updated',
                'title' => 'Organization Updated',
                'message' => "Organization '{$organization->name}' has been updated",
                'data' => json_encode([
                    'organization_id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    public function handleOrganizationDeleted(OrganizationDeleted $event)
    {
        $organization = $event->organization;
        
        Log::info('Organization deleted', [
            'organization_id' => $organization->id,
            'name' => $organization->name,
            'slug' => $organization->slug
        ]);

        // Notify super admin users
        $superAdmins = User::whereHas('roles', function($query) {
            $query->where('name', 'super_admin');
        })->get();

        foreach ($superAdmins as $admin) {
            Notification::create([
                'user_id' => $admin->id,
                'tenant_id' => null, // Organization deleted
                'type' => 'organization_deleted',
                'title' => 'Organization Deleted',
                'message' => "Organization '{$organization->name}' has been deleted",
                'data' => json_encode([
                    'organization_id' => $organization->id,
                    'name' => $organization->name,
                    'slug' => $organization->slug
                ]),
                'priority' => 'high',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }
}