<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Trait để hỗ trợ authentication trong tests
 */
trait AuthenticationTrait
{
    use TenantUserFactoryTrait;

    protected array $apiHeaders = [];

    protected function apiHeadersForTenant(string $tenantId, array $headers = []): array
    {
        $defaultHeaders = [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'X-Tenant-ID' => $tenantId,
        ];

        return array_merge($defaultHeaders, $headers);
    }
    /**
     * Tạo user với JWT token cho testing
     *
     * @param array $attributes
     * @param array $roles
     * @return User
     */
    protected function createAuthenticatedUser(array $attributes = [], array $roles = []): User
    {
        $tenant = Tenant::factory()->create();
        
        $user = User::factory()->create(array_merge([
            'tenant_id' => $tenant->id,
            'password' => Hash::make('password')
        ], $attributes));
        
        $this->assignApiRoles($user, $roles);
        
        return $user;
    }

    /**
     * Tạo và authenticate user trong một bước
     *
     * @param array $attributes
     * @param array $roles
     * @return User
     */
    protected function actingAsUser(array $attributes = [], ?array $roles = null): User
    {
        $selectedRoles = $roles ?? ['super_admin', 'Admin'];
        $user = $this->createAuthenticatedUser($attributes, $selectedRoles);
        $tenant = $this->resolveTenantForUser($user);
        $this->apiAs($user, $tenant);
        
        return $user;
    }

    protected function apiLoginToken(User $user, Tenant $tenant): string
    {
        $user->forceFill([
            'password' => Hash::make('password'),
            'is_active' => true,
        ])->save();

        $response = $this->withHeaders($this->apiHeadersForTenant((string) $tenant->id))
            ->postJson('/api/auth/login', [
                'email' => $user->email,
                'password' => 'password',
            ]);

        if (!$response->isSuccessful()) {
            throw new RuntimeException(sprintf(
                'API login failed (%s): %s',
                $response->status(),
                $response->getContent()
            ));
        }

        $token = $this->extractApiToken($response->json());

        if ($token === null) {
            throw new RuntimeException('Unable to extract API token from login response: ' . $response->getContent());
        }

        return $token;
    }

    protected function apiAs(User $user, Tenant $tenant): static
    {
        $token = $this->apiLoginToken($user, $tenant);

        $headers = array_merge(
            $this->apiHeadersForTenant((string) $tenant->id),
            ['Authorization' => 'Bearer ' . $token]
        );

        $this->apiHeaders = $headers;

        return $this->withHeaders($headers);
    }

    private function extractApiToken(array $payload): ?string
    {
        $keys = [
            'data.token',
            'data.access_token',
            'token',
            'access_token',
            'data.plainTextToken',
            'data.data.token',
        ];

        foreach ($keys as $key) {
            $value = data_get($payload, $key);

            if (is_string($value) && $value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function resolveTenantForUser(User $user): Tenant
    {
        if ($user->tenant) {
            return $user->tenant;
        }

        $tenant = Tenant::find($user->tenant_id);

        if (!$tenant) {
            throw new RuntimeException('Unable to resolve tenant for user ' . $user->id);
        }

        return $tenant;
    }

    protected function createRbacAdminUser(?Tenant $tenant = null, array $attributes = []): User
    {
        $tenant = $tenant ?? Tenant::factory()->create();

        $user = $this->createTenantUser($tenant, array_merge([
            'name' => 'System Admin',
            'email' => 'admin+' . Str::random(6) . '@example.com',
        ], $attributes), ['admin']);

        $role = Role::firstOrCreate(
            ['name' => 'admin', 'scope' => Role::SCOPE_SYSTEM],
            [
                'allow_override' => true,
                'is_active' => true,
                'description' => 'System Administrator',
            ]
        );

        $permission = Permission::firstOrCreate(
            ['code' => 'admin'],
            [
                'name' => 'admin',
                'module' => 'admin',
                'action' => 'access',
                'description' => 'System admin access',
            ]
        );

        $role->permissions()->syncWithoutDetaching($permission->id);
        $user->roles()->syncWithoutDetaching($role->id);

        return $user;
    }

    protected function authHeadersForUser(User $user, string $token, array $extra = []): array
    {
        return array_merge(
            $this->apiHeadersForTenant((string) $user->tenant_id),
            [
                'Authorization' => 'Bearer ' . $token,
            ],
            $extra
        );
    }
}
