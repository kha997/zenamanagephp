<?php declare(strict_types=1);

namespace Tests\Traits;

use App\Models\User;

trait SanctumAuthTestTrait
{
    protected function generateJwtToken(User $user): string
    {
        return $user->createToken('test-jwt-token')->plainTextToken;
    }
}
