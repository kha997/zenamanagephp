<?php declare(strict_types=1);

namespace Tests\Feature\Architecture;

use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\DocumentWorkflowController;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * GAP-031: DocumentController::approve()/reject() were dead code — unrouted,
 * writing to non-fillable/non-existent columns (approved_by/rejected_by/...),
 * using a 'pending' status that doesn't exist elsewhere. Replaced by
 * DocumentWorkflowController::approve()/reject() calling DocumentWorkflowService.
 * This guard blocks either the old methods or their route names from coming back.
 */
class DocumentApprovalDeadMethodsRemovedTest extends TestCase
{
    public function test_dead_approve_reject_methods_removed_from_document_controller(): void
    {
        $this->assertFalse(
            method_exists(DocumentController::class, 'approve'),
            'DocumentController::approve() phải bị xoá — thay bằng DocumentWorkflowController::approve().'
        );
        $this->assertFalse(
            method_exists(DocumentController::class, 'reject'),
            'DocumentController::reject() phải bị xoá — thay bằng DocumentWorkflowController::reject().'
        );
    }

    public function test_web_routes_do_not_reference_dead_document_controller_methods(): void
    {
        $source = File::get(base_path('routes/web.php'));

        $this->assertStringNotContainsString(
            "DocumentController::class, 'approve'",
            $source,
            'routes/web.php không được trỏ tới DocumentController::approve() (đã xoá).'
        );
        $this->assertStringNotContainsString(
            "DocumentController::class, 'reject'",
            $source,
            'routes/web.php không được trỏ tới DocumentController::reject() (đã xoá).'
        );
    }

    public function test_canonical_workflow_owns_decision_routes(): void
    {
        $this->assertTrue(Route::has('app.documents.workflow.submit'));
        $this->assertTrue(Route::has('app.documents.workflow.approve'));
        $this->assertTrue(Route::has('app.documents.workflow.reject'));

        $approveRoute = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'app.documents.workflow.approve');
        $rejectRoute = collect(Route::getRoutes())->first(fn ($r) => $r->getName() === 'app.documents.workflow.reject');

        $this->assertSame(DocumentWorkflowController::class, $approveRoute->getControllerClass());
        $this->assertSame(DocumentWorkflowController::class, $rejectRoute->getControllerClass());
    }
}
