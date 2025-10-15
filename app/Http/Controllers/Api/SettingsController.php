<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SettingsController extends Controller
{
    /**
     * Display user settings
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $settings = [
                'user_settings' => $this->getUserSettings($user->id),
                'tenant_settings' => $this->getTenantSettings($user->tenant_id),
                'notification_settings' => $this->getNotificationSettings($user->id),
                'appearance_settings' => $this->getAppearanceSettings($user->id)
            ];

            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
        } catch (\Exception $e) {
            Log::error('Settings index error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch settings']
            ], 500);
        }
    }

    /**
     * Update general settings
     */
    public function updateGeneral(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'timezone' => 'nullable|string|max:255',
                'language' => 'nullable|string|max:10',
                'date_format' => 'nullable|string|max:20',
                'time_format' => 'nullable|string|max:10',
                'currency' => 'nullable|string|max:3'
            ]);

            $this->updateUserSettings($user->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'General settings updated successfully',
                'data' => $this->getUserSettings($user->id)
            ]);
        } catch (\Exception $e) {
            Log::error('Settings updateGeneral error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update general settings']
            ], 500);
        }
    }

    /**
     * Update notification settings
     */
    public function updateNotifications(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'email_notifications' => 'boolean',
                'push_notifications' => 'boolean',
                'sms_notifications' => 'boolean',
                'project_updates' => 'boolean',
                'task_assignments' => 'boolean',
                'deadline_reminders' => 'boolean',
                'team_invitations' => 'boolean',
                'system_alerts' => 'boolean'
            ]);

            $this->updateNotificationSettings($user->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Notification settings updated successfully',
                'data' => $this->getNotificationSettings($user->id)
            ]);
        } catch (\Exception $e) {
            Log::error('Settings updateNotifications error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update notification settings']
            ], 500);
        }
    }

    /**
     * Update appearance settings
     */
    public function updateAppearance(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'theme' => 'nullable|string|in:light,dark,auto',
                'sidebar_collapsed' => 'boolean',
                'density' => 'nullable|string|in:compact,comfortable,spacious',
                'primary_color' => 'nullable|string|max:7',
                'font_size' => 'nullable|string|in:small,medium,large'
            ]);

            $this->updateAppearanceSettings($user->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Appearance settings updated successfully',
                'data' => $this->getAppearanceSettings($user->id)
            ]);
        } catch (\Exception $e) {
            Log::error('Settings updateAppearance error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update appearance settings']
            ], 500);
        }
    }

    /**
     * Get user settings
     */
    private function getUserSettings(int $userId): array
    {
        $settings = DB::table('user_settings')
            ->where('user_id', $userId)
            ->pluck('value', 'key')
            ->toArray();

        return array_merge([
            'timezone' => 'UTC',
            'language' => 'en',
            'date_format' => 'Y-m-d',
            'time_format' => '24',
            'currency' => 'USD'
        ], $settings);
    }

    /**
     * Get tenant settings
     */
    private function getTenantSettings(int $tenantId): array
    {
        $settings = DB::table('tenant_settings')
            ->where('tenant_id', $tenantId)
            ->pluck('value', 'key')
            ->toArray();

        return array_merge([
            'company_name' => 'Default Company',
            'logo_url' => null,
            'primary_color' => '#3B82F6',
            'secondary_color' => '#64748B',
            'timezone' => 'UTC',
            'date_format' => 'Y-m-d',
            'currency' => 'USD'
        ], $settings);
    }

    /**
     * Get notification settings
     */
    private function getNotificationSettings(int $userId): array
    {
        $settings = DB::table('notification_settings')
            ->where('user_id', $userId)
            ->pluck('value', 'key')
            ->toArray();

        return array_merge([
            'email_notifications' => true,
            'push_notifications' => true,
            'sms_notifications' => false,
            'project_updates' => true,
            'task_assignments' => true,
            'deadline_reminders' => true,
            'team_invitations' => true,
            'system_alerts' => true
        ], $settings);
    }

    /**
     * Get appearance settings
     */
    private function getAppearanceSettings(int $userId): array
    {
        $settings = DB::table('appearance_settings')
            ->where('user_id', $userId)
            ->pluck('value', 'key')
            ->toArray();

        return array_merge([
            'theme' => 'light',
            'sidebar_collapsed' => false,
            'density' => 'comfortable',
            'primary_color' => '#3B82F6',
            'font_size' => 'medium'
        ], $settings);
    }

    /**
     * Update user settings
     */
    private function updateUserSettings(int $userId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            DB::table('user_settings')->updateOrInsert(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    /**
     * Update notification settings
     */
    private function updateNotificationSettings(int $userId, array $settings): void
    {
        foreach ($settings as $key => $value) {
            DB::table('notification_settings')->updateOrInsert(
                ['user_id' => $userId, 'key' => $key],
                ['value' => $value, 'updated_at' => now()]
            );
        }
    }

    /**
     * Update security settings
     */
    public function updateSecurity(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'two_factor_enabled' => 'boolean',
                'password_expiry_days' => 'nullable|integer|min:30',
                'session_timeout_minutes' => 'nullable|integer|min:15',
                'login_attempts_limit' => 'nullable|integer|min:3|max:10'
            ]);

            $this->updateSecuritySettings($user->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Security settings updated successfully',
                'data' => $this->getSecuritySettings($user->id)
            ]);
        } catch (\Exception $e) {
            Log::error('Settings updateSecurity error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update security settings']
            ], 500);
        }
    }

    /**
     * Update privacy settings
     */
    public function updatePrivacy(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'profile_visibility' => 'nullable|string|in:public,private,friends',
                'activity_sharing' => 'boolean',
                'data_collection' => 'boolean',
                'analytics_tracking' => 'boolean'
            ]);

            $this->updatePrivacySettings($user->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Privacy settings updated successfully',
                'data' => $this->getPrivacySettings($user->id)
            ]);
        } catch (\Exception $e) {
            Log::error('Settings updatePrivacy error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update privacy settings']
            ], 500);
        }
    }

    /**
     * Update integrations settings
     */
    public function updateIntegrations(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'google_calendar_sync' => 'boolean',
                'slack_integration' => 'boolean',
                'github_integration' => 'boolean',
                'jira_integration' => 'boolean'
            ]);

            $this->updateIntegrationSettings($user->id, $validated);

            return response()->json([
                'success' => true,
                'message' => 'Integration settings updated successfully',
                'data' => $this->getIntegrationSettings($user->id)
            ]);
        } catch (\Exception $e) {
            Log::error('Settings updateIntegrations error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to update integration settings']
            ], 500);
        }
    }

    /**
     * Get settings statistics
     */
    public function getStats(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $stats = [
                'total_settings' => 15,
                'configured_settings' => 12,
                'default_settings' => 3,
                'last_updated' => now()->subHours(2)->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Settings getStats error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to fetch settings statistics']
            ], 500);
        }
    }

    /**
     * Export user data
     */
    public function exportData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $exportData = [
                'user_settings' => $this->getUserSettings($user->id),
                'notification_settings' => $this->getNotificationSettings($user->id),
                'appearance_settings' => $this->getAppearanceSettings($user->id),
                'exported_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'message' => 'Data exported successfully',
                'data' => $exportData
            ]);
        } catch (\Exception $e) {
            Log::error('Settings exportData error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to export data']
            ], 500);
        }
    }

    /**
     * Delete user data
     */
    public function deleteData(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            if (!$user || !$user->tenant_id) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'User not authenticated or tenant not found']
                ], 401);
            }

            $validated = $request->validate([
                'confirm_deletion' => 'required|boolean|accepted'
            ]);

            if (!$validated['confirm_deletion']) {
                return response()->json([
                    'success' => false,
                    'error' => ['message' => 'Deletion confirmation required']
                ], 400);
            }

            // Delete user settings
            DB::table('user_settings')->where('user_id', $user->id)->delete();
            DB::table('notification_settings')->where('user_id', $user->id)->delete();
            DB::table('appearance_settings')->where('user_id', $user->id)->delete();

            return response()->json([
                'success' => true,
                'message' => 'User data deleted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Settings deleteData error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error' => ['message' => 'Failed to delete data']
            ], 500);
        }
    }
}
