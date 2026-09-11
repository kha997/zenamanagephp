<?php

declare(strict_types=1);

namespace Tests\Concerns;

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Laravel\Sanctum\PersonalAccessToken;
use LogicException;

trait InteractsWithSanctumBearerTokens
{
    private ?PersonalAccessToken $issuedSanctumBearerToken = null;

    /**
     * Configure the next request with a genuine Sanctum Bearer token after
     * discarding any cached guard instances left by earlier test helpers.
     *
     * @param  array<int, string>  $abilities
     */
    protected function actingAsSanctumBearerToken(User $user, array $abilities = ['*']): static
    {
        $newAccessToken = $user->createToken('gap051-bearer-transport', $abilities);
        $this->issuedSanctumBearerToken = $newAccessToken->accessToken;
        $this->withToken($newAccessToken->plainTextToken);

        /** @var AuthManager $auth */
        $auth = $this->app->make(AuthManager::class);
        $auth->forgetGuards();

        return $this;
    }

    protected function issuedSanctumBearerToken(): PersonalAccessToken
    {
        return $this->issuedSanctumBearerToken
            ?? throw new LogicException('No Sanctum Bearer token has been issued by the GAP-051 helper.');
    }
}
