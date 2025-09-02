<?php declare(strict_types=1);

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Config;
use App\Models\User;
use App\Models\Tenant;
use Src\RBAC\Services\AuthService;
use Src\RBAC\Models\Role;
use Src\RBAC\Models\Permission;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Carbon\Carbon;

/**
 * Unit tests cho AuthService
 * 
 * Kiểm tra các chức năng xác thực JWT, đăng nhập, đăng ký,
 * tạo và xác thực token, quản lý quyền người dùng
 */
class AuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private AuthService $authService;
    private Tenant $tenant;
    private User $user;

    /**
     * Thiết lập dữ liệu test cho mỗi test case
     */
    protected function setUp(): void
    {
        parent::setUp();
        
        // Tạo tenant test
        $this->tenant = Tenant::factory()->create([
            'name' => 'Test Tenant',
            'domain' => 'test.example.com'
        ]);
        
        // Tạo user test
        $this->user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => Hash::make('password123'),
            'tenant_id' => $this->tenant->id
        ]);
        
        // Khởi tạo AuthService
        $this->authService = new AuthService();
        
        // Cấu hình JWT cho test
        Config::set('jwt.secret', 'test-secret-key-for-unit-testing');
        Config::set('jwt.ttl', 60); // 60 phút
        Config::set('jwt.refresh_ttl', 20160); // 2 tuần
        Config::set('jwt.algo', 'HS256');
    }

    /**
     * Test đăng nhập thành công với thông tin hợp lệ
     */
    public function test_login_success_with_valid_credentials(): void
    {
        $result = $this->authService->login('test@example.com', 'password123');
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertEquals('bearer', $result['token_type']);
        $this->assertIsString($result['access_token']);
        $this->assertIsInt($result['expires_in']);
    }

    /**
     * Test đăng nhập thất bại với email không tồn tại
     */
    public function test_login_fails_with_invalid_email(): void
    {
        $result = $this->authService->login('nonexistent@example.com', 'password123');
        
        $this->assertNull($result);
    }

    /**
     * Test đăng nhập thất bại với mật khẩu sai
     */
    public function test_login_fails_with_wrong_password(): void
    {
        $result = $this->authService->login('test@example.com', 'wrongpassword');
        
        $this->assertNull($result);
    }

    /**
     * Test đăng ký người dùng mới thành công
     */
    public function test_register_success_with_valid_data(): void
    {
        $userData = [
            'name' => 'New User',
            'email' => 'newuser@example.com',
            'password' => 'newpassword123',
            'tenant_id' => $this->tenant->id
        ];
        
        $result = $this->authService->register($userData);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        
        // Kiểm tra user được tạo trong database
        $this->assertDatabaseHas('users', [
            'email' => 'newuser@example.com',
            'name' => 'New User',
            'tenant_id' => $this->tenant->id
        ]);
    }

    /**
     * Test đăng ký thất bại với email đã tồn tại
     */
    public function test_register_fails_with_existing_email(): void
    {
        $userData = [
            'name' => 'Another User',
            'email' => 'test@example.com', // Email đã tồn tại
            'password' => 'password123',
            'tenant_id' => $this->tenant->id
        ];
        
        $this->expectException(\Exception::class);
        $this->authService->register($userData);
    }

    /**
     * Test tạo token cho user thành công
     */
    public function test_create_token_for_user_success(): void
    {
        $token = $this->authService->createTokenForUser($this->user);
        
        $this->assertIsString($token);
        $this->assertNotEmpty($token);
        
        // Kiểm tra token có thể decode được
        $payload = $this->authService->getTokenPayload($token);
        $this->assertEquals($this->user->id, $payload['sub']);
        $this->assertEquals($this->user->tenant_id, $payload['tenant_id']);
    }

    /**
     * Test xác thực token hợp lệ
     */
    public function test_validate_token_success_with_valid_token(): void
    {
        $token = $this->authService->createTokenForUser($this->user);
        
        $isValid = $this->authService->validateToken($token);
        
        $this->assertTrue($isValid);
    }

    /**
     * Test xác thực token thất bại với token không hợp lệ
     */
    public function test_validate_token_fails_with_invalid_token(): void
    {
        $invalidToken = 'invalid.jwt.token';
        
        $isValid = $this->authService->validateToken($invalidToken);
        
        $this->assertFalse($isValid);
    }

    /**
     * Test xác thực token thất bại với token đã hết hạn
     */
    public function test_validate_token_fails_with_expired_token(): void
    {
        // Tạo token với thời gian hết hạn trong quá khứ
        $payload = [
            'iss' => config('app.url'),
            'sub' => $this->user->id,
            'tenant_id' => $this->user->tenant_id,
            'iat' => Carbon::now()->subHours(2)->timestamp,
            'exp' => Carbon::now()->subHour()->timestamp // Đã hết hạn 1 giờ trước
        ];
        
        $expiredToken = JWT::encode($payload, config('jwt.secret'), config('jwt.algo'));
        
        $isValid = $this->authService->validateToken($expiredToken);
        
        $this->assertFalse($isValid);
    }

    /**
     * Test lấy thông tin user hiện tại từ token
     */
    public function test_get_current_user_success_with_valid_token(): void
    {
        $token = $this->authService->createTokenForUser($this->user);
        
        $currentUser = $this->authService->getCurrentUser($token);
        
        $this->assertInstanceOf(User::class, $currentUser);
        $this->assertEquals($this->user->id, $currentUser->id);
        $this->assertEquals($this->user->email, $currentUser->email);
    }

    /**
     * Test lấy thông tin user thất bại với token không hợp lệ
     */
    public function test_get_current_user_fails_with_invalid_token(): void
    {
        $invalidToken = 'invalid.jwt.token';
        
        $currentUser = $this->authService->getCurrentUser($invalidToken);
        
        $this->assertNull($currentUser);
    }

    /**
     * Test làm mới token thành công
     */
    public function test_refresh_token_success_with_valid_token(): void
    {
        $originalToken = $this->authService->createTokenForUser($this->user);
        
        // Đợi 1 giây để đảm bảo timestamp khác nhau
        sleep(1);
        
        $result = $this->authService->refreshToken($originalToken);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertNotEquals($originalToken, $result['access_token']);
    }

    /**
     * Test kiểm tra quyền người dùng
     */
    public function test_check_permission_with_user_having_permission(): void
    {
        // Tạo role và permission
        $role = Role::factory()->create([
            'name' => 'Test Role',
            'scope' => 'system'
        ]);
        
        $permission = Permission::factory()->create([
            'code' => 'task.create',
            'module' => 'task',
            'action' => 'create'
        ]);
        
        // Gán permission cho role
        $role->permissions()->attach($permission->id);
        
        // Gán role cho user
        $this->user->systemRoles()->attach($role->id);
        
        $hasPermission = $this->authService->checkPermission($this->user, 'task.create');
        
        $this->assertTrue($hasPermission);
    }

    /**
     * Test kiểm tra quyền với user không có quyền
     */
    public function test_check_permission_with_user_not_having_permission(): void
    {
        $hasPermission = $this->authService->checkPermission($this->user, 'nonexistent.permission');
        
        $this->assertFalse($hasPermission);
    }

    /**
     * Test logout thành công
     */
    public function test_logout_success(): void
    {
        $token = $this->authService->createTokenForUser($this->user);
        
        $result = $this->authService->logout($token);
        
        $this->assertTrue($result);
    }

    /**
     * Test lấy payload từ token hợp lệ
     */
    public function test_get_token_payload_success_with_valid_token(): void
    {
        $token = $this->authService->createTokenForUser($this->user);
        
        $payload = $this->authService->getTokenPayload($token);
        
        $this->assertIsArray($payload);
        $this->assertArrayHasKey('sub', $payload);
        $this->assertArrayHasKey('tenant_id', $payload);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertEquals($this->user->id, $payload['sub']);
        $this->assertEquals($this->user->tenant_id, $payload['tenant_id']);
    }

    /**
     * Test lấy payload thất bại với token không hợp lệ
     */
    public function test_get_token_payload_fails_with_invalid_token(): void
    {
        $invalidToken = 'invalid.jwt.token';
        
        $payload = $this->authService->getTokenPayload($invalidToken);
        
        $this->assertNull($payload);
    }
}