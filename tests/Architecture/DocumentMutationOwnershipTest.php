<?php declare(strict_types=1);

namespace Tests\Architecture;

use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Facades\Route;
use Illuminate\Routing\Route as RoutingRoute;
use Tests\TestCase;

/**
 * GAP-032 Task 7 — ownership inventory for every routed Document mutator.
 *
 * Every routed method on a class that touches the canonical Document surface is
 * classified into exactly one of two categories:
 *
 *  - GOVERNED: it may change canonical state, Approval/audit fields,
 *    `current_version_id`, or create `DocumentVersion` rows, and must therefore
 *    delegate to one of the governed services.
 *  - NON_STATE: it is an adapter-owned mutation that may keep using repository
 *    patterns, but must not write canonical state, workflow Approval/audit
 *    fields, `current_version_id`, or `DocumentVersion` rows.
 *
 * A third group is proven unreachable from any route (evidence allowlist), and
 * `App\Services\DocumentService` is classified separately because it operates on
 * the divergent `Src\DocumentManagement\Models\Document`.
 */
final class DocumentMutationOwnershipTest extends TestCase
{
    private const GOVERNED = 'governed';
    private const NON_STATE = 'non-state';

    /** @var list<string> */
    private const GOVERNED_SERVICES = [
        'DocumentCreationService',
        'DocumentVersionService',
        'DocumentLifecycleService',
        'DocumentWorkflowService',
        'DocumentStatusService',
    ];

    /**
     * Patterns that mark a routed method as touching the canonical Document surface.
     *
     * @var list<string>
     */
    private const DOCUMENT_SURFACE_PATTERNS = [
        // Constant reads (Document::VALID_*, Document::ENTITY_TYPE_*, ...) are not a surface touch.
        '/\bDocument::(?!VALID_|ENTITY_TYPE_|VISIBILITY_|DOCUMENT_TYPE_)/',
        '/\bDocumentVersion\b/',
        '/\$document\b/',
        '/\bDocumentCreationService\b/',
        '/\bDocumentVersionService\b/',
        '/\bDocumentLifecycleService\b/',
        '/\bDocumentWorkflowService\b/',
        '/\bDocumentStatusService\b/',
        "/'documents'/",
        '/\bcurrent_version_id\b/',
        '/\buploadDocument\b/',
    ];

    /**
     * Writes that only a governed service may perform.
     *
     * @var list<string>
     */
    private const PROTECTED_WRITE_TOKENS = [
        'lifecycle_status',
        'approval_status',
        'current_version_id',
        'DocumentVersion::',
        'createNewVersion',
        'revertToVersion',
        'writeState',
        'submitted_by',
        'decision_by',
        'document_approval_events',
    ];

    /**
     * Exactly one classification per routed method on the Document surface.
     *
     * @var array<string, string>
     */
    private const CLASSIFICATION = [
        // Canonical API adapter.
        'App\Http\Controllers\Api\SimpleDocumentController@store' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@update' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@createVersion' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@submit' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@decision' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@publish' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@archive' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@reopen' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@reactivate' => self::GOVERNED,
        'App\Http\Controllers\Api\SimpleDocumentController@attachLink' => self::NON_STATE,
        'App\Http\Controllers\Api\SimpleDocumentController@detachLink' => self::NON_STATE,
        'App\Http\Controllers\Api\SimpleDocumentController@destroy' => self::NON_STATE,

        // Design item adapter (creates/updates the linked canonical Document).
        'App\Http\Controllers\Api\DesignItemController@uploadDocument' => self::GOVERNED,

        // Web adapters.
        'App\Http\Controllers\Web\DocumentController@store' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@submit' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@approve' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@reject' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@publish' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@archive' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@reopen' => self::GOVERNED,
        'App\Http\Controllers\Web\DocumentWorkflowController@reactivate' => self::GOVERNED,

        // Design item page adapter — delegates to the API adapter, writes nothing itself.
        'App\Http\Controllers\Web\DesignItemPageController@uploadDocument' => self::NON_STATE,
    ];

    /**
     * Non-state surfaces that are allowlisted only because no route can reach them.
     *
     * @var list<string>
     */
    private const UNROUTED_NON_STATE_SURFACES = [
        \App\Services\SecureUploadService::class,
        \App\Http\Controllers\DocumentController::class,
        \App\Http\Controllers\Web\DocumentManagementController::class,
    ];

    public function test_every_routed_document_mutator_is_explicitly_classified(): void
    {
        $candidates = $this->routedDocumentSurfaceMethods();

        $unclassified = array_values(array_diff($candidates, array_keys(self::CLASSIFICATION)));
        self::assertSame([], $unclassified, 'Unclassified routed Document mutators: ' . implode(', ', $unclassified));

        $stale = array_values(array_diff(array_keys(self::CLASSIFICATION), $candidates));
        self::assertSame([], $stale, 'Classified entries that are no longer routed: ' . implode(', ', $stale));

        foreach (self::CLASSIFICATION as $key => $classification) {
            self::assertContains($classification, [self::GOVERNED, self::NON_STATE], $key);
        }
    }

