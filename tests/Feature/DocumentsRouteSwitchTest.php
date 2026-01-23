<?php declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Http\Request;
use Tests\TestCase;

final class DocumentsRouteSwitchTest extends TestCase
{
    private function documentRouteAction(string $uri, string $method = 'GET'): string
    {
        $request = Request::create($uri, $method);
        $route = app('router')->getRoutes()->match($request);

        return $route->getActionName();
    }

    private function assertDocumentRoutesResolveTo(string $controllerClass, string $downloadAction): void
    {
        $actionPrefix = $controllerClass . '@';

        $this->assertSame($actionPrefix . 'index', $this->documentRouteAction('/api/documents'));
        $this->assertSame($actionPrefix . 'show', $this->documentRouteAction('/api/documents/123'));
        $this->assertSame($actionPrefix . $downloadAction, $this->documentRouteAction('/api/documents/123/download'));
        $this->assertSame($actionPrefix . 'getVersions', $this->documentRouteAction('/api/documents/123/versions'));
        $this->assertSame(
            $actionPrefix . 'createVersion',
            $this->documentRouteAction('/api/documents/123/versions', 'POST')
        );
    }

    public function test_default_routes_use_legacy_document_controller(): void
    {
        putenv('API_CANONICAL_DOCUMENTS=0');
        $this->refreshApplication();

        $this->assertDocumentRoutesResolveTo(
            \App\Http\Controllers\Api\DocumentController::class,
            'download'
        );
    }

    public function test_canonical_flag_switches_to_src_controller(): void
    {
        putenv('API_CANONICAL_DOCUMENTS=1');
        $this->refreshApplication();

        $this->assertDocumentRoutesResolveTo(
            \Src\DocumentManagement\Controllers\DocumentController::class,
            'downloadVersion'
        );

        putenv('API_CANONICAL_DOCUMENTS=0');
    }
}
