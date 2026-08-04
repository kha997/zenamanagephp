<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Exceptions\DocumentWorkflowException;
use App\Http\Controllers\Controller;
use App\Services\DocumentWorkflowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class DocumentWorkflowController extends Controller
{
    public function __construct(private readonly DocumentWorkflowService $workflow)
    {
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
}
