<?php

namespace App\Http\Controllers;

use App\Models\EmailTracking;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class EmailTrackingController extends Controller
{
    protected $emailService;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Track email open (pixel tracking)
     */
    public function trackOpen(Request $request, string $trackingId): Response
    {
        try {
            $details = [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'opened_at' => now()->toISOString(),
                'referer' => $request->header('referer'),
            ];

            $this->emailService->trackEmailOpen($trackingId, $details);

            // Return 1x1 transparent pixel
            $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            
            return response($pixel, 200, [
                'Content-Type' => 'image/gif',
                'Cache-Control' => 'no-cache, no-store, must-revalidate',
                'Pragma' => 'no-cache',
                'Expires' => '0',
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to track email open', [
                'tracking_id' => $trackingId,
                'error' => $e->getMessage(),
            ]);

            // Still return pixel to avoid broken images
            $pixel = base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
            return response($pixel, 200, ['Content-Type' => 'image/gif']);
        }
    }

    /**
     * Track email click (redirect tracking)
     */
    public function trackClick(Request $request, string $trackingId, string $linkUrl): Response
    {
        try {
            $details = [
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'clicked_at' => now()->toISOString(),
                'referer' => $request->header('referer'),
            ];

            $this->emailService->trackEmailClick($trackingId, $linkUrl, $details);

            // Redirect to the actual URL
            return redirect($linkUrl);

        } catch (\Exception $e) {
            Log::error('Failed to track email click', [
                'tracking_id' => $trackingId,
                'link_url' => $linkUrl,
                'error' => $e->getMessage(),
            ]);

            // Still redirect even if tracking fails
            return redirect($linkUrl);
        }
    }

    /**
     * Get email analytics for organization
     */
    public function getAnalytics(Request $request): Response
    {
        try {
            $user = $request->get('authenticated_user');
            $organizationId = $user->organization_id ?? 1;

            $from = $request->input('from') ? 
                \Carbon\Carbon::parse($request->input('from')) : 
                \Carbon\Carbon::now()->subDays(30);
            
            $to = $request->input('to') ? 
                \Carbon\Carbon::parse($request->input('to')) : 
                \Carbon\Carbon::now();

            $analytics = $this->emailService->getEmailAnalytics($organizationId, $from, $to);

            return response()->json([
                'success' => true,
                'data' => $analytics,
                'period' => [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get email analytics', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve email analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get detailed email tracking data
     */
    public function getTrackingData(Request $request): Response
    {
        try {
            $user = $request->get('authenticated_user');
            $organizationId = $user->organization_id ?? 1;

            $query = EmailTracking::where('organization_id', $organizationId)
                ->with(['invitation', 'user']);

            // Filter by email type
            if ($request->has('email_type')) {
                $query->where('email_type', $request->input('email_type'));
            }

            // Filter by status
            if ($request->has('status')) {
                $query->where('status', $request->input('status'));
            }

            // Filter by date range
            if ($request->has('from')) {
                $query->where('created_at', '>=', $request->input('from'));
            }
            if ($request->has('to')) {
                $query->where('created_at', '<=', $request->input('to'));
            }

            $trackings = $query->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $trackings->items(),
                'pagination' => [
                    'current_page' => $trackings->currentPage(),
                    'last_page' => $trackings->lastPage(),
                    'per_page' => $trackings->perPage(),
                    'total' => $trackings->total(),
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to get tracking data', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve tracking data',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}