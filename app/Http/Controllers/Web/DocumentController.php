<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Models\Document;
use Src\CoreProject\Models\Project;

/**
 * Web Document Controller for document management interface
 * 
 * @package App\Http\Controllers\Web
 */
class DocumentController extends Controller
{
    /**
     * DocumentController constructor.
     */
    public function __construct()
    {
    }

    /**
     * Display a listing of documents.
     */
    public function index(Request $request): View
    {
        $tenantId = (string) Auth::user()?->tenant_id;

        $query = Document::query()
            ->with(['project:id,tenant_id,name,code', 'uploader:id,name'])
            ->whereHas('project', fn ($projectQuery) => $projectQuery->where('tenant_id', $tenantId));

        if ($request->filled('project_id')) {
            $query->where('project_id', (string) $request->input('project_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', (string) $request->input('status'));
        }

        return view('documents.index', [
            'documents' => $query->orderByDesc('created_at')->paginate(20)->withQueryString(),
            'projects' => Project::query()
                ->where('tenant_id', $tenantId)
                ->orderBy('name')
                ->get(['id', 'tenant_id', 'name', 'code']),
        ]);
    }

    /**
     * Show the form for creating a new document.
     */
    public function create(Request $request): View
    {
        try {
            $projects = Project::query()
                ->where('tenant_id', (string) Auth::user()?->tenant_id)
                ->select('id', 'name')
                ->get();
            $projectId = $request->get('project_id');

            return view('documents.create', compact('projects', 'projectId'));
        } catch (\Exception $e) {
            return view('documents.create', [
                'projects' => collect(),
                'projectId' => null,
                'error' => 'Không thể tải form tạo document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Store a newly uploaded document.
     */
    public function store(
        Request $request,
        \App\Http\Controllers\Api\SimpleDocumentController $apiController
    ): RedirectResponse {
        $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'project_id' => ['required', 'string'],
            'document_type' => ['required', Rule::in(Document::VALID_DOCUMENT_TYPES)],
            'file' => ['required', 'file', 'max:10240'],
        ]);

        // Multipart: dựng request thủ công để giữ uploaded files
        $apiRequest = Request::create(
            $request->fullUrl(),
            'POST',
            array_merge(
                $request->only(['title', 'project_id', 'document_type', 'description']),
                ['status' => \App\Enums\DocumentWorkflowStatus::DRAFT->value]
            ),
            $request->cookies->all(),
            $request->files->all(),
            $request->server->all()
        );
        $apiRequest->headers->replace($request->headers->all());
        $apiRequest->setLaravelSession($request->session());
        $apiRequest->setUserResolver(static fn () => $request->user());

        try {
            $response = $apiController->store($apiRequest);

            $status = $response->getStatusCode();
            if ($status >= 200 && $status < 300) {
                return redirect('/app/documents')->with('success', 'Đã tải tài liệu lên');
            }

            $payload = $response->getData(true);

            return redirect()
                ->back()
                ->withInput()
                ->with('error', (string) ($payload['message'] ?? 'Không thể tải tài liệu lên.'));
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()->back()->withErrors($exception->errors())->withInput();
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['error' => 'Không thể upload document: ' . $e->getMessage()]);
        }
    }

    /**
     * Display the specified document.
     */
    public function show(string $documentId): View
    {
        try {
            $document = Document::with(['project', 'uploader'])
                ->whereHas('project', fn ($projectQuery) => $projectQuery->where('tenant_id', (string) Auth::user()?->tenant_id))
                ->findOrFail($documentId);
            
            return view('documents.show', compact('document'));
        } catch (\Exception $e) {
            return view('documents.show', [
                'document' => null,
                'error' => 'Không thể tải document: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Download the specified document.
     */
    public function download(string $documentId)
    {
        try {
            $document = Document::findOrFail($documentId);
            
            // Check if file exists
            if (!Storage::exists($document->file_path)) {
                return redirect()
                    ->back()
                    ->withErrors(['error' => 'File không tồn tại trên server.']);
            }
            
            return Storage::download($document->file_path, $document->original_name);
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Không thể download document: ' . $e->getMessage()]);
        }
    }

    /**
     * Show documents pending approval.
     */
    public function approvals(Request $request): View
    {
        try {
            $tenantId = (string) Auth::user()?->tenant_id;

            $query = Document::with(['project', 'uploader'])
                ->whereHas('project', fn ($projectQuery) => $projectQuery->where('tenant_id', $tenantId));

            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $documents = $query->orderBy('created_at', 'desc')->paginate(15);
            $projects = Project::query()->where('tenant_id', $tenantId)->select('id', 'name')->get();
            $decisionUsers = $this->decisionUsersFor($documents, $tenantId);

            return view('documents.approvals', compact('documents', 'projects', 'decisionUsers'));
        } catch (\Throwable $e) {
            report($e);

            return view('documents.approvals', [
                'documents' => collect(),
                'projects' => collect(),
                'decisionUsers' => collect(),
                'error' => 'Không thể tải danh sách tài liệu cần duyệt. Vui lòng thử lại sau.',
            ]);
        }
    }

    /**
     * @param \Illuminate\Pagination\LengthAwarePaginator<int, Document> $paginatedDocuments
     * @return \Illuminate\Support\Collection<string, string>
     */
    public function decisionUsersFor(\Illuminate\Pagination\LengthAwarePaginator $paginatedDocuments, string $tenantId): \Illuminate\Support\Collection
    {
        $decisionUserIds = $paginatedDocuments->getCollection()
            ->pluck('decision_by_id')
            ->filter()
            ->unique()
            ->values();

        if ($decisionUserIds->isEmpty()) {
            return collect();
        }

        return \App\Models\User::query()->where('tenant_id', $tenantId)->whereIn('id', $decisionUserIds)->pluck('name', 'id');
    }


    /**
     * Remove the specified document.
     */
    public function destroy(string $documentId): RedirectResponse
    {
        try {
            $document = Document::findOrFail($documentId);
            
            // Delete file from storage
            if (Storage::exists($document->file_path)) {
                Storage::delete($document->file_path);
            }
            
            // Delete document record
            $document->delete();
            
            return redirect()
                ->route('documents.index')
                ->with('success', 'Document đã được xóa thành công!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Không thể xóa document: ' . $e->getMessage()]);
        }
    }
}
