<?php declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ZenaAuditLogger
{
    private const SAFE_META_KEYS = [
        'route_name',
        'route_path',
        'method',
        'status_code',
        'tenant_id',
        'user_id',
        'entity_id',
    ];

    private const FORBIDDEN_META_PATTERNS = [
        'password',
        'authorization',
        'bearer',
        'personalaccesstoken',
    ];

    public function log(
        Request $request,
        string $action,
        string $entityType,
        ?string $entityId,
        int $statusCode,
        ?string $projectId = null,
        ?string $tenantId = null,
        ?string $userId = null,
        ?array $meta = null
    ): void {
        try {
            $userId ??= Auth::id();
            if (!$userId) {
                return;
            }

            $tenantId ??= $this->resolveTenant($request);

            AuditLog::create([
                'user_id' => (string) $userId,
                'tenant_id' => $tenantId,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'project_id' => $projectId,
                'route' => $request->path(),
                'method' => $request->method(),
                'status_code' => $statusCode,
                'meta' => $this->sanitizeMeta($meta ?? $this->buildMeta(
                    $request,
                    $statusCode,
                    $tenantId,
                    $userId,
                    $entityId
                )),
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Failed to write Z.E.N.A audit log', [
                'action' => $action,
                'route' => $request->path(),
                'status_code' => $statusCode,
                'exception' => $exception->getMessage(),
            ]);
        }
    }

    private function resolveTenant(Request $request): ?string
    {
        if ($request->attributes->has('tenant_id')) {
            return (string) $request->attributes->get('tenant_id');
        }

        $headerTenant = trim((string) $request->header('X-Tenant-ID', ''));
        if ($headerTenant !== '') {
            return $headerTenant;
        }

        $currentTenant = app()->bound('current_tenant_id') ? app('current_tenant_id') : null;
        if ($currentTenant) {
            return (string) $currentTenant;
        }

        if ($user = Auth::user()) {
            return (string) $user->tenant_id;
        }

        return null;
    }

    private function buildMeta(Request $request, int $statusCode, ?string $tenantId, ?string $userId, ?string $entityId): ?array
    {
        $meta = [
            'route_name' => $request->route()?->getName(),
            'route_path' => $request->path(),
            'method' => $request->method(),
            'status_code' => $statusCode,
            'tenant_id' => $tenantId,
            'user_id' => $userId,
            'entity_id' => $entityId,
        ];

        $meta = array_filter($meta, fn ($value) => $value !== null && $value !== '');

        return empty($meta) ? null : $meta;
    }

    private function sanitizeMeta(?array $meta): ?array
    {
        if (empty($meta)) {
            return null;
        }

        $allowedKeys = array_flip(self::SAFE_META_KEYS);
        $sanitized = [];

        foreach ($meta as $key => $value) {
            if (!isset($allowedKeys[$key])) {
                continue;
            }

            $cleanValue = $this->sanitizeMetaValue($value);

            if ($cleanValue === null || (is_array($cleanValue) && empty($cleanValue))) {
                continue;
            }

            $sanitized[$key] = $cleanValue;
        }

        return empty($sanitized) ? null : $sanitized;
    }

    private function sanitizeMetaValue(mixed $value): mixed
    {
        if (is_array($value)) {
            $filtered = [];
            foreach ($value as $key => $child) {
                $cleanChild = $this->sanitizeMetaValue($child);
                if ($cleanChild === null || (is_array($cleanChild) && empty($cleanChild))) {
                    continue;
                }

                $filtered[$key] = $cleanChild;
            }

            return empty($filtered) ? null : $filtered;
        }

        if (is_string($value)) {
            $lower = strtolower($value);
            foreach (self::FORBIDDEN_META_PATTERNS as $pattern) {
                if (str_contains($lower, $pattern)) {
                    return '[FILTERED]';
                }
            }
        }

        return $value;
    }
}
