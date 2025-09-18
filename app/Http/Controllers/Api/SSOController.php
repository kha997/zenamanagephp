<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\OIDCService;
use App\Services\SAMLService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

/**
 * SSO Controller
 * 
 * Handles Single Sign-On authentication with OIDC and SAML providers
 */
class SSOController extends Controller
{
    private OIDCService $oidcService;
    private SAMLService $samlService;

    public function __construct(OIDCService $oidcService, SAMLService $samlService)
    {
        $this->oidcService = $oidcService;
        $this->samlService = $samlService;
    }

    /*
    |--------------------------------------------------------------------------
    | OIDC Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Get available OIDC providers
     */
    public function getOIDCProviders(): JsonResponse
    {
        try {
            $providers = $this->oidcService->getAvailableProviders();
            
            return response()->json([
                'status' => 'success',
                'data' => $providers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get OIDC providers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate OIDC authentication
     */
    public function initiateOIDC(Request $request, string $provider): JsonResponse
    {
        try {
            $result = $this->oidcService->getAuthorizationUrl($provider);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'authorization_url' => $result['authorization_url'],
                    'state' => $result['state']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initiate OIDC authentication: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle OIDC callback
     */
    public function handleOIDCCallback(Request $request, string $provider): JsonResponse
    {
        try {
            $request->validate([
                'code' => 'required|string',
                'state' => 'required|string'
            ]);

            $result = $this->oidcService->handleCallback(
                $provider,
                $request->code,
                $request->state
            );

            return response()->json([
                'status' => 'success',
                'message' => 'OIDC authentication successful',
                'data' => [
                    'user' => $result['user'],
                    'token' => $result['token'],
                    'provider' => $result['provider']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'OIDC authentication failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SAML Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Get available SAML providers
     */
    public function getSAMLProviders(): JsonResponse
    {
        try {
            $providers = $this->samlService->getAvailableProviders();
            
            return response()->json([
                'status' => 'success',
                'data' => $providers
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get SAML providers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate SAML authentication
     */
    public function initiateSAML(Request $request, string $provider): JsonResponse
    {
        try {
            $result = $this->samlService->generateAuthRequest($provider);
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'auth_url' => $result['auth_url'],
                    'request_id' => $result['request_id']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initiate SAML authentication: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Handle SAML callback
     */
    public function handleSAMLCallback(Request $request, string $provider): JsonResponse
    {
        try {
            $request->validate([
                'SAMLResponse' => 'required|string',
                'RelayState' => 'nullable|string'
            ]);

            $result = $this->samlService->handleResponse(
                $provider,
                $request->SAMLResponse,
                $request->RelayState
            );

            return response()->json([
                'status' => 'success',
                'message' => 'SAML authentication successful',
                'data' => [
                    'user' => $result['user'],
                    'token' => $result['token'],
                    'provider' => $result['provider']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'SAML authentication failed: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * Initiate SAML logout
     */
    public function initiateSAMLLogout(Request $request, string $provider): JsonResponse
    {
        try {
            $request->validate([
                'name_id' => 'required|string',
                'session_index' => 'nullable|string'
            ]);

            $result = $this->samlService->generateLogoutRequest(
                $provider,
                $request->name_id,
                $request->session_index
            );

            return response()->json([
                'status' => 'success',
                'data' => [
                    'logout_url' => $result['logout_url'],
                    'request_id' => $result['request_id']
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to initiate SAML logout: ' . $e->getMessage()
            ], 400);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Generic SSO Routes
    |--------------------------------------------------------------------------
    */

    /**
     * Get all available SSO providers
     */
    public function getSSOProviders(): JsonResponse
    {
        try {
            $oidcProviders = $this->oidcService->getAvailableProviders();
            $samlProviders = $this->samlService->getAvailableProviders();
            
            return response()->json([
                'status' => 'success',
                'data' => [
                    'oidc' => $oidcProviders,
                    'saml' => $samlProviders
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get SSO providers: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get SSO configuration for frontend
     */
    public function getSSOConfig(): JsonResponse
    {
        try {
            $config = [
                'oidc' => [
                    'enabled' => config('oidc.providers') ? true : false,
                    'providers' => $this->oidcService->getAvailableProviders()
                ],
                'saml' => [
                    'enabled' => config('saml.providers') ? true : false,
                    'providers' => $this->samlService->getAvailableProviders()
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $config
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to get SSO configuration: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Test SSO connection
     */
    public function testSSOConnection(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|in:oidc,saml',
                'provider' => 'required|string'
            ]);

            $type = $request->type;
            $provider = $request->provider;

            if ($type === 'oidc') {
                $providers = $this->oidcService->getAvailableProviders();
                $providerExists = collect($providers)->contains('name', $provider);
            } else {
                $providers = $this->samlService->getAvailableProviders();
                $providerExists = collect($providers)->contains('name', $provider);
            }

            if (!$providerExists) {
                return response()->json([
                    'status' => 'error',
                    'message' => "Provider '{$provider}' not found or not configured"
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => "SSO connection to {$provider} is available",
                'data' => [
                    'type' => $type,
                    'provider' => $provider,
                    'status' => 'available'
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'SSO connection test failed: ' . $e->getMessage()
            ], 500);
        }
    }
}
