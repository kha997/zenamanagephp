<?php declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Requests\ProjectBulkCreateRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Mockery;
use Tests\TestCase;

class ProjectBulkCreateRequestTest extends TestCase
{
    public function test_authorize_uses_canonical_project_create_permission(): void
    {
        $user = Mockery::mock(User::class);
        $user->shouldReceive('hasPermission')
            ->once()
            ->with('project.create')
            ->andReturnTrue();

        Auth::shouldReceive('check')->once()->andReturnTrue();
        Auth::shouldReceive('user')->once()->andReturn($user);

        $request = new ProjectBulkCreateRequest();

        $this->assertTrue($request->authorize());
    }
}
