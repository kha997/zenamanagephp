<?php declare(strict_types=1);

namespace App\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Sanctum\Events\TokenAuthenticated;
use Laravel\Sanctum\Guard;
use Laravel\Sanctum\Sanctum;
use Laravel\Sanctum\TransientToken;

class CustomSanctumGuard extends Guard
{
    public function __invoke(Request $request)
    {
        foreach (Arr::wrap(config('sanctum.guard', 'web')) as $guard) {
            if ($user = $this->auth->guard($guard)->user()) {
                return $this->supportsTokens($user)
                    ? $user->withAccessToken(new TransientToken)
                    : $user;
            }
        }

        if ($token = $this->getTokenFromRequest($request)) {
            $model = Sanctum::$personalAccessTokenModel;

            $accessToken = $model::findToken($token);

            if (! $this->isValidAccessToken($accessToken) ||
                ! $this->supportsTokens($accessToken->tokenable)) {
                return;
            }

            $tokenable = $accessToken->tokenable->withAccessToken(
                $accessToken
            );

            event(new TokenAuthenticated($accessToken));

            if (config('sanctum.update_last_used_at', true)) {
                if (method_exists($accessToken->getConnection(), 'hasModifiedRecords') &&
                    method_exists($accessToken->getConnection(), 'setRecordModificationState')) {
                    tap($accessToken->getConnection()->hasModifiedRecords(), function ($hasModifiedRecords) use ($accessToken) {
                        $accessToken->forceFill(['last_used_at' => now()])->save();

                        $accessToken->getConnection()->setRecordModificationState($hasModifiedRecords);
                    });
                } else {
                    $accessToken->forceFill(['last_used_at' => now()])->save();
                }
            }

            return $tokenable;
        }
    }
    /**
     * Strict Bearer token parsing:
     * - Accept only: "Bearer <token>" (case-insensitive, no extra prefix).
     * - If no Authorization header is present, fall back to parent (keeps cookie-based Sanctum behavior).
     */
    protected function getTokenFromRequest(Request $request)
    {
        $header = $request->header('Authorization');

        if (!is_string($header) || $header === '') {
            return parent::getTokenFromRequest($request);
        }

        $normalized = ltrim($header);

        if (strncasecmp($normalized, 'Bearer ', 7) === 0) {
            return $request->bearerToken();
        }

        return null;
    }

}
