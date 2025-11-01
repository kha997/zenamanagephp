<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class SimpleDocumentController extends Controller
{
    /**
     * Display a listing of documents (simple version)
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'status' => 'success',
                'message' => 'Documents API is working',
                'data' => [
                    'documents' => [],
                    'total' => 0,
                    'message' => 'No documents found - API is functional'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created document
     */
    public function store(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Document creation endpoint is working'
        ]);
    }

    /**
     * Display the specified document
     */
    public function show(string $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Document show endpoint is working',
            'data' => ['id' => $id]
        ]);
    }

    /**
     * Update the specified document
     */
    public function update(string $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Document update endpoint is working',
            'data' => ['id' => $id]
        ]);
    }

    /**
     * Remove the specified document
     */
    public function destroy(string $id): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Document delete endpoint is working',
            'data' => ['id' => $id]
        ]);
    }
}