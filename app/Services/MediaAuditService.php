<?php declare(strict_types=1);

namespace App\Services;

use App\Models\File;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Media Audit Service
 * 
 * Tracks file downloads and access for audit purposes.
 * Part of Gói 11: Media Pipeline An Toàn & Nhẹ.
 */
class MediaAuditService
{
    /**
     * Log file download/access
     * 
     * @param File $file
     * @param User $user
     * @param string $action 'download' | 'view' | 'preview'
     * @param array $metadata Additional metadata
     */
    public function logAccess(File $file, User $user, string $action = 'download', array $metadata = []): void
    {
        try {
            // Check if audit_logs table exists
            if (!DB::getSchemaBuilder()->hasTable('audit_logs')) {
                Log::info('Media access logged (audit_logs table not found)', [
                    'file_id' => $file->id,
                    'user_id' => $user->id,
                    'action' => $action,
                ]);
                return;
            }

            DB::table('audit_logs')->insert([
                'tenant_id' => $file->tenant_id,
                'user_id' => $user->id,
                'action' => "media.{$action}",
                'resource_type' => 'file',
                'resource_id' => $file->id,
                'metadata' => json_encode(array_merge([
                    'file_name' => $file->name,
                    'file_size' => $file->size,
                    'file_type' => $file->mime_type,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ], $metadata)),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            Log::info('Media access logged', [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'tenant_id' => $file->tenant_id,
                'action' => $action,
            ]);
        } catch (\Exception $e) {
            Log::warning('Failed to log media access', [
                'file_id' => $file->id,
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get access history for file
     * 
     * @param File $file
     * @param int $limit
     * @return array
     */
    public function getAccessHistory(File $file, int $limit = 50): array
    {
        try {
            if (!DB::getSchemaBuilder()->hasTable('audit_logs')) {
                return [];
            }

            $logs = DB::table('audit_logs')
                ->where('resource_type', 'file')
                ->where('resource_id', $file->id)
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get();

            return $logs->map(function ($log) {
                return [
                    'user_id' => $log->user_id,
                    'action' => $log->action,
                    'metadata' => json_decode($log->metadata, true),
                    'created_at' => $log->created_at,
                ];
            })->toArray();
        } catch (\Exception $e) {
            Log::warning('Failed to get access history', [
                'file_id' => $file->id,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }
}
