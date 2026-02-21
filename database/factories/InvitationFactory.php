<?php declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invitation;
use App\Models\Team;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    protected $model = Invitation::class;

    public function definition(): array
    {
        $tenant = Tenant::query()->inRandomOrder()->first();
        if (! $tenant instanceof Tenant) {
            $tenant = Tenant::factory()->createOne();
        }
        /** @var Tenant $tenant */

        $inviter = User::query()
            ->where('tenant_id', $tenant->id)
            ->inRandomOrder()
            ->first();
        if (! $inviter instanceof User) {
            $inviter = User::factory()->createOne(['tenant_id' => $tenant->id]);
        }
        /** @var User $inviter */

        $team = Team::query()
            ->where('tenant_id', $tenant->id)
            ->inRandomOrder()
            ->first();
        if (! $team instanceof Team) {
            $team = Team::factory()->createOne([
                'tenant_id' => $tenant->id,
                'created_by' => $inviter->id,
                'updated_by' => $inviter->id,
            ]);
        }
        /** @var Team $team */

        $rawToken = Str::random(80);

        return [
            'tenant_id' => $tenant->id,
            'team_id' => $team->id,
            'token' => null,
            'token_hash' => hash('sha256', $rawToken),
            'token_version' => Invitation::TOKEN_VERSION_HASH_ONLY,
            'email' => strtolower('invite+' . Str::ulid() . '@example.com'),
            'first_name' => $this->faker->firstName(),
            'last_name' => $this->faker->lastName(),
            'role' => Team::ROLE_MEMBER,
            'message' => $this->faker->optional()->sentence(),
            'organization_id' => (int) ($inviter->organization_id ?? 0),
            'project_id' => null,
            'invited_by' => 0,
            'invited_by_user_id' => $inviter->id,
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
            'accepted_at' => null,
            'accepted_by' => null,
            'accepted_by_user_id' => null,
            'revoked_at' => null,
            'revoked_by_user_id' => null,
            'metadata' => ['team_name' => $team->name],
            'notes' => null,
        ];
    }

    public function configure(): static
    {
        return $this;
    }

    public function expired(): static
    {
        return $this->state(fn (): array => [
            'expires_at' => now()->subMinute(),
        ]);
    }

    public function accepted(): static
    {
        return $this->state(fn (): array => [
            'status' => Invitation::STATUS_ACCEPTED,
            'accepted_at' => now(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'status' => Invitation::STATUS_CANCELLED,
            'revoked_at' => now(),
        ]);
    }

    public function withRawToken(?string $rawToken = null): static
    {
        $token = $rawToken !== null && $rawToken !== '' ? $rawToken : Str::random(80);

        return $this
            ->state(fn (): array => [
                'token' => null,
                'token_hash' => hash('sha256', $token),
                'token_version' => Invitation::TOKEN_VERSION_HASH_ONLY,
            ]);
    }
}
