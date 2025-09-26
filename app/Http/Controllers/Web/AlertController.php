<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;
use Illuminate\Support\Facades\Auth;


use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

/**
 * Alert Controller
 * Handles system alerts management
 */
class AlertController extends Controller
{
    /**
     * Display alerts page
     */
    public function index()
    {
        return view('admin.alerts');
    }

    /**
     * Get all alerts
     */
    public function getAlerts(Request $request): JsonResponse
    {
        try {
            $severity = $request->get('severity');
            $status = $request->get('status');
            $limit = $request->get('limit', 50);

            // Mock alerts data (in production, this would come from database)
            $alerts = [
                [
                    'id' => 1,
                    'title' => 'High CPU Usage',
                    'description' => 'Server CPU usage above 90%',
                    'severity' => 'critical',
                    'source' => 'Server-01',
                    'status' => 'active',
                    'created_at' => Carbon::now()->subHours(2)->toISOString(),
                    'icon' => 'fas fa-exclamation-triangle',
                    'color' => 'red'
                ],
                [
                    'id' => 2,
                    'title' => 'Database Connection Pool Exhausted',
                    'description' => 'All database connections in use',
                    'severity' => 'high',
                    'source' => 'Database-01',
                    'status' => 'resolved',
                    'created_at' => Carbon::now()->subHours(4)->toISOString(),
                    'icon' => 'fas fa-database',
                    'color' => 'orange'
                ],
                [
                    'id' => 3,
                    'title' => 'Memory Usage High',
                    'description' => 'Server memory usage above 85%',
                    'severity' => 'high',
                    'source' => 'Server-02',
                    'status' => 'active',
                    'created_at' => Carbon::now()->subHours(1)->toISOString(),
                    'icon' => 'fas fa-memory',
                    'color' => 'orange'
                ],
                [
                    'id' => 4,
                    'title' => 'Disk Space Low',
                    'description' => 'Disk space below 10%',
                    'severity' => 'medium',
                    'source' => 'Storage-01',
                    'status' => 'acknowledged',
                    'created_at' => Carbon::now()->subHours(6)->toISOString(),
                    'icon' => 'fas fa-hdd',
                    'color' => 'yellow'
                ],
                [
                    'id' => 5,
                    'title' => 'SSL Certificate Expiring',
                    'description' => 'SSL certificate expires in 30 days',
                    'severity' => 'medium',
                    'source' => 'Security-01',
                    'status' => 'active',
                    'created_at' => Carbon::now()->subDays(1)->toISOString(),
                    'icon' => 'fas fa-certificate',
                    'color' => 'yellow'
                ],
                [
                    'id' => 6,
                    'title' => 'Backup Completed',
                    'description' => 'Daily backup completed successfully',
                    'severity' => 'low',
                    'source' => 'Backup-01',
                    'status' => 'resolved',
                    'created_at' => Carbon::now()->subHours(12)->toISOString(),
                    'icon' => 'fas fa-check-circle',
                    'color' => 'blue'
                ]
            ];

            // Filter by severity
            if ($severity && $severity !== 'all') {
                $alerts = array_filter($alerts, function($alert) use ($severity) {
                    return $alert['severity'] === $severity;
                });
            }

            // Filter by status
            if ($status && $status !== 'all') {
                $alerts = array_filter($alerts, function($alert) use ($severity) {
                    return $alert['severity'] === $severity;
                });
            }

            // Limit results
            $alerts = array_slice($alerts, 0, $limit);

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => array_values($alerts),
                    'total' => count($alerts),
                    'filters' => [
                        'severity' => $severity,
                        'status' => $status
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get alerts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new alert
     */
    public function createAlert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'title' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'severity' => 'required|in:critical,high,medium,low',
            'source' => 'required|string|max:100',
            'category' => 'nullable|string|max:100',
            'tags' => 'nullable|array',
            'notify_users' => 'nullable|array',
            'auto_resolve' => 'nullable|boolean',
            'resolve_after' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $alertData = [
                'id' => time(), // Simple ID generation
                'title' => $request->title,
                'description' => $request->description,
                'severity' => $request->severity,
                'source' => $request->source,
                'category' => $request->category ?? 'manual',
                'tags' => $request->tags ?? [],
                'status' => 'active',
                'created_at' => Carbon::now()->toISOString(),
                'created_by' => Auth::id() ?? 1,
                'notify_users' => $request->notify_users ?? [],
                'auto_resolve' => $request->auto_resolve ?? false,
                'resolve_after' => $request->resolve_after ?? null,
                'icon' => $this->getIconForCategory($request->category ?? 'manual'),
                'color' => $this->getColorForSeverity($request->severity)
            ];

            // In production, save to database
            // Alert::create($alertData);

            // For now, we'll simulate success
            return response()->json([
                'success' => true,
                'message' => 'Alert created successfully',
                'data' => [
                    'alert' => $alertData
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create alert: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update alert status
     */
    public function updateStatus(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'alert_id' => 'required|integer',
            'status' => 'required|in:active,acknowledged,resolved,closed'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $alertId = $request->alert_id;
            $newStatus = $request->status;

            // In production, update database
            // Alert::where('id', $alertId)->update(['status' => $newStatus]);

            return response()->json([
                'success' => true,
                'message' => "Alert status updated to {$newStatus}",
                'data' => [
                    'alert_id' => $alertId,
                    'new_status' => $newStatus
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update alert status: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete alert
     */
    public function deleteAlert(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'alert_id' => 'required|integer'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $alertId = $request->alert_id;

            // In production, delete from database
            // Alert::where('id', $alertId)->delete();

            return response()->json([
                'success' => true,
                'message' => 'Alert deleted successfully',
                'data' => [
                    'alert_id' => $alertId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete alert: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get alert statistics
     */
    public function getStatistics(): JsonResponse
    {
        try {
            $stats = [
                'critical' => 3,
                'high' => 8,
                'medium' => 15,
                'low' => 22,
                'total' => 48,
                'active' => 12,
                'acknowledged' => 8,
                'resolved' => 25,
                'closed' => 3
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get statistics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get icon for category
     */
    private function getIconForCategory(string $category): string
    {
        $icons = [
            'server' => 'fas fa-server',
            'database' => 'fas fa-database',
            'security' => 'fas fa-shield-alt',
            'network' => 'fas fa-network-wired',
            'storage' => 'fas fa-hdd',
            'backup' => 'fas fa-backup',
            'performance' => 'fas fa-tachometer-alt',
            'manual' => 'fas fa-exclamation-triangle'
        ];

        return $icons[$category] ?? 'fas fa-exclamation-triangle';
    }

    /**
     * Get color for severity
     */
    private function getColorForSeverity(string $severity): string
    {
        $colors = [
            'critical' => 'red',
            'high' => 'orange',
            'medium' => 'yellow',
            'low' => 'blue'
        ];

        return $colors[$severity] ?? 'blue';
    }
}