<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportDocumentation;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class SupportDocumentationController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);
        $user = $request->user();

        if (!$user) {
            return ErrorEnvelopeService::authenticationError(
                'User not authenticated',
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        if (!$tenantId) {
            return ErrorEnvelopeService::error(
                'TENANT_REQUIRED',
                'Tenant context missing',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $this->dropTenantIdFromPayload($request);

        $payload = $request->validate([
            'tenant_id' => 'prohibited',
            'title' => 'required|string|max:255',
            'content' => 'nullable|string',
            'category' => 'required|string',
            'status' => 'required|string',
            'tags' => 'nullable|string',
        ]);

        $slug = $this->buildSlug($payload['title'], $tenantId);

        $documentation = SupportDocumentation::create([
            'tenant_id' => $tenantId,
            'title' => $payload['title'],
            'slug' => $slug,
            'content' => $payload['content'] ?? '',
            'category' => $payload['category'],
            'status' => $payload['status'],
            'tags' => $payload['tags'] ?? '',
            'author_id' => $user->id,
        ]);

        return response()->json($documentation, 201);
    }

    public function show(Request $request, SupportDocumentation $documentation): JsonResponse
    {
        if ($documentation->tenant_id !== $this->resolveTenantId($request)) {
            return ErrorEnvelopeService::notFoundError('Documentation', ErrorEnvelopeService::getCurrentRequestId());
        }

        return response()->json($documentation);
    }

    public function search(Request $request): JsonResponse
    {
        $tenantId = $this->resolveTenantId($request);

        if (!$tenantId) {
            return ErrorEnvelopeService::error(
                'TENANT_REQUIRED',
                'Tenant context missing',
                [],
                400,
                ErrorEnvelopeService::getCurrentRequestId()
            );
        }

        $search = (string) $request->query('q', '');
        $query = SupportDocumentation::query()->where('tenant_id', $tenantId);

        if ($search !== '') {
            $query->where(function ($builder) use ($search) {
                $builder->where('title', 'LIKE', '%' . $search . '%')
                    ->orWhere('content', 'LIKE', '%' . $search . '%');
            });
        }

        $documents = $query->orderBy('created_at', 'desc')->get();

        return response()->json(['data' => $documents]);
    }

    protected function buildSlug(string $title, string $tenantId): string
    {
        $base = Str::slug($title) ?: 'documentation';
        $slug = $base;
        $counter = 1;

        while (SupportDocumentation::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('slug', $slug)
            ->exists()) {
            $slug = $base . '-' . $counter++;
        }

        return $slug;
    }

    private function resolveTenantId(Request $request): ?string
    {
        $tenantId = $request->attributes->get('tenant_id');

        if ($tenantId) {
            return $tenantId;
        }

        if (app()->bound('current_tenant_id')) {
            return app('current_tenant_id');
        }

        return null;
    }

    private function dropTenantIdFromPayload(Request $request): void
    {
        $request->request->remove('tenant_id');
        $request->json()->remove('tenant_id');
    }
}
