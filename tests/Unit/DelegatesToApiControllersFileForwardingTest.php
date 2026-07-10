<?php declare(strict_types=1);

namespace Tests\Unit;

use App\Http\Controllers\Web\Concerns\DelegatesToApiControllers;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

class DelegatesToApiControllersFileForwardingTest extends TestCase
{
    public function test_build_api_request_forwards_files(): void
    {
        $harness = new class {
            use DelegatesToApiControllers;

            public function callBuild(Request $request, array $payload, array $files): Request
            {
                return $this->buildApiRequest($request, $payload, $files);
            }
        };

        $original = Request::create('/test', 'POST');
        $mockSession = $this->createMock(\Illuminate\Contracts\Session\Session::class);
        $original->setLaravelSession($mockSession);
        $file = UploadedFile::fake()->create('a.pdf', 10);

        $rebuilt = $harness->callBuild($original, ['comment' => 'hi'], ['file' => $file]);

        $this->assertTrue($rebuilt->hasFile('file'));
        $this->assertSame('hi', $rebuilt->input('comment'));
    }
}
