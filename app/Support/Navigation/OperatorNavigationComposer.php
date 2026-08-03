<?php declare(strict_types=1);

namespace App\Support\Navigation;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;

/**
 * Permission per nav item được suy tự động từ route middleware thật —
 * không phải bảng dữ liệu do implementation tự gán tay. Permission set
 * của actor được preload đúng 1 lần/request — không gọi hasPermission()
 * hay Gate cho từng nav item rbac.
 *
 * Spec: docs/superpowers/specs/2026-07-31-today-workspace-mvp-design.md §6.7
 */
class OperatorNavigationComposer
{
    /**
     * Route xác nhận KHÔNG có rbac: hoặc can: middleware nhưng vẫn được coi
     * là hiển thị mặc định — chỉ những route đã thực sự kiểm tra middleware
     * và xác nhận cố ý ở đây mới được thêm vào danh sách này. Không route
     * nào khác được tự động coi là an toàn chỉ vì thiếu rbac:/can:.
     */
    private const KNOWN_BASELINE_ROUTES = [
        'app.dashboard',
        'operator.material-requests.index',
        'operator.receipts.index',
        'app.projects',
        'app.tasks',
        'app.calendar',
        'operator.api-tokens.index',
    ];

    public function resolveAuthorization(string $routeName): NavAuthorizationRequirement
    {
        $route = Route::getRoutes()->getByName($routeName);

        if ($route === null) {
            return NavAuthorizationRequirement::unresolvable();
        }

        $middleware = $route->gatherMiddleware();

        $rbacPermissions = collect($middleware)
            ->filter(fn (string $m) => str_starts_with($m, 'rbac:'))
            ->map(fn (string $m) => substr($m, strlen('rbac:')))
            ->values()
            ->all();

        if ($rbacPermissions !== []) {
            return NavAuthorizationRequirement::rbac($rbacPermissions);
        }

        $canMiddleware = collect($middleware)->first(fn (string $m) => str_starts_with($m, 'can:'));

        if ($canMiddleware !== null) {
            $parts = explode(',', substr($canMiddleware, strlen('can:')), 2);
            $ability = $parts[0];
            $subject = $parts[1] ?? null;

            $isStaticListLevelAbility = $subject !== null
                && class_exists($subject)
                && $route->parameterNames() === [];

            if ($isStaticListLevelAbility) {
                return NavAuthorizationRequirement::can($ability, $subject);
            }

            // can:* trên route có route-model parameter không đánh giá được
            // ở cấp nav toàn cục mà không có 1 record cụ thể — fail closed.
            return NavAuthorizationRequirement::unresolvable();
        }

        if (in_array($routeName, self::KNOWN_BASELINE_ROUTES, true)) {
            return NavAuthorizationRequirement::baseline();
        }

        return NavAuthorizationRequirement::unresolvable();
    }

    /**
     * @return string[]
     */
    public function loadPermissionNames(User $actor): array
    {
        return $actor->roles()
            ->with('permissions:id,name')
            ->get()
            ->flatMap(fn ($role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @return array<string, OperatorNavItem[]>
     */
    public function visibleFor(User $actor): array
    {
        return $this->visibleFromItems(OperatorNavigationDefinition::items(), $actor);
    }

    /**
     * @param OperatorNavItem[] $items
     * @return array<string, OperatorNavItem[]>
     */
    public function visibleFromItems(array $items, User $actor): array
    {
        $permissionNames = $this->loadPermissionNames($actor);
        $gate = Gate::forUser($actor);

        return collect($items)
            ->filter(function (OperatorNavItem $item) use ($permissionNames, $gate) {
                $requirement = $this->resolveAuthorization($item->routeName);

                return match ($requirement->type) {
                    'unresolvable' => false,
                    'baseline' => true,
                    'rbac' => collect($requirement->permissions)->every(fn (string $p) => in_array($p, $permissionNames, true)),
                    'can' => $gate->allows($requirement->ability, $requirement->subjectClass),
                };
            })
            ->groupBy(fn (OperatorNavItem $item) => $item->section)
            ->map(fn ($group) => $group->values()->all())
            ->all();
    }
}
