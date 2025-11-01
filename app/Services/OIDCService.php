<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * OpenID Connect Service
 * 
 * Handles OIDC authentication with external providers
 */
class OIDCService
{
    private array $config;
    private SecureAuditService $auditService;

    public function __construct(SecureAuditService $auditService)
    {
        $this->config = config('oidc');
        $this->auditService = $auditService;
    }

    /**
     * Generate authorization URL for OIDC provider
     */
    public function getAuthorizationUrl(string $provider, string $state = null): array
    {
        $providerConfig = $this->getProviderConfig($provider);
        
        if (!$providerConfig) {
            throw new \Exception("OIDC provider '{$provider}' not configured");
        }

        $state = $state ?: Str::random(32);
        $nonce = Str::random(32);
        
        // Store state and nonce for validation
        Cache::put("oidc_state:{$state}", [
            'nonce' => $nonce,
            'provider' => $provider,
            'expires_at' => Carbon::now()->addSeconds($this->config['security']['state_expires_in'])
        ], $this->config['security']['state_expires_in']);

        $params = [
            'response_type' => 'code',
            'client_id' => $providerConfig['client_id'],
            'redirect_uri' => $providerConfig['redirect_uri'],
            'scope' => implode(' ', $providerConfig['scopes']),
            'state' => $state,
            'nonce' => $nonce,
            'response_mode' => 'query',
        ];

        // Add max_age if configured
        if ($this->config['security']['max_age']) {
            $params['max_age'] = $this->config['security']['max_age'];
        }

        $authorizationUrl = $providerConfig['authorization_endpoint'] . '?' . http_build_query($params);

        return [
            'authorization_url' => $authorizationUrl,
            'state' => $state,
            'nonce' => $nonce
        ];
    }

    /**
     * Handle OIDC callback and exchange code for tokens
     */
    public function handleCallback(string $provider, string $code, string $state): array
    {
        // Validate state
        $stateData = Cache::get("oidc_state:{$state}");
        if (!$stateData || $stateData['provider'] !== $provider) {
            throw new \Exception('Invalid state parameter');
        }

        if (Carbon::now()->gt($stateData['expires_at'])) {
            throw new \Exception('State has expired');
        }

        $providerConfig = $this->getProviderConfig($provider);
        
        // Exchange code for tokens
        $tokenResponse = $this->exchangeCodeForTokens($providerConfig, $code);
        
        // Get user info
        $userInfo = $this->getUserInfo($providerConfig, $tokenResponse['access_token']);
        
        // Validate ID token if present
        if (isset($tokenResponse['id_token'])) {
            $this->validateIdToken($providerConfig, $tokenResponse['id_token'], $stateData['nonce']);
        }

        // Create or update user
        $user = $this->createOrUpdateUser($provider, $userInfo);

        // Generate local JWT token
        $localToken = $this->generateLocalToken($user);

        // Clean up state
        Cache::forget("oidc_state:{$state}");

        // Log successful OIDC login
        $this->auditService->logAction(
            userId: $user->id,
            action: 'oidc_login_success',
            entityType: 'Authentication',
            entityId: $provider,
            newData: [
                'provider' => $provider,
                'oidc_user_id' => $userInfo['sub'] ?? null,
                'email' => $userInfo['email'] ?? null
            ]
        );

        return [
            'user' => $user,
            'token' => $localToken,
            'provider' => $provider,
            'oidc_data' => $userInfo
        ];
    }

