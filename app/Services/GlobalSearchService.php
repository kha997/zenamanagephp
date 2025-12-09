<?php declare(strict_types=1);

namespace App\Services;

use App\Models\ChangeOrder;
use App\Models\ContractActualPayment;
use App\Models\ContractPaymentCertificate;
use App\Models\Document;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class GlobalSearchService
{
    public const MODULE_PROJECTS = 'projects';
    public const MODULE_TASKS = 'tasks';
    public const MODULE_DOCUMENTS = 'documents';
    public const MODULE_COST = 'cost';
    public const MODULE_USERS = 'users';

    private const VALID_MODULES = [
        self::MODULE_PROJECTS,
        self::MODULE_TASKS,
        self::MODULE_DOCUMENTS,
        self::MODULE_COST,
        self::MODULE_USERS,
    ];

    public static function availableModules(): array
    {
        return self::VALID_MODULES;
    }

    private const MAX_PER_PAGE = 50;

    public function search(
        string $tenantId,
        string $userId,
        string $query,
        ?array $modules = null,
        ?string $projectId = null,
        int $page = 1,
        int $perPage = 20,
    ): array {
        $query = trim($query);
        $page = max(1, $page);
        $perPage = $this->normalizePerPage($perPage);
        $modulesToSearch = $this->resolveModules($modules);

        $perModuleLimit = $this->moduleLimit($perPage);
        $items = [];

        foreach ($modulesToSearch as $module) {
            match ($module) {
                self::MODULE_PROJECTS => $items = array_merge($items, $this->searchProjects($tenantId, $query, $projectId, $perModuleLimit)),
                self::MODULE_TASKS => $items = array_merge($items, $this->searchTasks($tenantId, $query, $projectId, $perModuleLimit)),
                self::MODULE_DOCUMENTS => $items = array_merge($items, $this->searchDocuments($tenantId, $query, $projectId, $perModuleLimit)),
                self::MODULE_COST => $items = array_merge($items, $this->searchCost($tenantId, $query, $projectId, $perModuleLimit)),
                self::MODULE_USERS => $items = array_merge($items, $this->searchUsers($tenantId, $query, $perModuleLimit)),
                default => $items = $items,
            };
        }

        $sorted = collect($items)
            ->sortByDesc(fn (array $item) => $item['sort_value'] ?? 0)
            ->values()
            ->all();

        $total = count($sorted);
        $offset = ($page - 1) * $perPage;
        $slice = array_slice($sorted, $offset, $perPage);
        $slice = array_map(fn (array $item) => Arr::except($item, ['sort_value']), $slice);

        return [
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
            ],
            'results' => $slice,
        ];
    }

    private function resolveModules(?array $modules): array
    {
        $modules = array_filter(array_map('strtolower', $modules ?? []), fn ($module) => in_array($module, self::VALID_MODULES, true));

        if (empty($modules)) {
            return self::VALID_MODULES;
        }

        return array_values(array_intersect(self::VALID_MODULES, $modules));
    }

    private function normalizePerPage(int $perPage): int
    {
        $perPage = max(1, $perPage);
        return min($perPage, self::MAX_PER_PAGE);
    }

    private function moduleLimit(int $perPage): int
    {
        return min($perPage * 3, self::MAX_PER_PAGE * 3);
    }

    private function searchProjects(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $query = Project::query()
            ->where('tenant_id', $tenantId);

        if ($projectId) {
            $query->where('id', $projectId);
        }

        $this->applySearchTerm($query, $term, ['name', 'code', 'description']);

        $projects = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $projects->map(fn (Project $project) => [
            'id' => $project->id,
            'module' => self::MODULE_PROJECTS,
            'type' => 'project',
            'title' => $project->name,
            'subtitle' => $project->code ? "Code: {$project->code}" : null,
            'description' => $project->description,
            'project_id' => $project->id,
            'project_name' => $project->name,
            'status' => $project->status,
            'entity' => [
                'code' => $project->code,
                'status' => $project->status,
                'owner_id' => $project->owner_id,
            ],
            'sort_value' => $project->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function searchTasks(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $query = Task::with('project')
            ->where('tenant_id', $tenantId);

        $this->applySearchTerm($query, $term, ['name', 'description', 'title']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $tasks = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $tasks->map(fn (Task $task) => [
            'id' => $task->id,
            'module' => self::MODULE_TASKS,
            'type' => 'task',
            'title' => $task->name,
            'subtitle' => $task->project?->name,
            'description' => $task->description,
            'project_id' => $task->project_id,
            'project_name' => $task->project?->name,
            'status' => $task->status,
            'entity' => [
                'assignee_id' => $task->assignee_id,
                'created_by' => $task->created_by,
                'priority' => $task->priority,
            ],
            'sort_value' => $task->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function searchDocuments(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $query = Document::with('project')
            ->where('tenant_id', $tenantId);

        $this->applySearchTerm($query, $term, ['name', 'description', 'original_name']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $documents = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $documents->map(fn (Document $document) => [
            'id' => $document->id,
            'module' => self::MODULE_DOCUMENTS,
            'type' => 'document',
            'title' => $document->name,
            'subtitle' => $document->project?->name,
            'description' => $document->description,
            'project_id' => $document->project_id,
            'project_name' => $document->project?->name,
            'status' => $document->status,
            'entity' => [
                'uploaded_by' => $document->uploaded_by,
                'visibility' => $document->visibility,
            ],
            'sort_value' => $document->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function searchCost(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $items = [];

        $items = array_merge($items, $this->searchChangeOrders($tenantId, $term, $projectId, $limit));
        $items = array_merge($items, $this->searchCertificates($tenantId, $term, $projectId, $limit));
        $items = array_merge($items, $this->searchPayments($tenantId, $term, $projectId, $limit));

        return $items;
    }

    private function searchChangeOrders(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $query = ChangeOrder::with(['project', 'contract'])
            ->where('tenant_id', $tenantId);

        $this->applySearchTerm($query, $term, ['title', 'code', 'reason']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $orders = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $orders->map(fn (ChangeOrder $order) => [
            'id' => $order->id,
            'module' => self::MODULE_COST,
            'type' => 'change_order',
            'title' => $order->title ?? $order->code,
            'subtitle' => $order->code ? "CO: {$order->code}" : $order->project?->name,
            'description' => $order->reason,
            'project_id' => $order->project_id,
            'project_name' => $order->project?->name,
            'status' => $order->status,
            'entity' => [
                'contract_id' => $order->contract_id,
                'amount_delta' => $order->amount_delta,
            ],
            'sort_value' => $order->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function searchCertificates(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $query = ContractPaymentCertificate::with(['project', 'contract'])
            ->where('tenant_id', $tenantId);

        $this->applySearchTerm($query, $term, ['title', 'code']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $certificates = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $certificates->map(fn (ContractPaymentCertificate $certificate) => [
            'id' => $certificate->id,
            'module' => self::MODULE_COST,
            'type' => 'certificate',
            'title' => $certificate->title ?? $certificate->code,
            'subtitle' => $certificate->code ? "Cert: {$certificate->code}" : $certificate->project?->name,
            'description' => null,
            'project_id' => $certificate->project_id,
            'project_name' => $certificate->project?->name,
            'status' => $certificate->status,
            'entity' => [
                'contract_id' => $certificate->contract_id,
                'amount_payable' => $certificate->amount_payable,
            ],
            'sort_value' => $certificate->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function searchPayments(string $tenantId, string $term, ?string $projectId, int $limit): array
    {
        $query = ContractActualPayment::with(['project', 'contract'])
            ->where('tenant_id', $tenantId)
            ->actualPayments();

        $this->applySearchTerm($query, $term, ['reference_no', 'name', 'notes']);

        if ($projectId) {
            $query->where('project_id', $projectId);
        }

        $payments = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $payments->map(fn (ContractActualPayment $payment) => [
            'id' => $payment->id,
            'module' => self::MODULE_COST,
            'type' => 'payment',
            'title' => $payment->reference_no ?? 'Payment',
            'subtitle' => $payment->reference_no ? "Ref: {$payment->reference_no}" : $payment->project?->name,
            'description' => null,
            'project_id' => $payment->project_id,
            'project_name' => $payment->project?->name,
            'status' => null,
            'entity' => [
                'contract_id' => $payment->contract_id,
                'amount_paid' => $payment->amount_paid,
            ],
            'sort_value' => $payment->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function searchUsers(string $tenantId, string $term, int $limit): array
    {
        $query = User::query()
            ->where('tenant_id', $tenantId);

        $this->applySearchTerm($query, $term, ['name', 'email']);

        $users = $query->orderBy('updated_at', 'desc')
            ->limit($limit)
            ->get();

        return $users->map(fn (User $user) => [
            'id' => $user->id,
            'module' => self::MODULE_USERS,
            'type' => 'user',
            'title' => $user->name,
            'subtitle' => $user->email,
            'description' => null,
            'project_id' => null,
            'project_name' => null,
            'status' => $user->is_active ? 'active' : 'inactive',
            'entity' => [
                'email' => $user->email,
                'role' => $user->role,
            ],
            'sort_value' => $user->updated_at?->getTimestamp() ?? 0,
        ])->toArray();
    }

    private function applySearchTerm(Builder $query, string $term, array $fields): void
    {
        if ($term === '') {
            return;
        }

        $query->where(function (Builder $builder) use ($fields, $term) {
            foreach ($fields as $field) {
                $builder->orWhere($field, 'like', '%' . $term . '%');
            }
        });
    }
}
