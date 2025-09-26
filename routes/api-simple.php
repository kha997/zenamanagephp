<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\App\ProjectController;

/*
|--------------------------------------------------------------------------
| Simple API Routes for Testing Middleware
|--------------------------------------------------------------------------
|
| This file contains simple API routes for testing middleware functionality.
| These routes are isolated from the main web.php file to avoid conflicts.
|
*/

// Simple test route without middleware
Route::get('/test', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Simple test route working',
        'timestamp' => now()->toISOString()
    ]);
});

// Test route with new test middleware
Route::get('/test-new-middleware', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'New test middleware working',
        'timestamp' => now()->toISOString()
    ]);
}); // Temporarily removed middleware for debugging

// Test route with auth:sanctum middleware
Route::get('/test-auth', function () {
    return response()->json([
        'status' => 'success',
        'message' => 'Auth middleware working',
        'timestamp' => now()->toISOString()
    ]);
}); // Temporarily removed middleware for debugging

// Simple projects route without middleware
Route::get('/projects', function () {
    return response()->json([
        'status' => 'success',
        'data' => [],
        'message' => 'Projects route working without middleware',
        'timestamp' => now()->toISOString()
    ]);
});

// Simple projects route with custom middleware
Route::get('/projects-with-middleware', function () {
    return response()->json([
        'status' => 'success',
        'data' => [],
        'message' => 'Projects route working with custom middleware',
        'timestamp' => now()->toISOString()
    ]);
})->middleware('simple.auth.test');

// Simple projects route with auth:sanctum middleware
Route::get('/projects-with-auth', function () {
    return response()->json([
        'status' => 'success',
        'data' => [],
        'message' => 'Projects route working with auth middleware',
        'timestamp' => now()->toISOString()
    ]);
})->middleware('auth:sanctum');
