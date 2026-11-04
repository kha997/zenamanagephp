<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Treasury;

use App\Http\Controllers\Controller;
use App\Models\Project;
use App\Models\Treasury\TreasuryDocumentAttachment;
use App\Models\Treasury\TreasuryFinancialDocument;
use App\Models\User;
use App\Services\Treasury\TreasuryEvidenceService;
use App\Services\Treasury\TreasuryReportService;
use App\Services\Treasury\TreasuryRuleViolation;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * GAP-106 — evidence of one Treasury money document: list, attach, download, remove; and the tenant switch
 * "expenses need evidence" (M-1). Every read goes through the Treasury project policy (S-2).
 */
class TreasuryEvidencePageController extends Controller
{
    public function __construct(private readonly TreasuryEvidenceService $evidence)
    {
    }

    public function show(string $project, string $treasuryDocument): View
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $document = $this->findDocument($model, $treasuryDocument);
        $user = $this->user();

        return view('treasury.evidence', [
            'project' => $model,
            'document' => $document,
            'typeLabel' => TreasuryReportService::TYPE_LABELS[$document->document_type] ?? $document->document_type,
            'attachments' => $this->evidence->attachments($model, $document),
            'canAttach' => $document->document_type !== TreasuryFinancialDocument::TYPE_REVERSAL && $this->evidence->canAttach($user, $model, $document),
            'canRemove' => fn (TreasuryDocumentAttachment $attachment): bool => $this->evidence->canRemove($user, $document, $attachment),
            'uploaders' => User::query()->where('tenant_id', (string) $model->tenant_id)->pluck('name', 'id')->all(),
        ]);
    }

    public function store(Request $request, string $project, string $treasuryDocument): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $document = $this->findDocument($model, $treasuryDocument);
        $data = $request->validate([
            'file' => ['required', 'file', 'max:' . TreasuryEvidenceService::MAX_KILOBYTES, 'mimes:' . implode(',', TreasuryEvidenceService::ALLOWED_EXTENSIONS)],
            'evidence_type' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        try {
            /** @var \Illuminate\Http\UploadedFile $file */
            $file = $request->file('file');
            $this->evidence->attach($model, $this->user(), $document, $file, (string) $data['evidence_type'], $data['note'] ?? null);
        } catch (TreasuryRuleViolation $e) {
            return back()->withInput()->withErrors([$e->field ?? 'file' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.evidence.show', [$model->id, $document->id])->with('success', 'Đã đính chứng từ.');
    }

    public function download(string $project, string $treasuryDocument, string $attachment): StreamedResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $document = $this->findDocument($model, $treasuryDocument);
        $file = $this->evidence->attachmentOf($model, $document, $attachment);
        abort_if($file === null, 404);
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk($this->evidence->disk());
        abort_unless($disk->exists($file->file_path), 404);

        $response = $disk->download($file->file_path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);

        return $response;
    }

    public function destroy(string $project, string $treasuryDocument, string $attachment): RedirectResponse
    {
        $model = $this->findProject($project);
        $this->authorize('treasury.view-project', $model);
        $document = $this->findDocument($model, $treasuryDocument);
        $file = $this->evidence->attachmentOf($model, $document, $attachment);
        abort_if($file === null, 404);

        try {
            $this->evidence->remove($model, $this->user(), $document, $file);
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'file' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.projects.evidence.show', [$model->id, $document->id])->with('success', 'Đã gỡ chứng từ.');
    }

    public function updatePolicy(Request $request): RedirectResponse
    {
        abort_unless($this->evidence->canChangePolicy($this->user()), 403);

        try {
            $this->evidence->setEvidenceRequired($this->user(), $request->boolean('evidence_required'));
        } catch (TreasuryRuleViolation $e) {
            return back()->withErrors([$e->field ?? 'evidence_required' => $e->getMessage()]);
        }

        return redirect()->route('operator.treasury.index')->with('success', 'Đã cập nhật quy định chứng từ.');
    }

    private function findProject(string $id): Project
    {
        /** @var Project $project */
        $project = Project::query()
            ->where('tenant_id', (string) $this->user()->tenant_id)
            ->whereKey($id)
            ->firstOrFail();

        return $project;
    }

    private function findDocument(Project $project, string $id): TreasuryFinancialDocument
    {
        /** @var TreasuryFinancialDocument $document */
        $document = TreasuryFinancialDocument::query()
            ->where('tenant_id', (string) $project->tenant_id)
            ->where('project_id', (string) $project->id)
            ->whereKey($id)
            ->firstOrFail();

        return $document;
    }

    private function user(): User
    {
        /** @var User $user */
        $user = Auth::user();

        return $user;
    }
}
