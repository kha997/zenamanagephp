<?php

namespace App\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;
use DOMDocument;
use DOMXPath;

/**
 * SAML Service
 * 
 * Handles SAML authentication with external providers
 */
class SAMLService
{
    private array $config;
    private SecureAuditService $auditService;

    public function __construct(SecureAuditService $auditService)
    {
        $this->config = config('saml');
        $this->auditService = $auditService;
    }

    /**
     * Generate SAML authentication request
     */
    public function generateAuthRequest(string $provider): array
    {
        $providerConfig = $this->getProviderConfig($provider);
        
        if (!$providerConfig) {
            throw new \Exception("SAML provider '{$provider}' not configured");
        }

        $requestId = '_' . Str::random(32);
        $issueInstant = Carbon::now()->toISOString();
        
        // Store request ID for validation
        Cache::put("saml_request:{$requestId}", [
            'provider' => $provider,
            'expires_at' => Carbon::now()->addMinutes(10)
        ], 600);

        $samlRequest = $this->buildAuthnRequest($providerConfig, $requestId, $issueInstant);
        $encodedRequest = base64_encode($samlRequest);
        
        $authUrl = $providerConfig['sso_url'] . '?' . http_build_query([
            'SAMLRequest' => $encodedRequest,
            'RelayState' => $requestId
        ]);

        return [
            'auth_url' => $authUrl,
            'request_id' => $requestId,
            'relay_state' => $requestId
        ];
    }

    /**
     * Handle SAML response
     */
    public function handleResponse(string $provider, string $samlResponse, string $relayState = null): array
    {
        // Decode SAML response
        $responseXml = base64_decode($samlResponse);
        
        if (!$responseXml) {
            throw new \Exception('Invalid SAML response');
        }

        // Parse and validate response
        $response = $this->parseAndValidateResponse($responseXml, $provider);
        
        // Extract user attributes
        $userAttributes = $this->extractUserAttributes($response);
        
        // Create or update user
        $user = $this->createOrUpdateUser($provider, $userAttributes);
        
        // Generate local JWT token
        $localToken = $this->generateLocalToken($user);

        // Log successful SAML login
        $this->auditService->logAction(
            userId: $user->id,
            action: 'saml_login_success',
            entityType: 'Authentication',
            entityId: $provider,
            newData: [
                'provider' => $provider,
                'saml_name_id' => $response['name_id'] ?? null,
                'email' => $userAttributes['email'] ?? null
            ]
        );

        return [
            'user' => $user,
            'token' => $localToken,
            'provider' => $provider,
            'saml_data' => $userAttributes
        ];
    }

    /**
     * Build SAML authentication request
     */
    private function buildAuthnRequest(array $providerConfig, string $requestId, string $issueInstant): string
    {
        $appConfig = $this->config['app'];
        
        $samlRequest = '<?xml version="1.0" encoding="UTF-8"?>
<samlp:AuthnRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                    xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                    ID="' . $requestId . '"
                    Version="2.0"
                    IssueInstant="' . $issueInstant . '"
                    Destination="' . $providerConfig['sso_url'] . '"
                    AssertionConsumerServiceURL="' . $appConfig['assertion_consumer_service_url'] . '"
                    ProtocolBinding="urn:oasis:names:tc:SAML:2.0:bindings:HTTP-POST">
    <saml:Issuer>' . $appConfig['entity_id'] . '</saml:Issuer>
    <samlp:NameIDPolicy Format="' . $providerConfig['name_id_format'] . '" AllowCreate="true"/>
</samlp:AuthnRequest>';

        return $samlRequest;
    }

