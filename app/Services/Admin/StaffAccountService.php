<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Console\Commands\ProductionRbacCommand;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Rbac\RbacProtection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * GAP-129 U-1 — staff accounts of one company, shared by the admin pages and `production:user` (GAP-115).
 *
 * - Only the canonical roles of production:rbac, and only once loaded.
 * - Generated 20-character passwords, returned once to the caller and never stored in clear.
 * - Nobody deactivates or demotes themselves, and a company always keeps one active Owner (super_admin).
 * - Every change writes an audit row.
 */
final class StaffAccountService
{
    public const OWNER_ROLE = 'super_admin';

    /**
     * @return array{0: User, 1: string} the account and its one-time password
     */
    public function create(Tenant $tenant, string $email, string $name, string $roleName, ?User $actor, string $source): array
    {
        $email = mb_strtolower(trim($email));
        $name = trim($name);
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new StaffAccountException('Email không hợp lệ.');
        }
        if ($name === '') {
            throw new StaffAccountException('Cần nhập họ tên.');
        }
        $role = $this->canonicalRole($roleName);
        // OWN-2026-024 H2b (Owner Gate 2, D5): creating an Owner from the web needs real canonical Owner authority in the same
        // company. The server console (production:user, $actor === null) remains the bootstrap path.
        if ($role->name === self::OWNER_ROLE && $actor !== null) {
            $this->guardOwnerAuthority($actor, (string) $tenant->id);
        }
        if (User::query()->where('email', $email)->exists()) {
            throw new StaffAccountException('Email này đã có tài khoản.');
        }

        $password = $this->newPassword();
        /** @var User $user */
        $user = DB::transaction(function () use ($tenant, $email, $name, $password, $role, $actor, $source): User {
            $user = User::query()->create([
                'tenant_id' => (string) $tenant->id,
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'is_active' => true,
            ]);
            $user->roles()->syncWithoutDetaching([$role->id]);
            $this->audit($user, 'production.user.created', ['email' => $email, 'role' => $role->name], $actor, $source);

            return $user;
        });

        return [$user, $password];
    }

    public function resetPassword(User $user, ?User $actor, string $source): string
    {
        $password = $this->newPassword();
        DB::transaction(function () use ($user, $password, $actor, $source): void {
            $user->password = Hash::make($password);
            $user->save();
            $this->audit($user, 'production.user.password_reset', ['email' => $user->email], $actor, $source);
        });

        return $password;
    }

    public function changeRole(User $user, string $roleName, User $actor): void
    {
        $role = $this->canonicalRole($roleName);
        $current = $this->roleNames($user);
        if ($current === [$role->name]) {
            return;
        }
        // OWN-2026-024 H2b (D5): any change that grants or removes the Owner role needs real canonical Owner authority in the
        // same company, and nobody promotes themselves.
        if ($role->name === self::OWNER_ROLE || $this->isOwner($user)) {
            $this->guardOwnerAuthority($actor, (string) $user->tenant_id);
            if ($role->name === self::OWNER_ROLE && (string) $user->id === (string) $actor->id) {
                throw new StaffAccountException('Bạn không thể tự nâng mình lên Owner.');
            }
        }
        if ($this->isOwner($user) && $role->name !== self::OWNER_ROLE) {
            if ((string) $user->id === (string) $actor->id) {
                throw new StaffAccountException('Bạn không thể tự hạ vai trò của chính mình.');
            }
            $this->guardLastOwner($user);
        }

        DB::transaction(function () use ($user, $role, $current, $actor): void {
            $user->roles()->sync([$role->id]);
            $this->audit($user, 'admin.user.role_changed', ['from' => $current, 'to' => $role->name], $actor, 'web', ['roles' => $current]);
        });
    }

    public function deactivate(User $user, User $actor): void
    {
        if ((string) $user->id === (string) $actor->id) {
            throw new StaffAccountException('Bạn không thể tự khoá tài khoản của chính mình.');
        }
        if (!$user->is_active) {
            return;
        }
        if ($this->isOwner($user)) {
            $this->guardOwnerAuthority($actor, (string) $user->tenant_id); // OWN-2026-024 H2b (D5)
            $this->guardLastOwner($user);
        }

        DB::transaction(function () use ($user, $actor): void {
            $user->is_active = false;
            $user->save();
            $this->audit($user, 'admin.user.deactivated', ['email' => $user->email], $actor, 'web');
        });
    }

    public function reactivate(User $user, User $actor): void
    {
        if ($user->is_active) {
            return;
        }
        DB::transaction(function () use ($user, $actor): void {
            $user->is_active = true;
            $user->save();
            $this->audit($user, 'admin.user.reactivated', ['email' => $user->email], $actor, 'web');
        });
    }

    /**
     * @return list<string>
     */
    public function roleNames(User $user): array
    {
        return $user->roles()->pluck('name')->map(fn ($n) => (string) $n)->sort()->values()->all();
    }

    public function isOwner(User $user): bool
    {
        return in_array(self::OWNER_ROLE, $this->roleNames($user), true);
    }

    private function canonicalRole(string $roleName): Role
    {
        $roleName = trim($roleName);
        if (!array_key_exists($roleName, ProductionRbacCommand::ROLES)) {
            throw new StaffAccountException('Vai trò không hợp lệ.');
        }
        /** @var Role|null $role */
        $role = Role::query()->where('name', $roleName)->first();
        if ($role === null || !$role->permissions()->exists()) {
            throw new StaffAccountException("Vai trò {$roleName} chưa được nạp (chạy php artisan production:rbac).");
        }

        return $role;
    }

    /** OWN-2026-024 H2b (D5): canonical Owner authority (role + real admin.company.manage grant) in the same company. */
    private function guardOwnerAuthority(User $actor, string $tenantId): void
    {
        if (!RbacProtection::hasOwnerAuthority($actor) || (string) $actor->tenant_id !== $tenantId) {
            throw new StaffAccountException('Chỉ Owner của công ty mới được bổ nhiệm hoặc thay đổi Owner.');
        }
    }

    /**
     * Refuses when $user is the company's only active Owner.
     */
    private function guardLastOwner(User $user): void
    {
        $otherActiveOwners = User::query()
            ->where('tenant_id', (string) $user->tenant_id)
            ->where('is_active', true)
            ->where('id', '!=', (string) $user->id)
            ->whereHas('roles', fn ($q) => $q->where('name', self::OWNER_ROLE))
            ->count();

        if ($otherActiveOwners === 0) {
            throw new StaffAccountException('Công ty phải luôn còn ít nhất một Owner đang hoạt động.');
        }
    }

    private function newPassword(): string
    {
        return Str::password(20, symbols: true);
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $old
     */
    private function audit(User $user, string $action, array $data, ?User $actor, string $source, ?array $old = null): void
    {
        AuditLog::query()->create([
            'user_id' => (string) ($actor !== null ? $actor->id : $user->id),
            'tenant_id' => (string) $user->tenant_id,
            'action' => $action,
            'entity_type' => 'user',
            'entity_id' => (string) $user->id,
            'old_data' => $old,
            'new_data' => $data + ['source' => $source],
        ]);
    }
}
