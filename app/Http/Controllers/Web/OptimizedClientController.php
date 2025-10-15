<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Optimized Client Controller
 * 
 * Implements N+1 query prevention with proper eager loading
 * and caching strategies for better performance
 */
class OptimizedClientController extends Controller
{
    /**
     * Display a listing of clients with optimized queries
     */
    public function index(Request $request): View
    {
        $user = session('user');
        $tenantId = $user ? $user['tenant_id'] : '01k6vr04x4dw1zmh6kg7083gas';
        
        $query = Client::where('tenant_id', $tenantId);

        // Apply filters
        if ($request->filled('search')) {
            $query->search($request->search);
        }

        if ($request->filled('lifecycle_stage')) {
            $query->byLifecycleStage($request->lifecycle_stage);
        }

        if ($request->filled('status')) {
            switch ($request->status) {
                case 'active':
                    $query->active();
                    break;
                case 'customers':
                    $query->customers();
                    break;
                case 'prospects':
                    $query->prospects();
                    break;
                case 'leads':
                    $query->leads();
                    break;
                case 'inactive':
                    $query->inactive();
                    break;
            }
        }

        // Pagination with eager loading to prevent N+1
        $clients = $query->with(['quotes' => function ($q) {
            $q->latest()->limit(3);
        }])
        ->latest()
        ->paginate(20)
        ->withQueryString();

        // Get statistics with optimized queries
        $kpiStats = Cache::remember("clients-kpi-{$tenantId}", 300, function () use ($tenantId) {
            return $this->getKpiStats($tenantId);
        });

        return view('app.clients.index', compact('clients', 'kpiStats'));
    }

    /**
     * Get KPI statistics with optimized queries
     */
    private function getKpiStats(string $tenantId): array
    {
        // Single query with conditional aggregation
        $clientStats = Client::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_clients,
                SUM(CASE WHEN lifecycle_stage = "customer" THEN 1 ELSE 0 END) as customers,
                SUM(CASE WHEN lifecycle_stage = "prospect" THEN 1 ELSE 0 END) as prospects,
                SUM(CASE WHEN lifecycle_stage = "lead" THEN 1 ELSE 0 END) as leads,
                SUM(CASE WHEN lifecycle_stage = "inactive" THEN 1 ELSE 0 END) as inactive
            ')
            ->first();

        // Single query for quote statistics
        $quoteStats = Quote::where('tenant_id', $tenantId)
            ->selectRaw('
                COUNT(*) as total_quotes,
                SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted_quotes,
                SUM(CASE WHEN status = "accepted" THEN final_amount ELSE 0 END) as total_value
            ')
            ->first();

        return [
            [
                'label' => 'Total Clients',
                'value' => $clientStats->total_clients ?? 0,
                'subtitle' => 'All clients',
                'icon' => 'fas fa-users',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => 'View All Clients'
            ],
            [
                'label' => 'Customers',
                'value' => $clientStats->customers ?? 0,
                'subtitle' => 'Active customers',
                'icon' => 'fas fa-handshake',
                'gradient' => 'from-green-500 to-green-600',
                'action' => 'View Customers'
            ],
            [
                'label' => 'Prospects',
                'value' => $clientStats->prospects ?? 0,
                'subtitle' => 'Potential customers',
                'icon' => 'fas fa-eye',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => 'View Prospects'
            ],
            [
                'label' => 'Total Value',
                'value' => '$' . number_format($quoteStats->total_value ?? 0, 0),
                'subtitle' => 'From accepted quotes',
                'icon' => 'fas fa-dollar-sign',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => 'View Quotes'
            ],
        ];
    }

    /**
     * Display the specified client with optimized queries
     */
    public function show(Client $client): View
    {
        // Eager load relationships to prevent N+1 queries
        $client->load([
            'quotes' => function ($q) {
                $q->latest()->limit(10);
            },
            'projects' => function ($q) {
                $q->latest()->limit(5);
            }
        ]);

        // Get quote statistics with optimized query
        $quoteStats = Cache::remember("client-quote-stats-{$client->id}", 300, function () use ($client) {
            return $client->quotes()
                ->selectRaw('
                    COUNT(*) as total,
                    SUM(CASE WHEN status = "draft" THEN 1 ELSE 0 END) as draft,
                    SUM(CASE WHEN status = "sent" THEN 1 ELSE 0 END) as sent,
                    SUM(CASE WHEN status = "viewed" THEN 1 ELSE 0 END) as viewed,
                    SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted,
                    SUM(CASE WHEN status = "rejected" THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN status = "expired" THEN 1 ELSE 0 END) as expired
                ')
                ->first();
        });

        // Get recent activity with optimized query
        $recentActivity = Cache::remember("client-activity-{$client->id}", 60, function () use ($client) {
            return collect()
                ->merge($client->quotes()->latest()->limit(5)->get())
                ->merge($client->projects()->latest()->limit(5)->get())
                ->sortByDesc('created_at')
                ->take(10);
        });

        return view('app.clients.show', compact('client', 'quoteStats', 'recentActivity'));
    }

    /**
     * Show the form for editing the specified client
     */
    public function edit(Client $client): View
    {
        return view('app.clients.edit', compact('client'));
    }

    /**
     * Update the specified client
     */
    public function update(Request $request, Client $client): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'lifecycle_stage' => 'required|in:lead,prospect,customer,inactive',
            'notes' => 'nullable|string',
            'address' => 'nullable|array',
        ]);

        try {
            $client->update($validated);

            // Clear cache after updating client
            Cache::forget("clients-kpi-{$client->tenant_id}");
            Cache::forget("client-quote-stats-{$client->id}");
            Cache::forget("client-activity-{$client->id}");

            return redirect()->route('app.clients.show', $client)
                ->with('success', 'Client updated successfully!');
        } catch (\Exception $e) {
            Log::error('Client update error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to update client: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Store a newly created client
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'company' => 'nullable|string|max:255',
            'lifecycle_stage' => 'required|in:lead,prospect,customer,inactive',
            'notes' => 'nullable|string',
            'address' => 'nullable|array',
        ]);

        try {
            $user = session('user');
            $validated['tenant_id'] = $user ? $user['tenant_id'] : '01k6vr04x4dw1zmh6kg7083gas';

            $client = Client::create($validated);

            // Clear cache after creating client
            Cache::forget("clients-kpi-{$validated['tenant_id']}");

            return redirect()->route('app.clients.show', $client)
                ->with('success', 'Client created successfully!');
        } catch (\Exception $e) {
            Log::error('Client creation error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to create client: ' . $e->getMessage())
                ->withInput();
        }
    }

    /**
     * Remove the specified client
     */
    public function destroy(Client $client): RedirectResponse
    {
        try {
            $tenantId = $client->tenant_id;
            $client->delete();

            // Clear cache after deleting client
            Cache::forget("clients-kpi-{$tenantId}");

            return redirect()->route('app.clients.index')
                ->with('success', 'Client deleted successfully!');
        } catch (\Exception $e) {
            Log::error('Client deletion error: ' . $e->getMessage());
            return redirect()->back()
                ->with('error', 'Failed to delete client: ' . $e->getMessage());
        }
    }
}
