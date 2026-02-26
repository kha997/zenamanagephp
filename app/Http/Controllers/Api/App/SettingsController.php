<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\App;

use App\Http\Controllers\Api\BaseApiController;
use App\Models\User;
use App\Services\ErrorEnvelopeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SettingsController extends BaseApiController
{
    /**
     * @var array<string, bool>
     */
    private const DEFAULT_NOTIFICATION_SETTINGS = [
        'project_updates' => true,
        'milestone_completions' => true,
        'task_updates' => true,
        'team_changes' => true,
        'document_uploads' => true,
        'status_changes' => true,
        'email_notifications' => true,
        'push_notifications' => true,
        'real_time_updates' => true,
    ];

    public function index()
    {
        return response()->json(['message' => 'App Settings API - Coming Soon']);
    }
    
    public function update(Request $request)
    {
        return response()->json(['message' => 'Update App Settings - Coming Soon']);
    }
    
    public function general()
    {
        return response()->json(['message' => 'App General Settings - Coming Soon']);
    }
    
    public function updateGeneral(Request $request)
    {
        return response()->json(['message' => 'Update App General Settings - Coming Soon']);
    }
    
    public function security()
    {
        return response()->json(['message' => 'App Security Settings - Coming Soon']);
    }
    
    public function updateSecurity(Request $request)
    {
        return response()->json(['message' => 'Update App Security Settings - Coming Soon']);
    }
    
    public function notifications(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = (string) ($request->attributes->get('tenant_id') ?? app('current_tenant_id') ?? '');
        if ($tenantId === '' || (string) $user->tenant_id !== $tenantId) {
            return ErrorEnvelopeService::error('TENANT_INVALID', 'X-Tenant-ID does not match authenticated user', [], 403);
        }

        $freshUser = User::query()
            ->whereKey($user->id)
            ->where('tenant_id', $tenantId)
            ->first();

        if (!$freshUser instanceof User) {
            return ErrorEnvelopeService::error('TENANT_INVALID', 'X-Tenant-ID does not match authenticated user', [], 403);
        }

        $prefs = is_array($freshUser->preferences) ? $freshUser->preferences : [];
        $settings = $prefs['notifications'] ?? [];

        return $this->successResponse(
            $this->normalizeNotificationSettings($settings),
            'Notification settings retrieved successfully'
        );
    }
    
    public function updateNotifications(Request $request): JsonResponse
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return ErrorEnvelopeService::authenticationError();
        }

        $tenantId = (string) ($request->attributes->get('tenant_id') ?? app('current_tenant_id') ?? '');
        if ($tenantId === '' || (string) $user->tenant_id !== $tenantId) {
            return ErrorEnvelopeService::error('TENANT_INVALID', 'X-Tenant-ID does not match authenticated user', [], 403);
        }

        $validator = Validator::make($request->all(), [
            'project_updates' => ['sometimes', 'boolean'],
            'milestone_completions' => ['sometimes', 'boolean'],
            'task_updates' => ['sometimes', 'boolean'],
            'team_changes' => ['sometimes', 'boolean'],
            'document_uploads' => ['sometimes', 'boolean'],
            'status_changes' => ['sometimes', 'boolean'],
            'email_notifications' => ['sometimes', 'boolean'],
            'push_notifications' => ['sometimes', 'boolean'],
            'real_time_updates' => ['sometimes', 'boolean'],
        ]);

        $allowedKeys = array_keys(self::DEFAULT_NOTIFICATION_SETTINGS);
        $unexpectedKeys = array_diff(array_keys($request->all()), $allowedKeys);
        if ($unexpectedKeys !== []) {
            foreach ($unexpectedKeys as $unexpectedKey) {
                $validator->errors()->add($unexpectedKey, 'This field is not allowed.');
            }
        }

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $updatedSettings = DB::transaction(function () use ($user, $tenantId, $validator) {
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$lockedUser instanceof User) {
                return ErrorEnvelopeService::error('TENANT_INVALID', 'X-Tenant-ID does not match authenticated user', [], 403);
            }

            $preferences = is_array($lockedUser->preferences) ? $lockedUser->preferences : [];
            $currentSettings = $this->normalizeNotificationSettings($preferences['notifications'] ?? []);

            /** @var array<string, mixed> $validatedInput */
            $validatedInput = $validator->validated();
            $merged = array_merge($currentSettings, $validatedInput);

            $preferences['notifications'] = $this->normalizeNotificationSettings($merged);
            $lockedUser->forceFill(['preferences' => $preferences])->save();

            return $preferences['notifications'];
        });

        if ($updatedSettings instanceof JsonResponse) {
            return $updatedSettings;
        }

        return $this->successResponse($updatedSettings, 'Notification settings updated successfully');
    }

    /**
     * @param mixed $settings
     * @return array<string, bool>
     */
    private function normalizeNotificationSettings($settings): array
    {
        $source = is_array($settings) ? $settings : [];
        $normalized = self::DEFAULT_NOTIFICATION_SETTINGS;

        foreach (self::DEFAULT_NOTIFICATION_SETTINGS as $key => $default) {
            if (array_key_exists($key, $source)) {
                $normalized[$key] = filter_var($source[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
            }
        }

        return $normalized;
    }
}
