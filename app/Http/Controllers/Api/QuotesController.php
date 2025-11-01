<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Quote;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuotesController extends Controller
{
    /**
     * Display a listing of quotes
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $query = Quote::where('tenant_id', $user->tenant_id);

            // Apply filters
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('quote_number', 'like', "%{$search}%")
                      ->orWhere('title', 'like', "%{$search}%");
                });
            }

            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            if ($request->filled('client_id')) {
                $query->where('client_id', $request->client_id);
            }

            // Sorting
            $sortBy = $request->get('sort_by', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortBy, $sortDirection);

            $quotes = $query->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $quotes->items(),
                'meta' => [
                    'total' => $quotes->total(),
                    'per_page' => $quotes->perPage(),
                    'current_page' => $quotes->currentPage(),
                    'last_page' => $quotes->lastPage()
                ]
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Store a newly created quote
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'client_id' => 'required|string|exists:clients,id',
                'amount' => 'required|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'valid_until' => 'nullable|date',
                'description' => 'nullable|string',
                'terms' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            $user = Auth::user();
            
            DB::beginTransaction();

            $quote = Quote::create([
                ...$request->validated(),
                'tenant_id' => $user->tenant_id,
                'quote_number' => $this->generateQuoteNumber($user->tenant_id),
                'status' => 'draft',
                'created_by' => $user->id,
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Quote created via API', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $quote->tenant_id,
                'created_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $quote,
                'message' => 'Quote created successfully'
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote creation failed via API', [
                'error' => $e->getMessage(),
                'data' => $request->all(),
                'created_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to create quote', 500);
        }
    }

    /**
     * Display the specified quote
     */
    public function show(Quote $quote): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($quote->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Quote belongs to different tenant', 403);
            }

            $quote->load(['client', 'items']);

            return response()->json([
                'success' => true,
                'data' => $quote
            ]);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 500);
        }
    }

    /**
     * Update the specified quote
     */
    public function update(Request $request, Quote $quote): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($quote->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Quote belongs to different tenant', 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|required|string|max:255',
                'client_id' => 'sometimes|required|string|exists:clients,id',
                'amount' => 'sometimes|required|numeric|min:0',
                'currency' => 'nullable|string|max:3',
                'valid_until' => 'nullable|date',
                'description' => 'nullable|string',
                'terms' => 'nullable|string',
                'status' => 'nullable|in:draft,sent,accepted,rejected,expired'
            ]);

            if ($validator->fails()) {
                return $this->errorResponse('Validation failed', 422, $validator->errors());
            }

            DB::beginTransaction();

            $quote->update([
                ...$request->validated(),
                'updated_by' => $user->id
            ]);

            DB::commit();

            Log::info('Quote updated via API', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $quote->tenant_id,
                'updated_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $quote,
                'message' => 'Quote updated successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote update failed via API', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'data' => $request->all(),
                'updated_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to update quote', 500);
        }
    }

    /**
     * Remove the specified quote
     */
    public function destroy(Quote $quote): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($quote->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Quote belongs to different tenant', 403);
            }

            DB::beginTransaction();

            $quoteNumber = $quote->quote_number;
            $quote->delete();

            DB::commit();

            Log::info('Quote deleted via API', [
                'quote_id' => $quote->id,
                'quote_number' => $quoteNumber,
                'tenant_id' => $quote->tenant_id,
                'deleted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Quote deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote deletion failed via API', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'deleted_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to delete quote', 500);
        }
    }

    /**
     * Send quote to client
     */
    public function send(Request $request, Quote $quote): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($quote->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Quote belongs to different tenant', 403);
            }

            $quote->update([
                'status' => 'sent',
                'sent_at' => now(),
                'updated_by' => $user->id
            ]);

            Log::info('Quote sent via API', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $quote->tenant_id,
                'sent_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $quote,
                'message' => 'Quote sent successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Quote send failed via API', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'sent_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to send quote', 500);
        }
    }

    /**
     * Accept quote
     */
    public function accept(Quote $quote): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($quote->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Quote belongs to different tenant', 403);
            }

            $quote->update([
                'status' => 'accepted',
                'accepted_at' => now(),
                'updated_by' => $user->id
            ]);

            Log::info('Quote accepted via API', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $quote->tenant_id,
                'accepted_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $quote,
                'message' => 'Quote accepted successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Quote accept failed via API', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'accepted_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to accept quote', 500);
        }
    }

    /**
     * Reject quote
     */
    public function reject(Quote $quote): JsonResponse
    {
        try {
            $user = Auth::user();
            
            // Verify tenant isolation
            if ($quote->tenant_id !== $user->tenant_id) {
                return $this->errorResponse('Access denied: Quote belongs to different tenant', 403);
            }

            $quote->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'updated_by' => $user->id
            ]);

            Log::info('Quote rejected via API', [
                'quote_id' => $quote->id,
                'quote_number' => $quote->quote_number,
                'tenant_id' => $quote->tenant_id,
                'rejected_by' => $user->id
            ]);

            return response()->json([
                'success' => true,
                'data' => $quote,
                'message' => 'Quote rejected successfully'
            ]);
        } catch (\Exception $e) {
            Log::error('Quote reject failed via API', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'rejected_by' => Auth::id()
            ]);

            return $this->errorResponse('Failed to reject quote', 500);
        }
    }

    /**
     * Generate quote number
     */
    private function generateQuoteNumber(string $tenantId): string
    {
        $count = Quote::where('tenant_id', $tenantId)->count() + 1;
        return 'QTE-' . strtoupper(substr($tenantId, -6)) . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
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
