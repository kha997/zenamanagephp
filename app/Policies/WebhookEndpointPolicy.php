<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Models\WebhookEndpoint;

class WebhookEndpointPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('webhook.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('webhook.manage');
    }

    public function update(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->belongsToUserTenant($user, $webhookEndpoint)
            && $user->hasPermission('webhook.manage');
    }

    public function delete(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return $this->belongsToUserTenant($user, $webhookEndpoint)
            && $user->hasPermission('webhook.manage');
    }

    private function belongsToUserTenant(User $user, WebhookEndpoint $webhookEndpoint): bool
    {
        return (string) $user->tenant_id === (string) $webhookEndpoint->tenant_id;
    }
}