    /**
     * Exchange authorization code for tokens
     */
    private function exchangeCodeForTokens(array $providerConfig, string $code): array
    {
        $response = Http::asForm()->post($providerConfig['token_endpoint'], [
            'grant_type' => 'authorization_code',
            'client_id' => $providerConfig['client_id'],
            'client_secret' => $providerConfig['client_secret'],
            'redirect_uri' => $providerConfig['redirect_uri'],
            'code' => $code,
        ]);

        if (!$response->successful()) {
            throw new \Exception('Failed to exchange code for tokens: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Get user information from OIDC provider
     */
    private function getUserInfo(array $providerConfig, string $accessToken): array
    {
        $response = Http::withToken($accessToken)->get($providerConfig['userinfo_endpoint']);

        if (!$response->successful()) {
            throw new \Exception('Failed to get user info: ' . $response->body());
        }

        return $response->json();
    }

    /**
     * Validate ID token
     */
    private function validateIdToken(array $providerConfig, string $idToken, string $nonce): void
    {
        // Decode JWT header and payload
        $parts = explode('.', $idToken);
        if (count($parts) !== 3) {
            throw new \Exception('Invalid ID token format');
        }

        $header = json_decode(base64_decode($parts[0]), true);
        $payload = json_decode(base64_decode($parts[1]), true);

        // Validate issuer
        if ($payload['iss'] !== $providerConfig['issuer']) {
            throw new \Exception('Invalid token issuer');
        }

        // Validate audience
        if ($payload['aud'] !== $providerConfig['client_id']) {
            throw new \Exception('Invalid token audience');
        }

        // Validate nonce
        if (isset($payload['nonce']) && $payload['nonce'] !== $nonce) {
            throw new \Exception('Invalid token nonce');
        }

        // Validate expiration
        if (isset($payload['exp']) && $payload['exp'] < time()) {
            throw new \Exception('Token has expired');
        }

        // Validate issued at
        if (isset($payload['iat']) && $payload['iat'] > time() + 60) {
            throw new \Exception('Token issued in the future');
        }
    }

    /**
     * Create or update user from OIDC data
     */
    private function createOrUpdateUser(string $provider, array $userInfo): User
    {
        $email = $userInfo['email'] ?? null;
        
        if (!$email) {
            throw new \Exception('Email is required for OIDC authentication');
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update existing user
            $this->updateUserFromOIDC($user, $provider, $userInfo);
        } else {
            // Create new user if auto-creation is enabled
            if (!$this->config['security']['auto_create_users']) {
                throw new \Exception('User not found and auto-creation is disabled');
            }
            
            $user = $this->createUserFromOIDC($provider, $userInfo);
        }

        return $user;
    }

    /**
     * Update existing user with OIDC data
     */
    private function updateUserFromOIDC(User $user, string $provider, array $userInfo): void
    {
        $fieldMapping = $this->config['field_mapping'];
        
        foreach ($fieldMapping as $localField => $oidcField) {
            if (isset($userInfo[$oidcField])) {
                $user->$localField = $userInfo[$oidcField];
            }
        }

        // Update OIDC provider info
        $user->oidc_provider = $provider;
        $user->oidc_subject_id = $userInfo['sub'] ?? null;
        $user->oidc_data = $userInfo;
        $user->last_login_at = Carbon::now();
        
        $user->save();
    }

    /**
     * Create new user from OIDC data
     */
    private function createUserFromOIDC(string $provider, array $userInfo): User
    {
        $fieldMapping = $this->config['field_mapping'];
        
        $userData = [
            'name' => $userInfo['name'] ?? $userInfo['email'],
            'email' => $userInfo['email'],
            'email_verified' => true, // OIDC providers typically verify email
            'password' => bcrypt(Str::random(32)), // Random password
            'oidc_provider' => $provider,
            'oidc_subject_id' => $userInfo['sub'] ?? null,
            'oidc_data' => $userInfo,
            'last_login_at' => Carbon::now(),
        ];

        // Map additional fields
        foreach ($fieldMapping as $localField => $oidcField) {
            if (isset($userInfo[$oidcField]) && !isset($userData[$localField])) {
                $userData[$localField] = $userInfo[$oidcField];
            }
        }

        // Assign default tenant
        $defaultTenant = Tenant::first();
        if ($defaultTenant) {
            $userData['tenant_id'] = $defaultTenant->id;
        }

        $user = User::create($userData);

        // Assign default role if auto-assignment is enabled
        if ($this->config['security']['auto_assign_roles']) {
            $defaultRole = $this->config['security']['default_role'];
            // Implement role assignment logic here
        }

        return $user;
    }

    /**
     * Generate local JWT token for user
     */
    private function generateLocalToken(User $user): string
    {
        $authService = app(AuthService::class);
        return $authService->generateToken($user);
    }

    /**
     * Get provider configuration
     */
    private function getProviderConfig(string $provider): ?array
    {
        return $this->config['providers'][$provider] ?? null;
    }

    /**
     * Get available providers
     */
    public function getAvailableProviders(): array
    {
        $providers = [];
        
        foreach ($this->config['providers'] as $name => $config) {
            if (!empty($config['client_id']) && !empty($config['client_secret'])) {
                $providers[] = [
                    'name' => $name,
                    'display_name' => ucfirst($name),
                    'authorization_url' => $this->getAuthorizationUrl($name)['authorization_url']
                ];
            }
        }

        return $providers;
    }

    /**
     * Revoke OIDC tokens
     */
    public function revokeTokens(string $provider, string $accessToken): bool
    {
        $providerConfig = $this->getProviderConfig($provider);
        
        if (!$providerConfig || !isset($providerConfig['revocation_endpoint'])) {
            return false;
        }

        $response = Http::asForm()->post($providerConfig['revocation_endpoint'], [
            'token' => $accessToken,
            'client_id' => $providerConfig['client_id'],
            'client_secret' => $providerConfig['client_secret'],
        ]);

        return $response->successful();
    }

    /**
     * Get JWKS for token validation
     */
    public function getJWKS(string $provider): array
    {
        $providerConfig = $this->getProviderConfig($provider);
        
        if (!$providerConfig || !isset($providerConfig['jwks_uri'])) {
            return [];
        }

        $cacheKey = "oidc_jwks:{$provider}";
        
        return Cache::remember($cacheKey, $this->config['cache']['ttl'], function () use ($response) {
            
            if ($response->successful()) {
                return $response->json();
            }
            
            return [];
        });
    }
}