    /**
     * Parse and validate SAML response
     */
    private function parseAndValidateResponse(string $responseXml, string $provider): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($responseXml);
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('samlp', 'urn:oasis:names:tc:SAML:2.0:protocol');
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        
        // Extract response data
        $response = [
            'response_id' => $xpath->evaluate('string(//samlp:Response/@ID)'),
            'in_response_to' => $xpath->evaluate('string(//samlp:Response/@InResponseTo)'),
            'issue_instant' => $xpath->evaluate('string(//samlp:Response/@IssueInstant)'),
            'destination' => $xpath->evaluate('string(//samlp:Response/@Destination)'),
            'issuer' => $xpath->evaluate('string(//saml:Issuer)'),
            'name_id' => $xpath->evaluate('string(//saml:NameID)'),
            'session_index' => $xpath->evaluate('string(//saml:AuthnStatement/@SessionIndex)'),
        ];

        // Validate response
        $this->validateResponse($response, $provider);
        
        return $response;
    }

    /**
     * Validate SAML response
     */
    private function validateResponse(array $response, string $provider): void
    {
        $providerConfig = $this->getProviderConfig($provider);
        $appConfig = $this->config['app'];
        
        // Validate issuer
        if ($response['issuer'] !== $providerConfig['entity_id']) {
            throw new \Exception('Invalid response issuer');
        }
        
        // Validate destination
        if ($response['destination'] !== $appConfig['assertion_consumer_service_url']) {
            throw new \Exception('Invalid response destination');
        }
        
        // Validate InResponseTo if we have a request ID
        if ($response['in_response_to']) {
            $requestData = Cache::get("saml_request:{$response['in_response_to']}");
            if (!$requestData || $requestData['provider'] !== $provider) {
                throw new \Exception('Invalid InResponseTo');
            }
        }
        
        // Validate issue instant
        $issueInstant = Carbon::parse($response['issue_instant']);
        if ($issueInstant->lt(Carbon::now()->subMinutes(5))) {
            throw new \Exception('Response too old');
        }
    }

    /**
     * Extract user attributes from SAML response
     */
    private function extractUserAttributes(array $response): array
    {
        $dom = new DOMDocument();
        $dom->loadXML($response['response_xml'] ?? '');
        
        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');
        
        $attributes = [];
        $fieldMapping = $this->config['field_mapping'];
        
        // Extract attributes
        $attributeNodes = $xpath->query('//saml:Attribute');
        foreach ($attributeNodes as $attributeNode) {
            $name = $attributeNode->getAttribute('Name');
            $values = [];
            
            $valueNodes = $xpath->query('.//saml:AttributeValue', $attributeNode);
            foreach ($valueNodes as $valueNode) {
                $values[] = $valueNode->textContent;
            }
            
            $attributes[$name] = count($values) === 1 ? $values[0] : $values;
        }
        
        // Map to local fields
        $userAttributes = [];
        foreach ($fieldMapping as $localField => $samlAttribute) {
            if (isset($attributes[$samlAttribute])) {
                $userAttributes[$localField] = $attributes[$samlAttribute];
            }
        }
        
        // Add name ID as email if no email found
        if (!isset($userAttributes['email']) && isset($response['name_id'])) {
            $userAttributes['email'] = $response['name_id'];
        }
        
        return $userAttributes;
    }

    /**
     * Create or update user from SAML data
     */
    private function createOrUpdateUser(string $provider, array $userAttributes): User
    {
        $email = $userAttributes['email'] ?? null;
        
        if (!$email) {
            throw new \Exception('Email is required for SAML authentication');
        }

        // Check if user exists
        $user = User::where('email', $email)->first();

        if ($user) {
            // Update existing user
            $this->updateUserFromSAML($user, $provider, $userAttributes);
        } else {
            // Create new user if auto-creation is enabled
            if (!config('saml.security.auto_create_users', false)) {
                throw new \Exception('User not found and auto-creation is disabled');
            }
            
            $user = $this->createUserFromSAML($provider, $userAttributes);
        }

        return $user;
    }

    /**
     * Update existing user with SAML data
     */
    private function updateUserFromSAML(User $user, string $provider, array $userAttributes): void
    {
        $user->name = $userAttributes['name'] ?? $user->name;
        $user->first_name = $userAttributes['first_name'] ?? $user->first_name;
        $user->last_name = $userAttributes['last_name'] ?? $user->last_name;
        $user->department = $userAttributes['department'] ?? $user->department;
        $user->job_title = $userAttributes['job_title'] ?? $user->job_title;
        $user->manager = $userAttributes['manager'] ?? $user->manager;
        
        // Update SAML provider info
        $user->saml_provider = $provider;
        $user->saml_name_id = $userAttributes['email'];
        $user->saml_data = $userAttributes;
        $user->last_login_at = Carbon::now();
        
        $user->save();
    }

    /**
     * Create new user from SAML data
     */
    private function createUserFromSAML(string $provider, array $userAttributes): User
    {
        $userData = [
            'name' => $userAttributes['name'] ?? $userAttributes['email'],
            'email' => $userAttributes['email'],
            'first_name' => $userAttributes['first_name'] ?? null,
            'last_name' => $userAttributes['last_name'] ?? null,
            'department' => $userAttributes['department'] ?? null,
            'job_title' => $userAttributes['job_title'] ?? null,
            'manager' => $userAttributes['manager'] ?? null,
            'email_verified' => true, // SAML providers typically verify email
            'password' => bcrypt(Str::random(32)), // Random password
            'saml_provider' => $provider,
            'saml_name_id' => $userAttributes['email'],
            'saml_data' => $userAttributes,
            'last_login_at' => Carbon::now(),
        ];

        // Assign default tenant
        $defaultTenant = Tenant::first();
        if ($defaultTenant) {
            $userData['tenant_id'] = $defaultTenant->id;
        }

        $user = User::create($userData);

        // Assign default role if auto-assignment is enabled
        if (config('saml.security.auto_assign_roles', false)) {
            $defaultRole = config('saml.security.default_role', 'user');
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
            if (!empty($config['entity_id']) && !empty($config['sso_url'])) {
                $providers[] = [
                    'name' => $name,
                    'display_name' => ucfirst(str_replace('_', ' ', $name)),
                    'entity_id' => $config['entity_id']
                ];
            }
        }

        return $providers;
    }

    /**
     * Generate SAML logout request
     */
    public function generateLogoutRequest(string $provider, string $nameId, string $sessionIndex = null): array
    {
        $providerConfig = $this->getProviderConfig($provider);
        
        if (!$providerConfig) {
            throw new \Exception("SAML provider '{$provider}' not configured");
        }

        $requestId = '_' . Str::random(32);
        $issueInstant = Carbon::now()->toISOString();
        
        $samlRequest = $this->buildLogoutRequest($providerConfig, $requestId, $issueInstant, $nameId, $sessionIndex);
        $encodedRequest = base64_encode($samlRequest);
        
        $logoutUrl = $providerConfig['slo_url'] . '?' . http_build_query([
            'SAMLRequest' => $encodedRequest,
            'RelayState' => $requestId
        ]);

        return [
            'logout_url' => $logoutUrl,
            'request_id' => $requestId
        ];
    }

    /**
     * Build SAML logout request
     */
    private function buildLogoutRequest(array $providerConfig, string $requestId, string $issueInstant, string $nameId, string $sessionIndex = null): string
    {
        $appConfig = $this->config['app'];
        
        $sessionIndexXml = $sessionIndex ? '<samlp:SessionIndex>' . $sessionIndex . '</samlp:SessionIndex>' : '';
        
        $samlRequest = '<?xml version="1.0" encoding="UTF-8"?>
<samlp:LogoutRequest xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol"
                     xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion"
                     ID="' . $requestId . '"
                     Version="2.0"
                     IssueInstant="' . $issueInstant . '"
                     Destination="' . $providerConfig['slo_url'] . '">
    <saml:Issuer>' . $appConfig['entity_id'] . '</saml:Issuer>
    <saml:NameID>' . $nameId . '</saml:NameID>
    ' . $sessionIndexXml . '
</samlp:LogoutRequest>';

        return $samlRequest;
    }
}
