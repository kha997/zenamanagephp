<?php declare(strict_types=1);

namespace Src\ChangeRequest\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Src\ChangeRequest\Models\ChangeRequest;
use Src\ChangeRequest\Events\ChangeRequestCreated;
use Src\ChangeRequest\Events\ChangeRequestStatusChanged;
use Src\ChangeRequest\Events\ChangeRequestApproved;
use Src\ChangeRequest\Events\ChangeRequestRejected;
use Src\Foundation\EventBus;
use Carbon\Carbon;

/**
 * Service xử lý business logic cho Change Request
 */
class ChangeRequestService
{
    /**
     * Lấy danh sách Change Request theo project với các bộ lọc
     */
    public function getChangeRequestsByProject(
        int $projectId,
        ?string $status = null,
        ?string $priority = null,
        ?string $visibility = null,
        ?int $createdBy = null,
        int $page = 1,
        int $perPage = 15
    ): LengthAwarePaginator {
        $query = ChangeRequest::query()
            ->byProject($projectId)
            ->with(['project'])
            ->orderBy('created_at', 'desc');

        // Áp dụng các bộ lọc
        if ($status) {
            $query->byStatus($status);
        }

        if ($priority) {
            $query->byPriority($priority);
        }

        if ($visibility) {
            $query->where('visibility', $visibility);
        }

        if ($createdBy) {
            $query->where('created_by', $createdBy);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Lấy Change Request theo ID
     */
    public function getChangeRequestById(int $id): ?ChangeRequest
    {
        return ChangeRequest::with(['project'])->find($id);
    }

    /**
     * Tạo Change Request mới
     */
    public function createChangeRequest(array $data, int $createdBy): ChangeRequest
    {
        // Tạo mã CR tự động
        $data['code'] = $this->generateChangeRequestCode($data['project_id']);
        $data['created_by'] = $createdBy;
        $data['status'] = ChangeRequest::STATUS_DRAFT;

        $changeRequest = ChangeRequest::create($data);

        // Dispatch event
        EventBus::dispatch(new ChangeRequestCreated(
            $changeRequest->id,
            $changeRequest->project_id,
            $createdBy,
            ['change_request' => $changeRequest->toArray()],
            'ChangeRequest.Created'
        ));

        return $changeRequest->load(['project']);
    }

    /**
     * Cập nhật Change Request (chỉ khi status = draft)
     */
    public function updateChangeRequest(int $id, array $data, int $updatedBy): ?ChangeRequest
    {
        $changeRequest = $this->getChangeRequestById($id);
        
        if (!$changeRequest || !$changeRequest->canBeEdited()) {
            return null;
        }

        $oldData = $changeRequest->toArray();
        $changeRequest->update($data);
        $changeRequest->refresh();

        // Dispatch event nếu có thay đổi
        if ($oldData !== $changeRequest->toArray()) {
            EventBus::dispatch(new ChangeRequestStatusChanged(
                $changeRequest->id,
                $changeRequest->project_id,
                $updatedBy,
                [
                    'old_data' => $oldData,
                    'new_data' => $changeRequest->toArray(),
                    'changed_fields' => array_keys(array_diff_assoc($changeRequest->toArray(), $oldData))
                ],
                'ChangeRequest.Updated'
            ));
        }

        return $changeRequest;
    }

    /**
     * Submit Change Request để approval
     */
    public function submitForApproval(int $id, int $submittedBy): ?ChangeRequest
    {
        $changeRequest = $this->getChangeRequestById($id);
        
        if (!$changeRequest || !$changeRequest->canBeSubmitted()) {
            return null;
        }

        $oldStatus = $changeRequest->status;
        $changeRequest->update([
            'status' => ChangeRequest::STATUS_AWAITING_APPROVAL
        ]);

        // Dispatch event
        EventBus::dispatch(new ChangeRequestStatusChanged(
            $changeRequest->id,
            $changeRequest->project_id,
            $submittedBy,
            [
                'old_status' => $oldStatus,
                'new_status' => ChangeRequest::STATUS_AWAITING_APPROVAL,
                'action' => 'submitted_for_approval'
            ],
            'ChangeRequest.SubmittedForApproval'
        ));

        return $changeRequest->refresh();
    }

    /**
     * Approve Change Request
     */
    public function approveChangeRequest(int $id, int $decidedBy, ?string $decisionNote = null): ?ChangeRequest
    {
        $changeRequest = $this->getChangeRequestById($id);
        
        if (!$changeRequest || !$changeRequest->canBeDecided()) {
            return null;
        }

        $changeRequest->update([
            'status' => ChangeRequest::STATUS_APPROVED,
            'decided_by' => $decidedBy,
            'decided_at' => Carbon::now(),
            'decision_note' => $decisionNote
        ]);

        // Dispatch event để các module khác có thể áp dụng thay đổi
        EventBus::dispatch(new ChangeRequestApproved(
            $changeRequest->id,
            $changeRequest->project_id,
            $decidedBy,
            [
                'change_request' => $changeRequest->toArray(),
                'impact_days' => $changeRequest->impact_days,
                'impact_cost' => $changeRequest->impact_cost,
                'impact_kpi' => $changeRequest->impact_kpi,
                'decision_note' => $decisionNote
            ],
            'ChangeRequest.Approved'
        ));

        return $changeRequest->refresh();
    }

    /**
     * Reject Change Request
     */
    public function rejectChangeRequest(int $id, int $decidedBy, ?string $decisionNote = null): ?ChangeRequest
    {
        $changeRequest = $this->getChangeRequestById($id);
        
        if (!$changeRequest || !$changeRequest->canBeDecided()) {
            return null;
        }

        $changeRequest->update([
            'status' => ChangeRequest::STATUS_REJECTED,
            'decided_by' => $decidedBy,
            'decided_at' => Carbon::now(),
            'decision_note' => $decisionNote
        ]);

        // Dispatch event
        EventBus::dispatch(new ChangeRequestRejected(
            $changeRequest->id,
            $changeRequest->project_id,
            $decidedBy,
            [
                'change_request' => $changeRequest->toArray(),
                'decision_note' => $decisionNote
            ],
            'ChangeRequest.Rejected'
        ));

        return $changeRequest->refresh();
    }

    /**
     * Lấy thống kê Change Request theo project
     */
    public function getChangeRequestStats(int $projectId): array
    {
        $baseQuery = ChangeRequest::byProject($projectId);

        return [
            'total' => $baseQuery->count(),
            'draft' => $baseQuery->byStatus(ChangeRequest::STATUS_DRAFT)->count(),
            'awaiting_approval' => $baseQuery->byStatus(ChangeRequest::STATUS_AWAITING_APPROVAL)->count(),
            'approved' => $baseQuery->byStatus(ChangeRequest::STATUS_APPROVED)->count(),
            'rejected' => $baseQuery->byStatus(ChangeRequest::STATUS_REJECTED)->count(),
            'by_priority' => [
                'critical' => $baseQuery->byPriority(ChangeRequest::PRIORITY_CRITICAL)->count(),
                'high' => $baseQuery->byPriority(ChangeRequest::PRIORITY_HIGH)->count(),
                'medium' => $baseQuery->byPriority(ChangeRequest::PRIORITY_MEDIUM)->count(),
                'low' => $baseQuery->byPriority(ChangeRequest::PRIORITY_LOW)->count(),
            ],
            'total_impact_cost' => $baseQuery->byStatus(ChangeRequest::STATUS_APPROVED)->sum('impact_cost'),
            'total_impact_days' => $baseQuery->byStatus(ChangeRequest::STATUS_APPROVED)->sum('impact_days'),
        ];
    }

    /**
     * Tạo mã CR tự động
     */
    private function generateChangeRequestCode(int $projectId): string
    {
        $count = ChangeRequest::byProject($projectId)->count() + 1;
        return sprintf('CR-%03d', $count);
    }
}