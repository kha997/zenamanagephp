<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AppApiGateway;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Web Client Controller - UI chỉ render, không có business logic
 * 
 * @package App\Http\Controllers\Web
 */
class ClientController extends Controller
{
    protected AppApiGateway $apiGateway;

    public function __construct(AppApiGateway $apiGateway)
    {
        $this->apiGateway = $apiGateway;
    }

    /**
     * Display a listing of clients
     */
    public function index(Request $request): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Get filters from request
            $filters = $request->only(['status', 'search', 'sort_by', 'sort_direction']);
            
            // Fetch clients from API
            $response = $this->apiGateway->fetchClients($filters);
            
            if (!$response['success']) {
                return view('app.clients.index', [
                    'clients' => collect(),
                    'kpis' => [],
                    'stats' => $this->buildClientStats([]),
                    'filters' => $filters,
                    'error' => $response['error']['message'] ?? 'Failed to fetch clients'
                ]);
            }

            // Fetch dashboard stats for KPIs
            $dashboardResponse = $this->apiGateway->fetchDashboardStats();

            return view('app.clients.index', [
                'clients' => $response['data']['clients'] ?? collect(),
                'meta' => $response['data']['meta'] ?? [],
                'kpis' => $this->buildKpis($dashboardResponse['data'] ?? []),
                'stats' => $this->buildClientStats($dashboardResponse['data'] ?? []),
                'filters' => $filters,
                'user' => auth()->user(),
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('app.dashboard')],
                    ['label' => 'Clients', 'url' => null]
                ],
                'actions' => '<a href="' . route('app.clients.create') . '" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i>
                    New Client
                </a>',
                'sortOptions' => [
                    ['value' => 'name', 'label' => 'Client Name'],
                    ['value' => 'status', 'label' => 'Status'],
                    ['value' => 'created_at', 'label' => 'Created Date'],
                    ['value' => 'updated_at', 'label' => 'Last Updated']
                ],
                'bulkActions' => [
                    ['value' => 'delete', 'label' => 'Delete Selected', 'icon' => 'fas fa-trash', 'handler' => 'bulkDelete'],
                    ['value' => 'export', 'label' => 'Export Selected', 'icon' => 'fas fa-download', 'handler' => 'bulkExport']
                ],
                'tableData' => collect($response['data']['clients'] ?? [])->map(function($client) {
                    return [
                        'id' => $client->id ?? $client['id'] ?? '',
                        'name' => $client->name ?? $client['name'] ?? '',
                        'status' => $client->status ?? $client['status'] ?? 'active',
                        'created_at' => $client->created_at ?? $client['created_at'] ?? '',
                        'updated_at' => $client->updated_at ?? $client['updated_at'] ?? ''
                    ];
                }),
                'columns' => [
                    ['key' => 'name', 'label' => 'Client Name', 'sortable' => true],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => true],
                    ['key' => 'created_at', 'label' => 'Created Date', 'sortable' => true],
                    ['key' => 'updated_at', 'label' => 'Last Updated', 'sortable' => true]
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('ClientController index error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return view('app.clients.index', [
                'clients' => collect(),
                'kpis' => [],
                'stats' => $this->buildClientStats([]),
                'filters' => [],
                'error' => 'An error occurred while loading clients',
                'user' => auth()->user(),
                'breadcrumbs' => [
                    ['label' => 'Dashboard', 'url' => route('app.dashboard')],
                    ['label' => 'Clients', 'url' => null]
                ],
                'actions' => '<a href="' . route('app.clients.create') . '" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    <i class="fas fa-plus mr-2"></i>
                    New Client
                </a>',
                'sortOptions' => [
                    ['value' => 'name', 'label' => 'Client Name'],
                    ['value' => 'status', 'label' => 'Status'],
                    ['value' => 'created_at', 'label' => 'Created Date'],
                    ['value' => 'updated_at', 'label' => 'Last Updated']
                ],
                'bulkActions' => [
                    ['value' => 'delete', 'label' => 'Delete Selected', 'icon' => 'fas fa-trash', 'handler' => 'bulkDelete'],
                    ['value' => 'export', 'label' => 'Export Selected', 'icon' => 'fas fa-download', 'handler' => 'bulkExport']
                ],
                'tableData' => collect(),
                'columns' => [
                    ['key' => 'name', 'label' => 'Client Name', 'sortable' => true],
                    ['key' => 'status', 'label' => 'Status', 'sortable' => true],
                    ['key' => 'created_at', 'label' => 'Created Date', 'sortable' => true],
                    ['key' => 'updated_at', 'label' => 'Last Updated', 'sortable' => true]
                ]
            ]);
        }
    }

    /**
     * Show the form for creating a new client.
     */
    public function create(): View
    {
        return view('app.clients.create');
    }

    /**
     * Store a newly created client.
     */
    public function store(Request $request): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Validate input
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'contact_person' => 'nullable|string|max:255',
                'status' => 'nullable|in:active,inactive,prospect',
                'notes' => 'nullable|string'
            ]);

            // Create client via API
            $response = $this->apiGateway->createClient($validated);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to create client'])
                    ->withInput();
            }

            return redirect()
                ->route('app.clients.index')
                ->with('success', 'Client created successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('ClientController store error', [
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while creating the client'])
                ->withInput();
        }
    }

    /**
     * Display the specified client.
     */
    public function show(string $clientId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch client details
            $response = $this->apiGateway->fetchClient($clientId);

            if (!$response['success']) {
                abort(404, 'Client not found');
            }

            // Fetch client projects
            $projectsResponse = $this->apiGateway->fetchProjects(['client_id' => $clientId]);

            return view('app.clients.show', [
                'client' => $response['data']['client'],
                'projects' => $projectsResponse['data']['projects'] ?? collect()
            ]);

        } catch (\Exception $e) {
            Log::error('ClientController show error', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Client not found');
        }
    }

    /**
     * Show the form for editing the specified client.
     */
    public function edit(string $clientId): View
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Fetch client details
            $response = $this->apiGateway->fetchClient($clientId);
            
            if (!$response['success']) {
                abort(404, 'Client not found');
            }

            return view('app.clients.edit', [
                'client' => $response['data']['client']
            ]);

        } catch (\Exception $e) {
            Log::error('ClientController edit error', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            abort(404, 'Client not found');
        }
    }

    /**
     * Update the specified client.
     */
    public function update(Request $request, string $clientId): RedirectResponse
    {
        try {
            $this->apiGateway->setAuthContext();
            
            // Validate input
            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255',
                'email' => 'nullable|email|max:255',
                'phone' => 'nullable|string|max:20',
                'address' => 'nullable|string|max:500',
                'contact_person' => 'nullable|string|max:255',
                'status' => 'nullable|in:active,inactive,prospect',
                'notes' => 'nullable|string'
            ]);

            // Update client via API
            $response = $this->apiGateway->updateClient($clientId, $validated);

            if (!$response['success']) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => $response['error']['message'] ?? 'Failed to update client'])
                    ->withInput();
            }

            return redirect()
                ->route('app.clients.index')
                ->with('success', 'Client updated successfully!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            return redirect()
                ->back()
                ->withErrors($e->errors())
                ->withInput();

        } catch (\Exception $e) {
            Log::error('ClientController update error', [
                'client_id' => $clientId,
                'error' => $e->getMessage(),
                'request_id' => $this->apiGateway->getRequestId()
            ]);

            return redirect()
                ->back()
                ->withErrors(['error' => 'An error occurred while updating the client'])
                ->withInput();
        }
    }

    /**
     * Build KPI data from dashboard stats
     */
    private function buildKpis(array $stats): array
    {
        return [
            [
                'label' => 'Total Clients',
                'value' => $stats['total_clients'] ?? 0,
                'subtitle' => 'All clients',
                'icon' => 'fas fa-users',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => 'View All Clients'
            ],
            [
                'label' => 'Active Clients',
                'value' => $stats['active_clients'] ?? 0,
                'subtitle' => 'Currently active',
                'icon' => 'fas fa-user-check',
                'gradient' => 'from-green-500 to-green-600',
                'action' => 'View Active'
            ],
            [
                'label' => 'Prospects',
                'value' => $stats['prospect_clients'] ?? 0,
                'subtitle' => 'Potential clients',
                'icon' => 'fas fa-user-plus',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => 'View Prospects'
            ],
            [
                'label' => 'Inactive',
                'value' => $stats['inactive_clients'] ?? 0,
                'subtitle' => 'Inactive clients',
                'icon' => 'fas fa-user-times',
                'gradient' => 'from-red-500 to-red-600',
                'action' => 'View Inactive'
            ]
        ];
    }

    /**
     * Build client statistics for the view
     */
    private function buildClientStats(array $stats): array
    {
        return [
            'total' => $stats['total_clients'] ?? 0,
            'leads' => $stats['lead_clients'] ?? 0,
            'prospects' => $stats['prospect_clients'] ?? 0,
            'customers' => $stats['customer_clients'] ?? 0,
            'inactive' => $stats['inactive_clients'] ?? 0
        ];
    }
}
