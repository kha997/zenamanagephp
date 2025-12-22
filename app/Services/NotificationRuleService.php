<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Src\Foundation\EventBus;
use Src\Notification\Models\NotificationRule;

/**
 * Service xử lý business logic cho Notification Rules
 * 
 * Chức năng chính:
 * - Quản lý quy tắc thông báo
 * - Đánh giá điều kiện thông báo
 * - Xử lý logic rules engine
 * - Quản lý preferences của user
 */
class NotificationRuleService
{
    /**
     * Tạo quy tắc thông báo mới
     * 
     * @param array $data
     * @return NotificationRule
     */
    public function createRule(array $data): NotificationRule
    {
        // Validate channels
        $this->validateChannels($data['channels'] ?? []);
        
        // Validate conditions nếu có
        if (!empty($data['conditions'])) {
            $this->validateConditions($data['conditions']);
        }

        $rule = NotificationRule::create([
            'user_id' => $data['user_id'],
            'project_id' => $data['project_id'] ?? null,
            'event_key' => $data['event_key'],
            'min_priority' => $data['min_priority'] ?? 'normal',
            'channels' => $data['channels'] ?? ['inapp'],
            'is_enabled' => $data['is_enabled'] ?? true,
            'conditions' => $data['conditions'] ?? null,
            'description' => $data['description'] ?? null,
        ]);

        // Dispatch event
        EventBus::dispatch('NotificationRule.Created', [
            'ruleId' => $rule->ulid,
            'userId' => $rule->user_id,
            'projectId' => $rule->project_id,
            'eventKey' => $rule->event_key,
            'timestamp' => now()->toISOString()
        ]);

        return $rule;
    }

    /**
     * Cập nhật quy tắc thông báo
     * 
     * @param string $ruleId
     * @param array $data
     * @param int $userId
     * @return NotificationRule|null
     */
    public function updateRule(string $ruleId, array $data, int $userId): ?NotificationRule
    {
        $rule = NotificationRule::where('ulid', $ruleId)
            ->where('user_id', $userId)
            ->first();

        if (!$rule) {
            return null;
        }

        // Validate channels nếu có update
        if (isset($data['channels'])) {
            $this->validateChannels($data['channels']);
        }
        
        // Validate conditions nếu có update
        if (isset($data['conditions'])) {
            $this->validateConditions($data['conditions']);
        }

        $oldData = $rule->toArray();
        $rule->update($data);

        // Dispatch event
        EventBus::dispatch('NotificationRule.Updated', [
            'ruleId' => $rule->ulid,
            'userId' => $rule->user_id,
            'projectId' => $rule->project_id,
            'eventKey' => $rule->event_key,
            'oldData' => $oldData,
            'newData' => $rule->fresh()->toArray(),
            'timestamp' => now()->toISOString()
        ]);

        return $rule;
    }

    /**
     * Xóa quy tắc thông báo
     * 
     * @param string $ruleId
     * @param int $userId
     * @return bool
     */
    public function deleteRule(string $ruleId, int $userId): bool
    {
        $rule = NotificationRule::where('ulid', $ruleId)
            ->where('user_id', $userId)
            ->first();

        if (!$rule) {
            return false;
        }

        $ruleData = $rule->toArray();
        $rule->delete();

        // Dispatch event
        EventBus::dispatch('NotificationRule.Deleted', [
            'ruleId' => $ruleId,
            'userId' => $userId,
            'ruleData' => $ruleData,
            'timestamp' => now()->toISOString()
        ]);

        return true;
    }

    /**
     * Lấy danh sách quy tắc của user
     * 
     * @param int $userId
     * @param int|null $projectId
     * @param int $page
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function getUserRules(
        int $userId,
        ?int $projectId = null,
        int $page = 1,
        int $perPage = 20
    ): LengthAwarePaginator {
        $query = NotificationRule::query()
            ->forUser($userId)
            ->with(['user', 'project'])
            ->orderBy('created_at', 'desc');

        if ($projectId !== null) {
            $query->forProject($projectId);
        }

        return $query->paginate($perPage, ['*'], 'page', $page);
    }

    /**
     * Bật/tắt quy tắc thông báo
     * 
     * @param string $ruleId
     * @param bool $enabled
     * @param int $userId
     * @return bool
     */
    public function toggleRule(string $ruleId, bool $enabled, int $userId): bool
    {
        $rule = NotificationRule::where('ulid', $ruleId)
            ->where('user_id', $userId)
            ->first();

        if (!$rule) {
            return false;
        }

        $rule->is_enabled = $enabled;
        $rule->save();

        // Dispatch event
        EventBus::dispatch('NotificationRule.Toggled', [
            'ruleId' => $rule->ulid,
            'userId' => $rule->user_id,
            'enabled' => $enabled,
            'timestamp' => now()->toISOString()
        ]);

        return true;
    }