    public function test_every_routed_state_or_version_mutator_uses_its_governed_service(): void
    {
        foreach (self::CLASSIFICATION as $key => $classification) {
            if ($classification !== self::GOVERNED) {
                continue;
            }

            [$class] = explode('@', $key, 2);
            $source = $this->methodSource($key);
            $usesGovernedService = false;
            foreach (self::GOVERNED_SERVICES as $service) {
                if (str_contains($source, $service)) {
                    $usesGovernedService = true;
                    break;
                }
            }
            foreach ($this->governedServiceProperties($class) as $property) {
                if (str_contains($source, '$this->' . $property)) {
                    $usesGovernedService = true;
                    break;
                }
            }

            self::assertTrue(
                $usesGovernedService,
                $key . ' is classified as a governed mutator but does not delegate to a governed service.'
            );
            self::assertStringNotContainsString('DocumentVersion::create', $source, $key . ' writes DocumentVersion rows directly.');
            self::assertStringNotContainsString('createNewVersion', $source, $key . ' uses a removed model mutation API.');
            self::assertStringNotContainsString('revertToVersion', $source, $key . ' uses a removed model mutation API.');
        }
    }

    public function test_non_state_adapter_mutators_cannot_write_protected_state_version_or_workflow_fields(): void
    {
        foreach (self::CLASSIFICATION as $key => $classification) {
            if ($classification !== self::NON_STATE) {
                continue;
            }

            $source = $this->methodSource($key);
            foreach (self::PROTECTED_WRITE_TOKENS as $token) {
                self::assertStringNotContainsString(
                    $token,
                    $source,
                    $key . ' is classified as a non-state adapter mutator but references the protected token "' . $token . '".'
                );
            }
        }

        // Evidence for the allowlisted surfaces: no route can reach them at all.
        $routedClasses = $this->routedControllerClasses();
        foreach (self::UNROUTED_NON_STATE_SURFACES as $class) {
            self::assertNotContains(
                $class,
                $routedClasses,
                $class . ' is allowlisted as an unreachable non-state surface but is now routed; classify it explicitly instead.'
            );
        }

        // App\Services\DocumentService is separately classified: it operates on the
        // divergent Src\DocumentManagement\Models\Document, never on App\Models\Document.
        $documentServiceSource = (string) file_get_contents(
            (string) (new ReflectionClass(\App\Services\DocumentService::class))->getFileName()
        );
        self::assertStringNotContainsString('App\\Models\\Document', $documentServiceSource);
        self::assertStringContainsString('Src\\DocumentManagement\\Models\\Document', $documentServiceSource);
    }

    /** @return list<string> */
    private function routedDocumentSurfaceMethods(): array
    {
        $mutatingVerbs = ['POST', 'PUT', 'PATCH', 'DELETE'];
        $methods = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            if (array_intersect($mutatingVerbs, $route->methods()) === []) {
                continue;
            }

            [$class, $method] = explode('@', $action, 2);
            if (! class_exists($class) || ! method_exists($class, $method)) {
                continue;
            }
            if (! $this->touchesDocumentSurface($this->methodSource($class . '@' . $method))) {
                continue;
            }

            $methods[$class . '@' . $method] = true;
        }

        $keys = array_keys($methods);
        sort($keys);

        return $keys;
    }

    /** @return list<string> */
    private function routedControllerClasses(): array
    {
        $classes = [];

        foreach (Route::getRoutes() as $route) {
            /** @var RoutingRoute $route */
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            $classes[explode('@', $action, 2)[0]] = true;
        }

        return array_keys($classes);
    }

    /**
     * Constructor-injected governed services, so `$this->workflow->submit()` counts as
     * delegation just like `app(DocumentWorkflowService::class)`.
     *
     * @return list<string>
     */
    private function governedServiceProperties(string $class): array
    {
        $properties = [];

        foreach ((new ReflectionClass($class))->getProperties() as $property) {
            $type = $property->getType();
            if (! $type instanceof \ReflectionNamedType) {
                continue;
            }
            $shortName = ($position = strrpos($type->getName(), '\\')) === false
                ? $type->getName()
                : substr($type->getName(), $position + 1);
            if (in_array($shortName, self::GOVERNED_SERVICES, true)) {
                $properties[] = $property->getName();
            }
        }

        return $properties;
    }

    private function touchesDocumentSurface(string $source): bool
    {
        foreach (self::DOCUMENT_SURFACE_PATTERNS as $pattern) {
            if (preg_match($pattern, $source) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * The adapter method body plus the bodies of the same class's private helpers it
     * calls, so that a mutation cannot be hidden one indirection away.
     */
    private function methodSource(string $key): string
    {
        [$class, $method] = explode('@', $key, 2);
        self::assertTrue(method_exists($class, $method), $key . ' no longer exists.');

        $source = $this->rawMethodSource($class, $method);
        if (preg_match_all('/\$this->([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $source, $matches) === false) {
            return $source;
        }

        foreach (array_unique($matches[1]) as $helper) {
            if ($helper === $method || ! method_exists($class, $helper)) {
                continue;
            }
            $reflection = new ReflectionMethod($class, $helper);
            if ($reflection->isPublic() || $reflection->getDeclaringClass()->getName() !== $class) {
                continue;
            }
            $source .= "\n" . $this->rawMethodSource($class, $helper);
        }

        return $source;
    }

    private function rawMethodSource(string $class, string $method): string
    {
        $reflection = new ReflectionMethod($class, $method);
        $file = $reflection->getFileName();
        if ($file === false) {
            return '';
        }

        $lines = (array) file($file);
        $start = (int) $reflection->getStartLine() - 1;
        $length = (int) $reflection->getEndLine() - $start;

        return implode('', array_slice($lines, $start, $length));
    }
}
