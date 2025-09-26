<?php declare(strict_types=1);

namespace App\Auth;

use Illuminate\Auth\GuardHelpers;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Auth\UserProvider;

class SimpleJwtGuard implements Guard
{
    use GuardHelpers;

    protected AuthService $authService;

    public function __construct(UserProvider $provider, AuthService $authService)
    {
        $this->provider = $provider;
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

    public function login($user): string
    {
        $this->setUser($user);
        return $this->authService->createTokenForUser($user);
    }

    protected function getTokenFromRequest(): ?string
    {
        try {
            // Try to get request from container
            if (app()->bound('request')) {
                $request = app('request');
                $header = $request->header('Authorization');
                
                if ($header && str_starts_with($header, 'Bearer ')) {
                    return substr($header, 7);
                }
            }
            
            // Fallback: return null if no request available
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
}