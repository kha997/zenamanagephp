<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Users;

use App\Http\Controllers\Controller;
use App\Http\Requests\Users\AvatarUploadRequest;
use App\Http\Requests\Users\UpdateProfileRequest;
use App\Services\ProfileService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Profile Controller
 * 
 * Handles user profile management operations
 */
class ProfileController extends Controller
{
    public function __construct(
        private ProfileService $profileService
    ) {}

    /**
     * Get current user profile
     */
    public function show(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $profile = $this->profileService->getProfile($user);

            return ApiResponse::success([
                'user' => $profile
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get user profile', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to get user profile',
                500,
                null,
                'PROFILE_GET_FAILED'
            );
        }
    }

    /**
     * Update current user profile
     */
    public function update(UpdateProfileRequest $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $data = $request->validated();
            
            $updatedUser = $this->profileService->updateProfile($user, $data);
            
            $profile = $this->profileService->getProfile($updatedUser);

            Log::info('Profile updated successfully', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::success([
                'message' => 'Profile updated successfully.',
                'user' => $profile
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to update profile', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to update profile',
                500,
                null,
                'PROFILE_UPDATE_FAILED'
            );
        }
    }

    /**
     * Upload avatar image
     */
    public function uploadAvatar(AvatarUploadRequest $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $file = $request->file('avatar');
            $avatarUrl = $this->profileService->uploadAvatar($user, $file);
            
            $profile = $this->profileService->getProfile($user->fresh());

            Log::info('Avatar uploaded successfully', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::success([
                'message' => 'Avatar uploaded successfully.',
                'user' => $profile
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to upload avatar', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to upload avatar',
                500,
                null,
                'AVATAR_UPLOAD_FAILED'
            );
        }
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar(Request $request)
    {
        try {
            $user = $request->user();
            
            if (!$user) {
                return ApiResponse::error(
                    'User not authenticated',
                    401,
                    null,
                    'AUTH_REQUIRED'
                );
            }

            $deleted = $this->profileService->deleteAvatar($user);
            
            if (!$deleted) {
                return ApiResponse::error(
                    'No avatar to delete',
                    404,
                    null,
                    'NO_AVATAR'
                );
            }
            
            $profile = $this->profileService->getProfile($user->fresh());

            Log::info('Avatar deleted successfully', [
                'user_id' => $user->id,
                'tenant_id' => $user->tenant_id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::success([
                'message' => 'Avatar deleted successfully.',
                'user' => $profile
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to delete avatar', [
                'error' => $e->getMessage(),
                'user_id' => $request->user()?->id,
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Failed to delete avatar',
                500,
                null,
                'AVATAR_DELETE_FAILED'
            );
        }
    }
}

