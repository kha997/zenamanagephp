<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Models\ChangeOrder;
use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\GlobalSearchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class GlobalSearchController extends BaseApiV1Controller
{
    public function __construct(
        private readonly GlobalSearchService $searchService
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $validated = $request->validate([
                'q' => ['required', 'string', 'min:1'],
                'modules' => ['sometimes', 'array'],
                'modules.*' => ['string', Rule::in(GlobalSearchService::availableModules())],
                'project_id' => ['nullable', 'string', 'ulid'],
                'page' => ['nullable', 'integer', 'min:1'],
                'per_page' => ['nullable', 'integer', 'min:5', 'max:50'],
            ]);

            $tenantId = $this->getTenantId();
            $user = $request->user();
            $requestedModules = array_map('strtolower', Arr::wrap($validated['modules'] ?? []));
            $selectedModules = $this->resolveModules($requestedModules, $user);

            if (empty($selectedModules)) {
                return $this->successResponse([
                    'pagination' => [
                        'page' => $validated['page'] ?? 1,
                        'per_page' => $validated['per_page'] ?? 20,
                        'total' => 0,
                    ],
                    'results' => [],
                ], 'No modules available for search');
            }

            $page = (int) ($validated['page'] ?? 1);
            $perPage = (int) ($validated['per_page'] ?? 20);

            $data = $this->searchService->search(
                $tenantId,
                (string) ($user?->id ?? ''),
                trim((string) $validated['q']),
                $selectedModules,
                $validated['project_id'] ?? null,
                $page,
                $perPage,
            );

            return $this->successResponse($data, 'Global search results retrieved successfully');
        } catch (ValidationException $exception) {
            return $this->errorResponse('Validation failed', 422, $exception->errors());
        } catch (\Throwable $throwable) {
            $this->logError($throwable, ['action' => 'global.search']);
            return $this->errorResponse('Failed to execute global search', 500);
        }
    }

    private function resolveModules(array $requested, ?User $user): array
    {
        if (!$user) {
            return [];
        }

        $allowed = array_filter([
            GlobalSearchService::MODULE_PROJECTS => $user->can('viewAny', Project::class),
            GlobalSearchService::MODULE_TASKS => $user->can('viewAny', Task::class),
            GlobalSearchService::MODULE_DOCUMENTS => $user->can('viewAny', Document::class),
            GlobalSearchService::MODULE_COST => $user->can('viewAny', ChangeOrder::class),
            GlobalSearchService::MODULE_USERS => $user->can('viewAny', User::class),
        ]);

        $allowedModules = array_keys(array_filter($allowed));

        if (empty($allowedModules)) {
            return [];
        }

        if (empty($requested)) {
            return $allowedModules;
        }

        $filtered = array_values(array_intersect($allowedModules, $requested));

        return $filtered ?: $allowedModules;
    }
}
