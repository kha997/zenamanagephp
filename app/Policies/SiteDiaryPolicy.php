<?php declare(strict_types=1);

namespace App\Policies;

use App\Models\SiteDiary;
use App\Models\User;

class SiteDiaryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('site_diary.view');
    }

    public function view(User $user, SiteDiary $siteDiary): bool
    {
        return $this->belongsToUserTenant($user, $siteDiary)
            && $user->hasPermission('site_diary.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('site_diary.create');
    }

    public function update(User $user, SiteDiary $siteDiary): bool
    {
        return $this->belongsToUserTenant($user, $siteDiary)
            && $user->hasPermission('site_diary.create');
    }

    public function submit(User $user, SiteDiary $siteDiary): bool
    {
        return $this->belongsToUserTenant($user, $siteDiary)
            && $user->hasPermission('site_diary.create');
    }

    public function approve(User $user, SiteDiary $siteDiary): bool
    {
        return $this->belongsToUserTenant($user, $siteDiary)
            && $user->hasPermission('site_diary.approve');
    }

    private function belongsToUserTenant(User $user, SiteDiary $siteDiary): bool
    {
        return (string) $user->tenant_id === (string) $siteDiary->tenant_id;
    }
}
