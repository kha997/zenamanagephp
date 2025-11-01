<?php declare(strict_types=1);

namespace App\Http\Controllers\Test;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Test Seed Controller
 * 
 * Provides seeding endpoints for E2E testing
 * Only available in testing/development environment
 */
class TestSeedController extends Controller
{
    /**
     * Create a test user
     */
    public function createUser(Request $request): JsonResponse
    {
        // Only allow in testing environment
        if (!app()->environment(['testing', 'local', 'development'])) {
            return response()->json(['error' => 'Not available'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string|min:8',
            'name' => 'required|string',
            'tenant' => 'required|string',
            'role' => 'required|string',
            'verified' => 'boolean',
            'locked' => 'boolean',
            'twoFA' => 'boolean',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        
        // Find or create tenant
        $tenant = Tenant::firstOrCreate(
            ['slug' => $request->tenant],
            ['name' => ucfirst($request->tenant) . ' Company']
        );
        
        // Create user
        $user = User::updateOrCreate(
            ['email' => $request->email],
            [
                'name' => $request->name,
                'password' => Hash::make($request->password ?? 'password'),
                'tenant_id' => $tenant->id,
                'email_verified_at' => $request->get('verified', true) ? now() : null,
                'is_active' => !$request->get('locked', false),
                'two_factor_enabled' => $request->get('twoFA', false),
            ]
        );
        
        // Assign role
        $user->assignRole($request->role);
        
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'tenant_id' => $user->tenant_id,
        ]);
    }
    
    /**
     * Create a test tenant
     */
    public function createTenant(Request $request): JsonResponse
    {
        // Only allow in testing environment
        if (!app()->environment(['testing', 'local', 'development'])) {
            return response()->json(['error' => 'Not available'], 403);
        }
        
        $validator = Validator::make($request->all(), [
            'slug' => 'required|string',
            'name' => 'required|string',
        ]);
        
        if ($validator->fails()) {
            return response()->json(['error' => $validator->errors()], 422);
        }
        
        $tenant = Tenant::updateOrCreate(
            ['slug' => $request->slug],
            ['name' => $request->name]
        );
        
        return response()->json([
            'id' => $tenant->id,
            'slug' => $tenant->slug,
            'name' => $tenant->name,
        ]);
    }
    
    /**
     * Get user by email
     */
    public function getUser(string $email): JsonResponse
    {
        if (!app()->environment(['testing', 'local', 'development'])) {
            return response()->json(['error' => 'Not available'], 403);
        }
        
        $user = User::where('email', $email)->first();
        
        if (!$user) {
            return response()->json(['error' => 'User not found'], 404);
        }
        
        return response()->json([
            'id' => $user->id,
            'email' => $user->email,
            'name' => $user->name,
            'tenant_id' => $user->tenant_id,
            'verified' => $user->email_verified_at !== null,
            'active' => $user->is_active,
        ]);
    }
    
    /**
     * Clean up test data
     */
    public function cleanup(): JsonResponse
    {
        if (!app()->environment(['testing', 'local', 'development'])) {
            return response()->json(['error' => 'Not available'], 403);
        }
        
        // Delete test users (those with @test.com emails)
        User::where('email', 'LIKE', '%@test.com')->delete();
        
        // Note: Don't delete tenants to avoid breaking other tests
        
        return response()->json(['success' => true]);
    }
}

