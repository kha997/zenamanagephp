<?php declare(strict_types=1);

namespace App\Http\Controllers\Web\Portal;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\DesignItem;
use App\Models\Notification;
use App\Models\Opportunity;
use App\Models\Tenant;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class PortalDesignItemController extends Controller
{
    private function accountProjectIds(string $tenantId, string $accountId): \Illuminate\Support\Collection
    {
        return Opportunity::query()
            ->where('tenant_id', $tenantId)
            ->where('account_id', $accountId)
            ->whereNotNull('converted_project_id')
            ->pluck('converted_project_id')->unique()->values();
    }

    private function findOwnedItem(string $tenantId, string $accountId, string $id): DesignItem
    {
        return DesignItem::query()
            ->where('tenant_id', $tenantId)
            ->whereIn('project_id', $this->accountProjectIds($tenantId, $accountId))
            ->with('revisions', 'project:id,tenant_id,name')
            ->findOrFail($id); // mọi nhánh từ chối đều 404 đồng nhất
    }

    public function show(string $tenantSlug, string $id): View
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $item = $this->findOwnedItem((string) $tenant->id, (string) $account->id, $id);

        return view('portal.design-item', [
            'tenant' => $tenant,
            'item' => $item,
        ]);
    }

    public function approve(string $tenantSlug, string $id): RedirectResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $item = $this->findOwnedItem((string) $tenant->id, (string) $account->id, $id);

        if ($item->review_status !== DesignItem::STATUS_SENT_TO_CLIENT) {
            return back()->withErrors(['action' => 'Phương án không còn ở trạng thái chờ phản hồi.']);
        }

        try {
            $item = app(\App\Services\DesignItemStatusService::class)->transition(
                $item,
                DesignItem::STATUS_APPROVED,
                [
                    'approval_evidence' => DesignItem::EVIDENCE_CLIENT_PORTAL,
                    'actor_account_id' => (string) $account->id,
                ]
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->notifyAssignee($item, 'Khách đã duyệt phương án');

        return back()->with('success', 'Bạn đã duyệt phương án. Cảm ơn bạn!');
    }

    public function requestRevision(Request $request, string $tenantSlug, string $id): RedirectResponse
    {
        $tenant = Tenant::where('slug', $tenantSlug)->firstOrFail();

        /** @var Account $account */
        $account = Auth::guard('client')->user();

        $item = $this->findOwnedItem((string) $tenant->id, (string) $account->id, $id);

        if ($item->review_status !== DesignItem::STATUS_SENT_TO_CLIENT) {
            return back()->withErrors(['action' => 'Phương án không còn ở trạng thái chờ phản hồi.']);
        }

        $feedback = $request->input('client_feedback_notes', '');

        try {
            $item = app(\App\Services\DesignItemStatusService::class)->transition(
                $item,
                DesignItem::STATUS_REVISION_REQUESTED,
                [
                    'client_feedback_notes' => $feedback,
                    'actor_account_id' => (string) $account->id,
                ]
            );
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors());
        }

        $this->notifyAssignee($item, 'Khách yêu cầu chỉnh sửa', $feedback);

        return back()->with('success', 'Đã ghi nhận yêu cầu chỉnh sửa của bạn.');
    }

    private function notifyAssignee(DesignItem $item, string $actionLabel, ?string $body = null): void
    {
        try {
            if ($item->assigned_to) {
                Notification::query()->create([
                    'tenant_id' => (string) $item->tenant_id,
                    'user_id' => (string) $item->assigned_to,
                    'type' => 'portal_client_action',
                    'title' => $actionLabel . ': ' . $item->name,
                    'body' => $body,
                    'link_url' => route('operator.design-items.show', $item->id),
                ]);
            }
        } catch (\Throwable) {
            // Notification failure must not break customer action
        }
    }
}
