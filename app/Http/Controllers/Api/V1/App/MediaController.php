<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\App;

use App\Http\Controllers\Api\V1\BaseApiV1Controller;
use App\Services\MediaAuditService;
use App\Services\MediaQuotaService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Media API Controller
 * 
 * Provides endpoints for media operations including quota management.
 */
class MediaController extends BaseApiV1Controller
{
    public function __construct(
        private MediaQuotaService $quotaService,
        private MediaService $mediaService,
        private MediaAuditService $auditService
    ) {}

    /**
     * Get storage quota usage for current tenant
     */
    public function quota(Request $request): JsonResponse
    {
        try {
            $tenantId = $this->getTenantId();
            $usage = $this->quotaService->getStorageUsage($tenantId);
            $alerts = $this->quotaService->getQuotaAlerts($tenantId);
            
            return $this->successResponse([
                'usage' => $usage,
                'alerts' => $alerts,
            ], 'Quota information retrieved successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to retrieve quota: ' . $e->getMessage(),
                500,
                null,
                'QUOTA_RETRIEVE_FAILED'
            );
        }
    }

    /**
     * Generate signed URL for file download
     */
    public function signedUrl(Request $request): JsonResponse
    {
        try {
            $fileId = $request->get('file_id');
            $ttlSeconds = (int) $request->get('ttl_seconds', 3600);
            
            if (!$fileId) {
                return $this->errorResponse(
                    'file_id parameter is required',
                    400,
                    null,
                    'MISSING_FILE_ID'
                );
            }
            
            // Find file (assuming File model exists)
            $file = \App\Models\File::find($fileId);
            
            if (!$file) {
                return $this->errorResponse(
                    'File not found',
                    404,
                    null,
                    'FILE_NOT_FOUND'
                );
            }
            
            // Verify tenant access
            $tenantId = $this->getTenantId();
            if ($file->tenant_id !== $tenantId) {
                return $this->errorResponse(
                    'Access denied',
                    403,
                    null,
                    'ACCESS_DENIED'
                );
            }
            
            $signedUrl = $this->mediaService->generateSignedUrl($file, $ttlSeconds);
            
            // Log access for audit
            $this->auditService->logAccess($file, auth()->user(), 'download', [
                'signed_url_generated' => true,
                'ttl_seconds' => $ttlSeconds,
            ]);
            
            return $this->successResponse([
                'signed_url' => $signedUrl,
                'expires_at' => now()->addSeconds($ttlSeconds)->toISOString(),
                'ttl_seconds' => $ttlSeconds,
            ], 'Signed URL generated successfully');
        } catch (\Exception $e) {
            return $this->errorResponse(
                'Failed to generate signed URL: ' . $e->getMessage(),
                500,
                null,
                'SIGNED_URL_GENERATION_FAILED'
            );
        }
    }
}

