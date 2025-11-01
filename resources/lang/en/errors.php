<?php

return [
    'errors' => [
        'E400.BAD_REQUEST' => 'Bad request - invalid request format or parameters',
        'E401.AUTHENTICATION' => 'Authentication required or failed',
        'E403.AUTHORIZATION' => 'Insufficient permissions to access resource',
        'E404.NOT_FOUND' => 'Requested resource not found',
        'E409.CONFLICT' => 'Resource conflict - resource already exists or is in use',
        'E422.VALIDATION' => 'Validation failed - invalid input data',
        'E429.RATE_LIMIT' => 'Rate limit exceeded - too many requests',
        'E500.SERVER_ERROR' => 'Internal server error',
        'E503.SERVICE_UNAVAILABLE' => 'Service temporarily unavailable',
    ],
    
    'messages' => [
        'validation_failed' => 'Validation failed',
        'authentication_required' => 'Authentication required',
        'insufficient_permissions' => 'Insufficient permissions',
        'resource_not_found' => 'Resource not found',
        'resource_conflict' => 'Resource conflict',
        'rate_limit_exceeded' => 'Rate limit exceeded',
        'internal_server_error' => 'Internal server error',
        'service_unavailable' => 'Service temporarily unavailable',
    ],
    
    'project_manager' => [
        'role_required' => 'Project manager role required',
        'stats_retrieval_failed' => 'Failed to retrieve Project Manager dashboard statistics',
        'timeline_retrieval_failed' => 'Failed to retrieve project timeline',
    ],
    
    'validation' => [
        'required' => 'The :attribute field is required.',
        'email' => 'The :attribute must be a valid email address.',
        'min' => 'The :attribute must be at least :min characters.',
        'max' => 'The :attribute may not be greater than :max characters.',
        'unique' => 'The :attribute has already been taken.',
        'exists' => 'The selected :attribute is invalid.',
    ],
];