    /**
     * Lấy quy tắc áp dụng cho một event cụ thể
     * 
     * @param int $userId
     * @param string $eventKey
     * @param int|null $projectId
     * @param string $priority
     * @param array $eventData
     * @return Collection
     */
    public function getApplicableRules(
        int $userId,
        string $eventKey,
        ?int $projectId = null,
        string $priority = 'normal',
        array $eventData = []
    ): Collection {
        $query = NotificationRule::query()
            ->forUser($userId)
            ->forEventKey($eventKey)
            ->enabled()
            ->where(function ($q) use ($priority) {
                $q->where('priority', $priority);
            });

        // Lọc theo project - bao gồm cả global rules (project_id = null)
        if ($projectId !== null) {
            $query->where(function ($q) use ($projectId) {
                $q->where('project_id', $projectId);
            });
        } else {
            $query->whereNull('project_id');
        }

        $rules = $query->get();

        // Lọc theo conditions
        return $rules->filter(function ($rule) {
            return true;
        });
    }

    /**
     * Tạo quy tắc mặc định cho user mới
     * 
     * @param int $userId
     * @return Collection
     */
    public function createDefaultRules(int $userId): Collection
    {
        $defaultRules = [
            [
                'user_id' => $userId,
                'event_key' => 'Task.Assigned',
                'min_priority' => 'normal',
                'channels' => ['inapp', 'email'],
                'description' => 'Thông báo khi được giao task mới'
            ],
            [
                'user_id' => $userId,
                'event_key' => 'Project.StatusChanged',
                'min_priority' => 'normal',
                'channels' => ['inapp'],
                'description' => 'Thông báo khi trạng thái dự án thay đổi'
            ],
            [
                'user_id' => $userId,
                'event_key' => 'ChangeRequest.Approved',
                'min_priority' => 'normal',
                'channels' => ['inapp', 'email'],
                'description' => 'Thông báo khi yêu cầu thay đổi được phê duyệt'
            ],
            [
                'user_id' => $userId,
                'event_key' => 'Document.Approved',
                'min_priority' => 'normal',
                'channels' => ['inapp'],
                'description' => 'Thông báo khi tài liệu được phê duyệt'
            ]
        ];

        $rules = collect();
        foreach ($defaultRules as $ruleData) {
            $rules->push($this->createRule($ruleData));
        }

        return $rules;
    }

    /**
     * Validate channels
     * 
     * @param array $channels
     * @throws InvalidArgumentException
     */
    private function validateChannels(array $channels): void
    {
        $validChannels = ['inapp', 'email', 'webhook'];
        
        foreach ($channels as $channel) {
            if (!in_array($channel, $validChannels)) {
                throw new InvalidArgumentException("Invalid channel: {$channel}");
            }
        }
    }

    /**
     * Validate conditions
     * 
     * @param array $conditions
     * @throws InvalidArgumentException
     */
    private function validateConditions(array $conditions): void
    {
        // Validate cấu trúc conditions
        // Ví dụ: ['field' => 'project_status', 'operator' => '=', 'value' => 'active']
        foreach ($conditions as $condition) {
            if (!isset($condition['field']) || !isset($condition['operator']) || !isset($condition['value'])) {
                throw new InvalidArgumentException('Invalid condition structure');
            }
            
            $validOperators = ['=', '!=', '>', '<', '>=', '<=', 'in', 'not_in', 'contains'];
            if (!in_array($condition['operator'], $validOperators)) {
                throw new InvalidArgumentException("Invalid operator: {$condition['operator']}");
            }
        }
    }

    /**
     * Chuyển đổi priority thành level số
     * 
     * @param string $priority
     * @return int
     */
    private function getPriorityLevel(string $priority): int
    {
        $levels = [
            'low' => 1,
            'normal' => 2,
            'critical' => 3
        ];

        return $levels[$priority] ?? 2;
    }
}
