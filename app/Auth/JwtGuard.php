<?php declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Http\Request;
use Src\RBAC\Services\AuthService;

class JwtGuard implements Guard
{
    use GuardHelpers;

    protected Request $request;
    protected AuthService $authService;

    public function __construct(UserProvider $provider, Request $request, AuthService $authService)
    {
        $this->provider = $provider;
        $this->request = $request;
        $this->authService = $authService;
    }

    public function user()
    {
        if ($this->user !== null) {
            return $this->user;
        }

        $token = $this->getTokenFromRequest();
        
        if ($token) {
            $payload = $this->authService->validateToken($token);
            if ($payload) {
                $this->user = $this->provider->retrieveById($payload['user_id'] ?? null);
            }
        }

        return $this->user;
    }

    public function validate(array $credentials = [])
    {
        return false;
    }

    protected function getTokenFromRequest(): ?string
    {
        $header = $this->request->header('Authorization');
        
        if ($header && str_starts_with($header, 'Bearer ')) {
            return substr($header, 7);
        }

        return null;
    }
}