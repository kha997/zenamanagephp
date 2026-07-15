<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Src\DocumentManagement\Models\Document;
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
            $request->only(['title', 'project_id', 'document_type', 'description']),
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
                ->where('is_active', true)
                ->whereHas('project', fn ($projectQuery) => $projectQuery->where('tenant_id', $tenantId));

            // Apply filters
            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $documents = $query->orderBy('created_at', 'desc')->paginate(15);
            $projects = Project::query()->where('tenant_id', $tenantId)->select('id', 'name')->get();
            
            return view('documents.approvals', compact('documents', 'projects'));
        } catch (\Exception $e) {
            return view('documents.approvals', [
                'documents' => collect(),
                'projects' => collect(),
                'error' => 'Không thể tải danh sách documents cần duyệt: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Approve a document.
     */
    public function approve(Request $request, string $documentId): RedirectResponse
    {
        $request->validate([
            'approval_note' => 'nullable|string|max:500',
        ]);

        try {
            $document = Document::findOrFail($documentId);
            
            $document->update([
                'status' => 'approved',
                'approved_by' => Auth::id(),
                'approved_at' => now(),
                'approval_note' => $request->input('approval_note'),
            ]);
            
            return redirect()
                ->back()
                ->with('success', 'Document đã được duyệt thành công!');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Không thể duyệt document: ' . $e->getMessage()]);
        }
    }

    /**
     * Reject a document.
     */
    public function reject(Request $request, string $documentId): RedirectResponse
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        try {
            $document = Document::findOrFail($documentId);
            
            $document->update([
                'status' => 'rejected',
                'rejected_by' => Auth::id(),
                'rejected_at' => now(),
                'rejection_reason' => $request->input('rejection_reason'),
            ]);
            
            return redirect()
                ->back()
                ->with('success', 'Document đã bị từ chối.');
        } catch (\Exception $e) {
            return redirect()
                ->back()
                ->withErrors(['error' => 'Không thể từ chối document: ' . $e->getMessage()]);
        }
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
