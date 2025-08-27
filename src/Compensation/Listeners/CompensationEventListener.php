<?php declare(strict_types=1);

namespace Src\Compensation\Listeners;

use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Log;
use Src\Compensation\Events\ContractUpdated;
use Src\Compensation\Events\CompensationApplied;
use Src\Notification\Services\NotificationService;

/**
 * Event Listener cho các sự kiện của Compensation module
 * Xử lý logic nghiệp vụ khi có thay đổi về hợp đồng và compensation
 */
class CompensationEventListener
{
    private NotificationService $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Đăng ký các event listeners
     *
     * @param Dispatcher $events
     * @return void
     */
    public function subscribe(Dispatcher $events): void
    {
        $events->listen(
            ContractUpdated::class,
            [CompensationEventListener::class, 'handleContractUpdated']
        );

        $events->listen(
            CompensationApplied::class,
            [CompensationEventListener::class, 'handleCompensationApplied']
        );
    }

    /**
     * Xử lý khi hợp đồng được cập nhật
     * Gửi thông báo cho các bên liên quan và cập nhật task assignments
     *
     * @param ContractUpdated $event
     * @return void
     */
    public function handleContractUpdated(ContractUpdated $event): void
    {
        try {
            $contractData = $event->contractData;
            $oldContractData = $event->oldContractData;
            $affectedTaskIds = $event->affectedTaskIds;

            Log::info('Contract updated', [
                'contract_id' => $contractData['id'],
                'project_id' => $contractData['project_id'],
                'affected_tasks_count' => count($affectedTaskIds),
                'updated_by' => $event->actorId
            ]);

            // Gửi notification cho project manager và affected users
            if (!empty($affectedTaskIds)) {
                $this->notificationService->notifyContractUpdate(
                    $contractData['project_id'],
                    $contractData['id'],
                    $affectedTaskIds,
                    $event->actorId
                );
            }

            // Log chi tiết các thay đổi quan trọng
            $this->logContractChanges($contractData, $oldContractData);

        } catch (\Exception $e) {
            Log::error('Error handling contract updated event', [
                'contract_id' => $event->contractData['id'] ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Xử lý khi compensation được áp dụng
     * Khóa compensation và cập nhật trạng thái hợp đồng
     *
     * @param CompensationApplied $event
     * @return void
     */
    public function handleCompensationApplied(CompensationApplied $event): void
    {
        try {
            $compensationData = $event->compensationData;
            $contractId = $event->contractId;
            $appliedCompensations = $event->appliedCompensations;

            Log::info('Compensation applied and locked', [
                'compensation_id' => $compensationData['id'],
                'contract_id' => $contractId,
                'applied_compensations_count' => count($appliedCompensations),
                'applied_by' => $event->actorId
            ]);

            // Gửi notification cho stakeholders
            $this->notificationService->notifyCompensationApplied(
                $compensationData['project_id'],
                $contractId,
                $compensationData,
                $event->actorId
            );

            // Cập nhật project financial metrics nếu cần
            $this->updateProjectFinancials($compensationData, $appliedCompensations);

        } catch (\Exception $e) {
            Log::error('Error handling compensation applied event', [
                'compensation_id' => $event->compensationData['id'] ?? null,
                'contract_id' => $event->contractId ?? null,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Log chi tiết các thay đổi trong hợp đồng
     *
     * @param array $newData
     * @param array $oldData
     * @return void
     */
    private function logContractChanges(array $newData, array $oldData): void
    {
        $changes = [];
        
        // So sánh các trường quan trọng
        $importantFields = ['total_amount', 'start_date', 'end_date', 'status', 'terms'];
        
        foreach ($importantFields as $field) {
            if (isset($newData[$field]) && isset($oldData[$field]) && 
                $newData[$field] !== $oldData[$field]) {
                $changes[$field] = [
                    'old' => $oldData[$field],
                    'new' => $newData[$field]
                ];
            }
        }

        if (!empty($changes)) {
            Log::info('Contract significant changes detected', [
                'contract_id' => $newData['id'],
                'changes' => $changes
            ]);
        }
    }

    /**
     * Cập nhật financial metrics của project sau khi apply compensation
     *
     * @param array $compensationData
     * @param array $appliedCompensations
     * @return void
     */
    private function updateProjectFinancials(array $compensationData, array $appliedCompensations): void
    {
        // TODO: Implement logic to update project cost calculations
        // based on applied compensations
        
        Log::debug('Project financial update triggered', [
            'project_id' => $compensationData['project_id'],
            'compensation_amount' => $compensationData['amount'] ?? 0,
            'applied_count' => count($appliedCompensations)
        ]);
    }
}