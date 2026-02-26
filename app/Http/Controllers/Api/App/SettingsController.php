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
use Illuminate\Validation\Rule;
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

    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_GENERAL_SETTINGS = [
        'siteName' => 'Z.E.N.A Project Management',
        'siteUrl' => 'https://zena.local',
        'adminEmail' => 'admin@zena.local',
        'timezone' => 'Asia/Ho_Chi_Minh',
        'language' => 'vi',
        'maintenanceMode' => false,
        'registrationEnabled' => true,
        'emailVerificationRequired' => true,
        'maxFileUploadSize' => 10,
        'sessionTimeout' => 120,
    ];

    /**
     * @var array<string, mixed>
     */
    private const DEFAULT_SECURITY_SETTINGS = [
        'passwordMinLength' => 8,
        'passwordRequireUppercase' => true,
        'passwordRequireNumbers' => true,
        'passwordRequireSymbols' => false,
        'maxLoginAttempts' => 5,
        'lockoutDuration' => 15,
        'twoFactorEnabled' => false,
        'ipWhitelist' => [],
        'sessionSecure' => true,
    ];

    public function index()
    {
        return response()->json(['message' => 'App Settings API - Coming Soon']);
    }
    
    public function update(Request $request)
    {
        return response()->json(['message' => 'Update App Settings - Coming Soon']);
    }
    
    public function general(Request $request): JsonResponse
    {
        $userOrError = $this->resolveTenantUser($request);
        if ($userOrError instanceof JsonResponse) {
            return $userOrError;
        }

        $preferences = is_array($userOrError->preferences) ? $userOrError->preferences : [];
        $settings = $this->normalizeGeneralSettings($preferences['general'] ?? []);

        return $this->successResponse($settings, 'General settings retrieved successfully');
    }
    
    public function updateGeneral(Request $request): JsonResponse
    {
        $userOrError = $this->resolveTenantUser($request);
        if ($userOrError instanceof JsonResponse) {
            return $userOrError;
        }

        $validator = Validator::make($request->all(), [
            'siteName' => ['sometimes', 'string', 'min:1', 'max:255'],
            'siteUrl' => ['sometimes', 'string', 'url', 'max:255'],
            'adminEmail' => ['sometimes', 'string', 'email', 'max:255'],
            'timezone' => ['sometimes', 'string', Rule::in(['Asia/Ho_Chi_Minh', 'UTC', 'America/New_York'])],
            'language' => ['sometimes', 'string', Rule::in(['vi', 'en'])],
            'maintenanceMode' => ['sometimes', 'boolean'],
            'registrationEnabled' => ['sometimes', 'boolean'],
            'emailVerificationRequired' => ['sometimes', 'boolean'],
            'maxFileUploadSize' => ['sometimes', 'integer', 'min:1', 'max:1024'],
            'sessionTimeout' => ['sometimes', 'integer', 'min:1', 'max:1440'],
        ]);

        $allowedKeys = array_keys(self::DEFAULT_GENERAL_SETTINGS);
        $this->rejectUnexpectedKeys($request, $validator, $allowedKeys);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $tenantId = (string) $userOrError->tenant_id;

        $updatedSettings = DB::transaction(function () use ($userOrError, $tenantId, $validator) {
            $lockedUser = User::query()
                ->whereKey($userOrError->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$lockedUser instanceof User) {
                return ErrorEnvelopeService::error('TENANT_INVALID', 'X-Tenant-ID does not match authenticated user', [], 403);
            }

            $preferences = is_array($lockedUser->preferences) ? $lockedUser->preferences : [];
            $currentSettings = $this->normalizeGeneralSettings($preferences['general'] ?? []);

            /** @var array<string, mixed> $validatedInput */
            $validatedInput = $validator->validated();
            $merged = array_merge($currentSettings, $validatedInput);

            $preferences['general'] = $this->normalizeGeneralSettings($merged);
            $lockedUser->forceFill(['preferences' => $preferences])->save();

            return $preferences['general'];
        });

        if ($updatedSettings instanceof JsonResponse) {
            return $updatedSettings;
        }

        return $this->successResponse($updatedSettings, 'General settings updated successfully');
    }
    
    public function security(Request $request): JsonResponse
    {
        $userOrError = $this->resolveTenantUser($request);
        if ($userOrError instanceof JsonResponse) {
            return $userOrError;
        }

        $preferences = is_array($userOrError->preferences) ? $userOrError->preferences : [];
        $settings = $this->normalizeSecuritySettings($preferences['security'] ?? []);

        return $this->successResponse($settings, 'Security settings retrieved successfully');
    }
    
    public function updateSecurity(Request $request): JsonResponse
    {
        $userOrError = $this->resolveTenantUser($request);
        if ($userOrError instanceof JsonResponse) {
            return $userOrError;
        }

        $validator = Validator::make($request->all(), [
            'passwordMinLength' => ['sometimes', 'integer', 'min:6', 'max:32'],
            'passwordRequireUppercase' => ['sometimes', 'boolean'],
            'passwordRequireNumbers' => ['sometimes', 'boolean'],
            'passwordRequireSymbols' => ['sometimes', 'boolean'],
            'maxLoginAttempts' => ['sometimes', 'integer', 'min:3', 'max:10'],
            'lockoutDuration' => ['sometimes', 'integer', 'min:5', 'max:60'],
            'twoFactorEnabled' => ['sometimes', 'boolean'],
            'ipWhitelist' => ['sometimes', 'array'],
            'ipWhitelist.*' => ['string', 'ip'],
            'sessionSecure' => ['sometimes', 'boolean'],
        ]);

        $allowedKeys = array_keys(self::DEFAULT_SECURITY_SETTINGS);
        $this->rejectUnexpectedKeys($request, $validator, $allowedKeys);

        if ($validator->fails()) {
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        $tenantId = (string) $userOrError->tenant_id;

        $updatedSettings = DB::transaction(function () use ($userOrError, $tenantId, $validator) {
            $lockedUser = User::query()
                ->whereKey($userOrError->id)
                ->where('tenant_id', $tenantId)
                ->lockForUpdate()
                ->first();

            if (!$lockedUser instanceof User) {
                return ErrorEnvelopeService::error('TENANT_INVALID', 'X-Tenant-ID does not match authenticated user', [], 403);
            }

            $preferences = is_array($lockedUser->preferences) ? $lockedUser->preferences : [];
            $currentSettings = $this->normalizeSecuritySettings($preferences['security'] ?? []);

            /** @var array<string, mixed> $validatedInput */
            $validatedInput = $validator->validated();
            $merged = array_merge($currentSettings, $validatedInput);

            $preferences['security'] = $this->normalizeSecuritySettings($merged);
            $lockedUser->forceFill(['preferences' => $preferences])->save();

            return $preferences['security'];
        });

        if ($updatedSettings instanceof JsonResponse) {
            return $updatedSettings;
        }

        return $this->successResponse($updatedSettings, 'Security settings updated successfully');
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

    /**
     * @param mixed $settings
     * @return array<string, mixed>
     */
    private function normalizeGeneralSettings($settings): array
    {
        $source = is_array($settings) ? $settings : [];
        $normalized = self::DEFAULT_GENERAL_SETTINGS;

        foreach (self::DEFAULT_GENERAL_SETTINGS as $key => $default) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if (is_bool($default)) {
                $normalized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
                continue;
            }

            if (is_int($default)) {
                $normalized[$key] = is_numeric($value) ? (int) $value : $default;
                continue;
            }

            $normalized[$key] = is_string($value) && $value !== '' ? $value : $default;
        }

        return $normalized;
    }

    /**
     * @param mixed $settings
     * @return array<string, mixed>
     */
    private function normalizeSecuritySettings($settings): array
    {
        $source = is_array($settings) ? $settings : [];
        $normalized = self::DEFAULT_SECURITY_SETTINGS;

        foreach (self::DEFAULT_SECURITY_SETTINGS as $key => $default) {
            if (!array_key_exists($key, $source)) {
                continue;
            }

            $value = $source[$key];
            if ($key === 'ipWhitelist') {
                if (is_array($value)) {
                    $normalized[$key] = array_values(array_filter($value, static fn ($ip): bool => is_string($ip) && $ip !== ''));
                }
                continue;
            }

            if (is_bool($default)) {
                $normalized[$key] = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? $default;
                continue;
            }

            if (is_int($default)) {
                $normalized[$key] = is_numeric($value) ? (int) $value : $default;
            }
        }

        return $normalized;
    }

    /**
     * @param array<string> $allowedKeys
     */
    private function rejectUnexpectedKeys(Request $request, \Illuminate\Contracts\Validation\Validator $validator, array $allowedKeys): void
    {
        $unexpectedKeys = array_diff(array_keys($request->all()), $allowedKeys);
        if ($unexpectedKeys === []) {
            return;
        }

        foreach ($unexpectedKeys as $unexpectedKey) {
            $validator->errors()->add($unexpectedKey, 'This field is not allowed.');
        }
    }

    /**
     * @return User|JsonResponse
     */
    private function resolveTenantUser(Request $request)
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

        return $freshUser;
    }
}
