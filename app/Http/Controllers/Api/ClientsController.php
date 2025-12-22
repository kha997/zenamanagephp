<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientStoreRequest;
use App\Http\Requests\ClientUpdateRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ClientsController extends Controller
{
    /**
     * Display a listing of clients
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Client::where('tenant_id', $user->tenant_id);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('email', 'like', "%{$search}%")
                      ->orWhere('company', 'like', "%{$search}%");
                });
            }

            if ($request->filled('lifecycle_stage')) {
                $query->where('lifecycle_stage', $request->lifecycle_stage);
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $clients = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $clients->items(),
                'meta' => [
                    'total' => $clients->total(),
                    'per_page' => $clients->perPage(),
                    'current_page' => $clients->currentPage(),
                    'last_page' => $clients->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created client
     */
    public function store(ClientStoreRequest $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'required|email|unique:clients,email',
                'phone' => 'nullable|string|max:20',
                'company' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'lifecycle_stage' => 'nullable|in:lead,prospect,customer,inactive',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = Auth::user();
            
            DB::beginTransaction();

            $client = Client::create([
                ...$request->validated(),
                'tenant_id' => $user->tenant_id,
                'lifecycle_stage' => $request->lifecycle_stage ?? 'lead',
                'created_by' => $user->id,
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Client created via API', [
                'client_id' => $client->id,
                'name' => $client->name,
                'tenant_id' => $client->tenant_id,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $client,
                'message' => 'Client created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Client creation failed via API', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'created_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to create client', 500);
        }
    }

    /**
     * Display the specified client
     */
    public function show(Client $client): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($client->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Client belongs to different tenant', 403);
            }

            $client->load([
                'quotes' => function ($q) {
                    $q->latest()->limit(10);
                },
                'projects' => function ($q) {
                    $q->latest()->limit(5);
                }
            ]);

            return response()->json([
                'success' => true,
                'data' => $client
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified client
     */
    public function update(Request $request, Client $client): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($client->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Client belongs to different tenant', 403);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:255',
                'email' => [
                    'sometimes',
                    'required',
                    'email',
                    'unique:clients,email,' . $client->id
                ],
                'phone' => 'nullable|string|max:20',
                'company' => 'nullable|string|max:255',
                'address' => 'nullable|string',
                'lifecycle_stage' => 'nullable|in:lead,prospect,customer,inactive',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            $client->update([
                ...$request->validated(),
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Client updated via API', [
                'client_id' => $client->id,
                'name' => $client->name,
                'tenant_id' => $client->tenant_id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $client,
                'message' => 'Client updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Client update failed via API', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'data' => $request->all(),
                'updated_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to update client', 500);
        }
    }

    /**
     * Remove the specified client
     */
    public function destroy(Client $client): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($client->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Client belongs to different tenant', 403);
            }

            DB::beginTransaction();

            $clientName = $client->name;
            $client->delete();

            DB::commit();

            Log::info('Client deleted via API', [
                'client_id' => $client->id,
                'name' => $clientName,
                'tenant_id' => $client->tenant_id,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Client deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Client deletion failed via API', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'deleted_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to delete client', 500);
        }
    }

    /**
     * Update client lifecycle stage
     */
    public function updateLifecycleStage(Request $request, Client $client): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($client->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Client belongs to different tenant', 403);
            }

            $validator = Validator::make($request->all(), [
                'lifecycle_stage' => 'required|in:lead,prospect,customer,inactive'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $client->update([
                'lifecycle_stage' => $request->lifecycle_stage,
                'updated_by' => $user->id
            ]);

            Log::info('Client lifecycle stage updated via API', [
                'client_id' => $client->id,
                'lifecycle_stage' => $request->lifecycle_stage,
                'tenant_id' => $client->tenant_id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $client,
                'message' => 'Client lifecycle stage updated successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Client lifecycle stage update failed via API', [
                'error' => $e->getMessage(),
                'client_id' => $client->id,
                'updated_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to update client lifecycle stage', 500);
        }
    }

    /**
     * Standardized error response with error envelope
     */
    private function errorResponse(string $message, int $status = 500, $errors = null): JsonResponse
    {
        $errorId = uniqid('err_', true);
        
        $response = [
            'success' => false,
            'error' => [
                'id' => $errorId,
                'message' => $message,
                'status' => $status,
                'timestamp' => now()->toISOString()
            ]
        ];

        if ($errors) {
            $response['error']['details'] = $errors;
        }

        return response()->json($response, $status);
    }
}
