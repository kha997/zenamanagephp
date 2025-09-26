<?php

namespace App\Http\Controllers\Api;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="ErrorEnvelope",
 *     type="object",
 *     required={"error"},
 *     @OA\Property(
 *         property="error",
 *         type="object",
 *         required={"id", "code", "message"},
 *         @OA\Property(
 *             property="id",
 *             type="string",
 *             description="Unique error identifier for correlation",
 *             example="req_abc12345"
 *         ),
 *         @OA\Property(
 *             property="code",
 *             type="string",
 *             description="Error code following pattern E{HTTP_CODE}.{CATEGORY}",
 *             example="E422.VALIDATION"
 *         ),
 *         @OA\Property(
 *             property="message",
 *             type="string",
 *             description="Human-readable error message",
 *             example="Validation failed"
 *         ),
 *         @OA\Property(
 *             property="details",
 *             type="object",
 *             description="Additional error details",
 *             @OA\Property(
 *                 property="validation",
 *                 type="object",
 *                 description="Validation errors (for E422.VALIDATION)",
 *                 additionalProperties={"type": "array", "items": {"type": "string"}}
 *             ),
 *             @OA\Property(
 *                 property="retry_after",
 *                 type="integer",
 *                 description="Seconds to wait before retry (for E429.RATE_LIMIT, E503.SERVICE_UNAVAILABLE)",
 *                 example=60
 *             ),
 *             @OA\Property(
 *                 property="exception",
 *                 type="string",
 *                 description="Exception details (non-production only)",
 *                 example="Database connection failed"
 *             )
 *         )
 *     )
 * )
 * 
 * @OA\Schema(
 *     schema="ErrorCodes",
 *     type="object",
 *     description="Standard error codes used in ZenaManage API",
 *     @OA\Property(
 *         property="E400.BAD_REQUEST",
 *         type="string",
 *         description="Bad request - invalid request format or parameters"
 *     ),
 *     @OA\Property(
 *         property="E401.AUTHENTICATION",
 *         type="string",
 *         description="Authentication required or failed"
 *     ),
 *     @OA\Property(
 *         property="E403.AUTHORIZATION",
 *         type="string",
 *         description="Insufficient permissions to access resource"
 *     ),
 *     @OA\Property(
 *         property="E404.NOT_FOUND",
 *         type="string",
 *         description="Requested resource not found"
 *     ),
 *     @OA\Property(
 *         property="E409.CONFLICT",
 *         type="string",
 *         description="Resource conflict - resource already exists or is in use"
 *     ),
 *     @OA\Property(
 *         property="E422.VALIDATION",
 *         type="string",
 *         description="Validation failed - invalid input data"
 *     ),
 *     @OA\Property(
 *         property="E429.RATE_LIMIT",
 *         type="string",
 *         description="Rate limit exceeded - too many requests"
 *     ),
 *     @OA\Property(
 *         property="E500.SERVER_ERROR",
 *         type="string",
 *         description="Internal server error"
 *     ),
 *     @OA\Property(
 *         property="E503.SERVICE_UNAVAILABLE",
 *         type="string",
 *         description="Service temporarily unavailable"
 *     )
 * )
 */
class ErrorEnvelopeSchema
{
    // This class is used only for OpenAPI documentation
    // The actual error envelope implementation is in ErrorEnvelopeService
}
