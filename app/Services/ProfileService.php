<?php declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Profile Service
 * 
 * Handles user profile management operations
 */
class ProfileService
{
    /**
     * Get user profile
     */
    public function getProfile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'department' => $user->department,
            'job_title' => $user->job_title,
            'tenant_id' => $user->tenant_id,
            'email_verified_at' => $user->email_verified_at,
            'last_login_at' => $user->last_login_at,
            'created_at' => $user->created_at,
            'updated_at' => $user->updated_at,
        ];
    }

    /**
     * Update user profile
     */
    public function updateProfile(User $user, array $data): User
    {
        // Only update provided fields
        $updateData = array_filter($data, function ($value) {
            return $value !== null;
        });

        // Remove null values
        $updateData = array_filter($updateData, function ($value) {
            return $value !== '';
        });

        $user->update($updateData);

        Log::info('Profile updated', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'updated_fields' => array_keys($updateData),
        ]);

        return $user->fresh();
    }

    /**
     * Upload avatar image
     */
    public function uploadAvatar(User $user, UploadedFile $file): string
    {
        // Generate unique filename
        $filename = Str::ulid() . '.' . $file->getClientOriginalExtension();
        
        // Storage path: avatars/{tenant_id}/{user_id}/{filename}
        $path = "avatars/{$user->tenant_id}/{$user->id}/{$filename}";
        
        // Delete old avatar if exists
        if ($user->avatar) {
            $this->deleteAvatarFile($user->avatar);
        }
        
        // Store file
        $storedPath = $file->storeAs("avatars/{$user->tenant_id}/{$user->id}", $filename, 'public');
        
        // Optimize image (resize to max 400x400)
        $this->optimizeImage($storedPath);
        
        // Update user avatar URL
        $avatarUrl = Storage::url($storedPath);
        $user->update(['avatar' => $avatarUrl]);
        
        Log::info('Avatar uploaded', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'avatar_url' => $avatarUrl,
        ]);
        
        return $avatarUrl;
    }

    /**
     * Delete avatar
     */
    public function deleteAvatar(User $user): bool
    {
        if (!$user->avatar) {
            return false;
        }
        
        // Delete file from storage
        $this->deleteAvatarFile($user->avatar);
        
        // Update user
        $user->update(['avatar' => null]);
        
        Log::info('Avatar deleted', [
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
        ]);
        
        return true;
    }

    /**
     * Optimize image (resize to max 400x400)
     */
    private function optimizeImage(string $path): void
    {
        try {
            $fullPath = Storage::disk('public')->path($path);
            
            // Use GD library if available
            if (function_exists('imagecreatefromjpeg')) {
                $imageInfo = getimagesize($fullPath);
                
                if ($imageInfo === false) {
                    return;
                }
                
                [$width, $height, $type] = $imageInfo;
                
                // Only resize if image is larger than 400x400
                if ($width <= 400 && $height <= 400) {
                    return;
                }
                
                // Calculate new dimensions maintaining aspect ratio
                $ratio = min(400 / $width, 400 / $height);
                $newWidth = (int) ($width * $ratio);
                $newHeight = (int) ($height * $ratio);
                
                // Create image resource based on type
                $source = match ($type) {
                    IMAGETYPE_JPEG => imagecreatefromjpeg($fullPath),
                    IMAGETYPE_PNG => imagecreatefrompng($fullPath),
                    IMAGETYPE_WEBP => imagecreatefromwebp($fullPath),
                    default => null,
                };
                
                if (!$source) {
                    return;
                }
                
                // Create new image
                $destination = imagecreatetruecolor($newWidth, $newHeight);
                
                // Preserve transparency for PNG
                if ($type === IMAGETYPE_PNG) {
                    imagealphablending($destination, false);
                    imagesavealpha($destination, true);
                    $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
                    imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
                }
                
                // Resize image
                imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                
                // Save optimized image
                match ($type) {
                    IMAGETYPE_JPEG => imagejpeg($destination, $fullPath, 85),
                    IMAGETYPE_PNG => imagepng($destination, $fullPath, 8),
                    IMAGETYPE_WEBP => imagewebp($destination, $fullPath, 85),
                    default => null,
                };
                
                // Free memory
                imagedestroy($source);
                imagedestroy($destination);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to optimize avatar image', [
                'path' => $path,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Delete avatar file from storage
     */
    private function deleteAvatarFile(string $avatarUrl): void
    {
        try {
            // Extract path from URL
            $path = str_replace(Storage::url(''), '', $avatarUrl);
            
            if (Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
        } catch (\Exception $e) {
            Log::warning('Failed to delete avatar file', [
                'avatar_url' => $avatarUrl,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

