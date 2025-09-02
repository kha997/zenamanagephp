<?php

namespace Src\Foundation\Utils;

use Illuminate\Http\JsonResponse;

/**
 * JSend Response Helper
 * Chuẩn hóa API responses theo JSend specification
 * 
 * @see https://github.com/omniti-labs/jsend
 */
class JSendResponse
{
    /**
     * Success response
     * 
     * @param mixed $data
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, int $statusCode = 200): JsonResponse
    {
        $response = ['status' => 'success'];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return response()->json($response, $statusCode);
    }
    
    /**
     * Fail response (client error)
     * 
     * @param mixed $data
     * @param int $statusCode
     * @return JsonResponse
     */
    public static function fail($data, int $statusCode = 400): JsonResponse
    {
        return response()->json([
            'status' => 'fail',
            'data' => $data
        ], $statusCode);
    }
    
    /**
     * Error response (server error)
     * 
     * @param string $message
     * @param int $statusCode
     * @param mixed $data
     * @return JsonResponse
     */
    public static function error(string $message, int $statusCode = 500, $data = null): JsonResponse
    {
        $response = [
            'status' => 'error',
            'message' => $message
        ];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        return response()->json($response, $statusCode);
    }
}