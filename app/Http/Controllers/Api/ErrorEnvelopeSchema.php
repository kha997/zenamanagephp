<?php

namespace App\Http\Controllers\Api;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ErrorEnvelope',
    required: [
        'error',
    ],
    properties: [
        new OA\Property(
            property: 'error',
            required: [
                'id',
                'code',
                'message',
            ],
            properties: [
                new OA\Property(
                    property: 'id',
                    description: 'Unique error identifier for correlation',
                    type: 'string',
                    example: 'req_abc12345',
                ),
                new OA\Property(
                    property: 'code',
                    description: 'Error code following pattern E{HTTP_CODE}.{CATEGORY}',
                    type: 'string',
                    example: 'E422.VALIDATION',
                ),
                new OA\Property(
                    property: 'message',
                    description: 'Human-readable error message',
                    type: 'string',
                    example: 'Validation failed',
                ),
                new OA\Property(
                    property: 'details',
                    description: 'Additional error details',
                    properties: [
                        new OA\Property(
                            property: 'validation',
                            description: 'Validation errors (for E422.VALIDATION)',
                            type: 'object',
                            additionalProperties: new OA\AdditionalProperties(
                                type: 'array',
                                items: new OA\Items(
                                    type: 'string',
                                ),
                            ),
                        ),
                        new OA\Property(
                            property: 'retry_after',
                            description: 'Seconds to wait before retry (for E429.RATE_LIMIT, E503.SERVICE_UNAVAILABLE)',
                            type: 'integer',
                            example: 60,
                        ),
                        new OA\Property(
                            property: 'exception',
                            description: 'Exception details (non-production only)',
                            type: 'string',
                            example: 'Database connection failed',
                        ),
                    ],
                    type: 'object',
                ),
            ],
            type: 'object',
        ),
    ],
    type: 'object',
)]
#[OA\Schema(
    schema: 'ErrorCodes',
    description: 'Standard error codes used in ZenaManage API',
    properties: [
        new OA\Property(
            property: 'E400.BAD_REQUEST',
            description: 'Bad request - invalid request format or parameters',
            type: 'string',
        ),
        new OA\Property(
            property: 'E401.AUTHENTICATION',
            description: 'Authentication required or failed',
            type: 'string',
        ),
        new OA\Property(
            property: 'E403.AUTHORIZATION',
            description: 'Insufficient permissions to access resource',
            type: 'string',
        ),
        new OA\Property(
            property: 'E404.NOT_FOUND',
            description: 'Requested resource not found',
            type: 'string',
        ),
        new OA\Property(
            property: 'E409.CONFLICT',
            description: 'Resource conflict - resource already exists or is in use',
            type: 'string',
        ),
        new OA\Property(
            property: 'E422.VALIDATION',
            description: 'Validation failed - invalid input data',
            type: 'string',
        ),
        new OA\Property(
            property: 'E429.RATE_LIMIT',
            description: 'Rate limit exceeded - too many requests',
            type: 'string',
        ),
        new OA\Property(
            property: 'E500.SERVER_ERROR',
            description: 'Internal server error',
            type: 'string',
        ),
        new OA\Property(
            property: 'E503.SERVICE_UNAVAILABLE',
            description: 'Service temporarily unavailable',
            type: 'string',
        ),
    ],
    type: 'object',
)]
class ErrorEnvelopeSchema
{
    // This class is used only for OpenAPI documentation
    // The actual error envelope implementation is in ErrorEnvelopeService
}
