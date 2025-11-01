<?php

return [
    'validation_error' => 'Validation failed',
    'unauthorized' => 'Unauthorized access',
    'forbidden' => 'Access forbidden',
    'not_found' => 'Resource not found',
    'conflict' => 'Resource conflict',
    'unprocessable_entity' => 'Unprocessable entity',
    'rate_limited' => 'Too many requests',
    'internal_error' => 'Internal server error',
    'service_unavailable' => 'Service temporarily unavailable',
    'unknown_error' => 'Unknown error occurred',
    
    'validation' => [
        'required' => 'The :attribute field is required',
        'email' => 'The :attribute must be a valid email address',
        'unique' => 'The :attribute has already been taken',
        'min' => 'The :attribute must be at least :min characters',
        'max' => 'The :attribute may not be greater than :max characters',
    ],
    
    'auth' => [
        'failed' => 'These credentials do not match our records',
        'throttle' => 'Too many login attempts. Please try again in :seconds seconds',
        'token_expired' => 'Authentication token has expired',
        'token_invalid' => 'Authentication token is invalid',
    ],
    
    'tenant' => [
        'not_found' => 'Tenant not found',
        'access_denied' => 'Access denied for this tenant',
        'quota_exceeded' => 'Tenant quota exceeded',
    ],
    
    'project' => [
        'not_found' => 'Project not found',
        'access_denied' => 'Access denied for this project',
        'already_exists' => 'Project with this name already exists',
    ],
    
    'task' => [
        'not_found' => 'Task not found',
        'access_denied' => 'Access denied for this task',
        'invalid_status' => 'Invalid task status',
    ],
];
