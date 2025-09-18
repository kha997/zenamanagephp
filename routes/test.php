<?php

use Illuminate\Support\Facades\Route;

// Simple test route
Route::get('/test', function () {
    return response()->json([
        'message' => 'Test route working!',
        'timestamp' => now()->toISOString()
    ]);
});

// Test API route
Route::get('/api/test', function () {
    return response()->json([
        'message' => 'API test route working!',
        'timestamp' => now()->toISOString()
    ]);
});
