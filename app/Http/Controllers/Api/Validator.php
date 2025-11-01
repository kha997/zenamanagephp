<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class Validator extends Controller
{
    /**
     * API Validator utility class
     */
    public function validateRequest(Request $request, array $rules): JsonResponse
    {
        $validator = validator($request->all(), $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Validation passed'
        ]);
    }

    public function validateData(array $data, array $rules): JsonResponse
    {
        $validator = validator($data, $rules);
        
        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Validation passed'
        ]);
    }
}
