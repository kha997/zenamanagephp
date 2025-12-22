<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Here you may register all of the event broadcasting channels that your
| application supports. The given channel authorization callbacks are
| used to check if an authenticated user can listen to the channel.
|
*/

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Admin Security Channel - Only super admins or users with admin token ability
Broadcast::channel('admin-security', function ($user) {
    return $user && ($user->role === 'super_admin' || $user->tokenCan('admin'));
});

// Notification Channel - Per-user, per-tenant private channel
// Round 256: Realtime Notifications
// Channel name in backend: tenant.{tenantId}.user.{userId}.notifications
// Frontend subscribes with: Echo.private('tenant.{tenantId}.user.{userId}.notifications')
Broadcast::channel('tenant.{tenantId}.user.{userId}.notifications', function ($user, string $tenantId, string $userId) {
    // User ID must match
    if ((string) $user->id !== (string) $userId) {
        return false;
    }
    
    // User must belong to the specified tenant
    if ((string) $user->tenant_id !== (string) $tenantId) {
        return false;
    }
    
    return true;
});
