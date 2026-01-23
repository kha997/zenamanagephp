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
     * @param string|int|null $messageOrStatusCode
     * @param int|null $statusCode
     * @return JsonResponse
     */
    public static function success($data = null, $messageOrStatusCode = null, ?int $statusCode = null): JsonResponse
    {
        $response = ['status' => 'success'];

        if ($data !== null) {
            $response['data'] = $data;
        }

        $message = null;

        if (is_int($messageOrStatusCode)) {
            $statusCode = $messageOrStatusCode;
        } elseif (is_string($messageOrStatusCode)) {
            $message = $messageOrStatusCode;
        }

        if ($statusCode === null) {
            $statusCode = 200;
        }

        if ($message !== null) {
            $response['message'] = $message;
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
