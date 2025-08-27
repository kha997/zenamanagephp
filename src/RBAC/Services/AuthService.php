<?php declare(strict_types=1);

namespace Src\RBAC\Services;

use App\Models\User;
use App\Models\Tenant;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Exception;

/**
 * Service xử lý authentication với JWT
 * 
 * @package Src\RBAC\Services
 */
class AuthService
{
    private string $jwtSecret;
    private int $jwtTtl;
    private int $jwtRefreshTtl;
    private string $jwtAlgo;

    public function __construct()
    {
        // Sử dụng JWT secret từ .env thay vì app.key
        $this->jwtSecret = config('jwt.secret') ?: env('JWT_SECRET', 'default-secret-key');
        $this->jwtTtl = (int) (config('jwt.ttl') ?: env('JWT_TTL', 3600));
        $this->jwtRefreshTtl = (int) (config('jwt.refresh_ttl') ?: env('JWT_REFRESH_TTL', 20160));
        $this->jwtAlgo = config('jwt.algo') ?: env('JWT_ALGO', 'HS256');
    }

    /**
     * Đăng nhập user
     *
     * @param array $credentials
     * @return array
     */
    public function login(array $credentials): array
    {
        try {
            $user = User::where('email', $credentials['email'])->first();

            if (!$user || !Hash::check($credentials['password'], $user->password)) {
                return [
                    'success' => false,
                    'message' => 'Email hoặc mật khẩu không đúng'
                ];
            }

            $token = $this->generateToken($user);

            return [
                'success' => true,
                'user' => $user->load('tenant'),
                'token' => $token
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Lỗi đăng nhập: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Đăng ký user mới
     *
     * @param array $userData
     * @param array $tenantData
     * @return array
     */
    public function register(array $userData, array $tenantData): array
    {
        try {
            DB::beginTransaction();

            // Tạo tenant trước
            $tenant = Tenant::create($tenantData);

            // Tạo user với tenant_id
            $userData['tenant_id'] = $tenant->id;
            $userData['password'] = Hash::make($userData['password']);
            $user = User::create($userData);

            $token = $this->generateToken($user);

            DB::commit();

            return [
                'success' => true,
                'user' => $user->load('tenant'),
                'tenant' => $tenant,
                'token' => $token
            ];
        } catch (Exception $e) {
            DB::rollBack();
            return [
                'success' => false,
                'message' => 'Lỗi đăng ký: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Lấy thông tin user hiện tại từ token
     *
     * @return User|null
     */
    public function getCurrentUser(): ?User
    {
        try {
            $token = request()->bearerToken();
            if (!$token) {
                return null;
            }

            $payload = $this->validateToken($token);
            if (!$payload) {
                return null;
            }

            return User::with('tenant')->find($payload['user_id']);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Refresh JWT token
     *
     * @return array
     */
    public function refreshToken(): array
    {
        try {
            $user = $this->getCurrentUser();
            if (!$user) {
                return [
                    'success' => false,
                    'message' => 'Token không hợp lệ'
                ];
            }

            $newToken = $this->generateToken($user);

            return [
                'success' => true,
                'token' => $newToken
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Không thể refresh token: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Đăng xuất user (invalidate token)
     *
     * @return array
     */
    public function logout(): array
    {
        // Với JWT stateless, chúng ta chỉ cần client xóa token
        // Trong production, có thể implement blacklist token
        return [
            'success' => true,
            'message' => 'Đăng xuất thành công'
        ];
    }

    /**
     * Kiểm tra quyền của user
     *
     * @param string $permission
     * @param string|null $projectId
     * @return bool
     */
    public function checkPermission(string $permission, ?string $projectId = null): bool
    {
        $user = $this->getCurrentUser();
        if (!$user) {
            return false;
        }

        // TODO: Implement RBAC permission checking logic
        // Tạm thời return true cho development
        return true;
    }

    /**
     * Tạo JWT token cho user
     *
     * @param User $user
     * @return string
     */
    private function generateToken(User $user): string
    {
        $now = time();
        $payload = [
            'iss' => config('app.url'),
            'iat' => $now,
            'exp' => $now + $this->jwtTtl,
            'nbf' => $now,
            'sub' => (string) $user->id,
            'jti' => uniqid(),
            'user_id' => $user->id,
            'tenant_id' => $user->tenant_id,
            'email' => $user->email,
            'system_roles' => [] // TODO: Load user roles từ database
        ];

        return JWT::encode($payload, $this->jwtSecret, $this->jwtAlgo);
    }

    /**
     * Validate JWT token và trả về payload
     *
     * @param string $token
     * @return array|null
     */
    public function validateToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->jwtSecret, $this->jwtAlgo));
            return (array) $decoded;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Lấy payload từ JWT token
     *
     * @param string $token
     * @return array|null
     */
    public function getTokenPayload(string $token): ?array
    {
        return $this->validateToken($token);
    }
}