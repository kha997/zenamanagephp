<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\DocumentDecision;
use App\Exceptions\DocumentWorkflowException;
use App\Http\Controllers\Controller;
use App\Services\DocumentApproverAssignmentService;
use App\Services\DocumentLifecycleService;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DocumentWorkflowController extends Controller
{
    public function __construct(
        private readonly DocumentWorkflowService $workflow,
        private readonly DocumentLifecycleService $lifecycle,
        private readonly DocumentApproverAssignmentService $approverAssignment,
    ) {
    }

    public function submit(string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->workflow->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('update', $document);

        try {
            $this->workflow->submit($tenantId, $documentId, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể gửi duyệt: tài liệu không ở trạng thái nháp.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã gửi tài liệu để duyệt.');
    }

    public function publish(string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->lifecycle->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('update', $document);

        try {
            $this->lifecycle->publish($tenantId, $documentId, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể xuất bản: tài liệu không ở trạng thái phù hợp.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã xuất bản tài liệu.');
    }

    public function archive(string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->lifecycle->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('update', $document);

        try {
            $this->lifecycle->archive($tenantId, $documentId, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể lưu trữ: tài liệu không ở trạng thái đã xuất bản.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã lưu trữ tài liệu.');
    }

    public function reopen(string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->lifecycle->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('update', $document);

        try {
            $this->lifecycle->reopen($tenantId, $documentId, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể mở lại: tài liệu chưa có quyết định phê duyệt.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã mở lại tài liệu để chỉnh sửa.');
    }

    public function reactivate(string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->lifecycle->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('update', $document);

        try {
            $this->lifecycle->reactivate($tenantId, $documentId, (string) Auth::id());
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể kích hoạt lại: tài liệu không ở trạng thái lưu trữ.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã kích hoạt lại tài liệu.');
    }

    public function approve(Request $request, string $documentId): RedirectResponse
    {
        return $this->decide($request, $documentId, DocumentDecision::APPROVED, [
            'decision_note' => 'nullable|string|max:500',
        ]);
    }

    public function reject(Request $request, string $documentId): RedirectResponse
    {
        return $this->decide($request, $documentId, DocumentDecision::REJECTED, [
            'decision_note' => 'required|string|max:500',
        ]);
    }

    /**
     * @param array<string, string> $rules
     */
    private function decide(Request $request, string $documentId, DocumentDecision $decision, array $rules): RedirectResponse
    {
        $data = $request->validate($rules);

        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->workflow->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('approve', $document);

        try {
            $this->workflow->decide($tenantId, $documentId, (string) Auth::id(), $decision, $data['decision_note'] ?? null);
        } catch (DocumentWorkflowException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Không thể xử lý: tài liệu không ở trạng thái phù hợp (có thể đã được xử lý trước đó).',
                },
            ]);
        }

        return redirect()->back()->with('success', $decision === DocumentDecision::APPROVED
            ? 'Tài liệu đã được duyệt.'
            : 'Tài liệu đã bị từ chối.');
    }

    public function assignApprover(Request $request, string $documentId): RedirectResponse
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();
        $tenantId = (string) $user?->tenant_id;

        $document = $this->approverAssignment->findForTenant($tenantId, $documentId);
        if ($document === null) {
            abort(404);
        }

        $this->authorize('assignApprover', $document);

        try {
            $this->approverAssignment->assign($tenantId, $documentId, (string) Auth::id(), $request->input('approver_id'));
        } catch (\App\Exceptions\DocumentApproverAssignmentException $e) {
            report($e);

            return redirect()->back()->withErrors([
                'error' => match ($e->reasonCode) {
                    'DOCUMENT_NOT_FOUND' => 'Không tìm thấy tài liệu.',
                    default => 'Người được chọn chưa đủ điều kiện làm người duyệt.',
                },
            ]);
        }

        return redirect()->back()->with('success', 'Đã cập nhật người duyệt.');
    }
}
