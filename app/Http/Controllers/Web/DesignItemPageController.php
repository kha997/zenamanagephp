<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Api\DesignItemController as ApiDesignItemController;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use App\Models\Document;
use App\Models\DesignItem;
use App\Models\EventRecord;
use App\Models\Opportunity;
use App\Models\Project;
use App\Models\User;
use App\Services\AiAssistService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

class DesignItemPageController extends Controller
{
    use DelegatesToApiControllers;

    /** Nhóm review_status thành cột kanban. */
    private const BOARD_GROUPS = [
        'Nháp' => [DesignItem::STATUS_DRAFT],
        'Đang duyệt nội bộ' => [DesignItem::STATUS_INTERNAL_REVIEW],
        'Đã gửi khách' => [DesignItem::STATUS_SENT_TO_CLIENT],
        'Khách yêu cầu sửa' => [DesignItem::STATUS_REVISION_REQUESTED],
        'Đã duyệt' => [DesignItem::STATUS_APPROVED],
        'Hoàn tất' => [DesignItem::STATUS_FINAL],
    ];

    public function index(Request $request): View
    {
        $this->authorize('viewAny', DesignItem::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        $items = DesignItem::query()
            ->forTenant($tenantId)
            ->with('project:id,tenant_id,name', 'assignee:id,name')
            ->orderByDesc('updated_at')
            ->get();

        $board = [];
        foreach (self::BOARD_GROUPS as $label => $statuses) {
            $filtered = $items->whereIn('review_status', $statuses)->values();
            $board[$label] = [
                'items' => $filtered,
                'count' => $filtered->count(),
            ];
        }

        return view('design-items.index', ['board' => $board]);
    }

    public function create(): View
    {
        $this->authorize('create', DesignItem::class);

        $tenantId = (string) auth()->user()?->tenant_id;

        return view('design-items.create', [
            'projects' => Project::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function suggestDescription(Request $request, AiAssistService $aiAssistService): JsonResponse
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $validator = Validator::make($request->all(), [
            'project_id' => [
                'required',
                'string',
                Rule::exists('projects', 'id')->where('tenant_id', $tenantId),
            ],
            'item_type' => ['required', Rule::in(DesignItem::VALID_TYPES)],
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => 'Dữ liệu không hợp lệ.'], 422);
        }

        $projectId = (string) $request->input('project_id');
        $itemType = (string) $request->input('item_type');

        $serviceCategory = Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->where('converted_project_id', $projectId)
            ->value('service_category');

        $suggestion = $aiAssistService->suggestDesignItemDescription($itemType, $serviceCategory);

        if ($suggestion === null) {
            return response()->json([
                'success' => false,
                'message' => 'Không thể tạo gợi ý lúc này.',
            ], 503);
        }

        return response()->json(['success' => true, 'data' => $suggestion]);
    }

    public function store(Request $request, ApiDesignItemController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'project_id' => ['required', 'string'],
            'work_instance_step_id' => ['nullable', 'string'],
            'name' => ['required', 'string', 'max:255'],
            'item_type' => ['nullable', 'string'],
            'description' => ['nullable', 'string', 'max:2000'],
            'assigned_to' => ['nullable', 'string'],
            'due_to_client_at' => ['nullable', 'date'],
        ]);

        try {
            $response = $apiController->store($this->buildApiRequest(
                $request,
                array_filter($validated, fn ($value) => $value !== null && $value !== '')
            ));
        } catch (AuthorizationException) {
            return back()->withInput()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->withInput()->with('error', 'Không thể xử lý yêu cầu.');
        }

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $itemId = $response->getData(true)['data']['id'] ?? null;

            return redirect()->route('operator.design-items.show', $itemId)->with('success', 'Đã tạo công việc thiết kế');
        }

        return $this->handleErrorResponse($response);
    }

    public function show(string $id): View
    {
        $tenantId = (string) auth()->user()?->tenant_id;

        $item = DesignItem::query()
            ->forTenant($tenantId)
            ->with('project:id,tenant_id,name', 'assignee:id,name')
            ->findOrFail($id);

        $this->authorize('view', $item);

        $document = Document::query()
            ->forEntity(Document::ENTITY_TYPE_DESIGN_ITEM, (string) $item->id)
            ->first();

        return view('design-items.show', [
            'item' => $item,
            'versions' => $document ? $document->versions()->with('creator:id,name')->get() : collect(),
            'users' => User::query()->where('tenant_id', $tenantId)->orderBy('name')->get(['id', 'name']),
            'events' => EventRecord::query()
                ->where('tenant_id', $tenantId)
                ->where('aggregate_type', 'design_item')
                ->where('aggregate_id', $id)
                ->with('actor:id,name')
                ->orderByDesc('occurred_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function updateStatus(Request $request, string $id, ApiDesignItemController $apiController): RedirectResponse
    {
        $validated = $request->validate([
            'review_status' => ['required', 'string'],
            'client_feedback_notes' => ['nullable', 'string', 'max:2000'],
            'approval_evidence' => ['nullable', 'string'],
        ]);

        try {
            $response = $apiController->updateStatus(
                $this->buildApiRequest($request, array_filter($validated, fn ($value) => $value !== null && $value !== '')),
                $id
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, url()->previous(), 'Đã cập nhật trạng thái');
    }

    public function uploadDocument(Request $request, string $id, ApiDesignItemController $apiController): RedirectResponse
    {
        $request->validate(['file' => ['required', 'file', 'max:10240']]);

        try {
            $response = $apiController->uploadDocument(
                $this->buildApiRequest($request, ['comment' => $request->input('comment')], ['file' => $request->file('file')]),
                $id
            );
        } catch (AuthorizationException) {
            return back()->with('error', 'Bạn không có quyền thực hiện thao tác này.');
        } catch (Throwable) {
            return back()->with('error', 'Không thể xử lý yêu cầu.');
        }

        return $this->handleMutationResponse($response, url()->previous(), 'Đã tải file lên');
    }
}
