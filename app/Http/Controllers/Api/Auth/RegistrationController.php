<?php declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\TenantProvisioningService;
use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Registration Controller
 * 
 * Handles user registration with tenant creation
 * and email verification workflow.
 */
class RegistrationController extends Controller
{
    public function __construct(
        private TenantProvisioningService $tenantProvisioningService
    ) {}

    /**
     * Register new user and create tenant
     */
    public function store(Request $request)
    {
        try {
            // Validate request data
            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'email' => 'required|string|email|max:255|unique:users',
                'password' => 'required|string|min:8|confirmed',
                'tenant_name' => 'required|string|max:255',
                'phone' => 'nullable|string|max:20',
                'terms' => 'required|accepted',
            ]);
            
            $data = $request->only(['name', 'email', 'password', 'tenant_name', 'phone']);
            
            // Create tenant and owner user
            $result = $this->tenantProvisioningService->provisionTenant([
                'tenant_name' => $data['tenant_name'],
                'owner_name' => $data['name'],
                'owner_email' => $data['email'],
                'owner_password' => $data['password'],
            ]);

            if (!$result['success']) {
                return ApiResponse::error(
                    $result['error'],
                    422,
                    ['code' => $result['code']],
                    'REGISTRATION_FAILED'
                );
            }

            // Log successful registration
            Log::info('User registered successfully', [
                'user_id' => $result['user']->id,
                'tenant_id' => $result['tenant']->id,
                'email' => $data['email'],
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::created([
                'message' => 'Registration successful. Please check your email for verification.',
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                    'tenant_id' => $result['user']->tenant_id,
                    'email_verified_at' => $result['user']->email_verified_at,
                ],
                'tenant' => [
                    'id' => $result['tenant']->id,
                    'name' => $result['tenant']->name,
                    'slug' => $result['tenant']->slug,
                ],
                'verification_sent' => true
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            // Handle validation errors
            return ApiResponse::error(
                'Validation failed',
                422,
                $e->errors(),
                'VALIDATION_FAILED'
            );
        } catch (\Exception $e) {
            Log::error('Registration failed', [
                'error' => $e->getMessage(),
                'email' => $request->input('email'),
                'ip' => $request->ip(),
                'request_id' => $request->header('X-Request-Id')
            ]);

            return ApiResponse::error(
                'Registration failed',
                500,
                null,
                'REGISTRATION_FAILED'
            );
        }
    }
}
