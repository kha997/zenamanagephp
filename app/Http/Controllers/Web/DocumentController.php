<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
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
        // Enhanced mock data with more file types and categories
        $mockDocuments = collect([
            [
                'id' => '1',
                'name' => 'Project Requirements Document',
                'description' => 'Detailed requirements for the website project',
                'file_type' => 'pdf',
                'file_size' => '2.5 MB',
                'file_size_bytes' => 2621440,
                'status' => 'approved',
                'category' => 'requirements',
                'project_name' => 'Dự án Website Công ty',
                'uploaded_by' => 'Admin',
                'uploaded_at' => '2024-01-15T08:00:00Z',
                'approved_at' => '2024-01-16T10:30:00Z',
                'download_count' => 15,
                'version' => '1.2',
                'tags' => ['requirements', 'website', 'planning'],
                'mime_type' => 'application/pdf',
                'file_path' => '/documents/project-requirements-v1.2.pdf'
            ],
            [
                'id' => '2',
                'name' => 'Technical Specifications',
                'description' => 'Technical specifications for HR management system',
                'file_type' => 'docx',
                'file_size' => '1.8 MB',
                'file_size_bytes' => 1887436,
                'status' => 'pending',
                'category' => 'technical',
                'project_name' => 'Hệ thống Quản lý Nhân sự',
                'uploaded_by' => 'Project Manager',
                'uploaded_at' => '2024-01-20T14:15:00Z',
                'approved_at' => null,
                'download_count' => 8,
                'version' => '2.0',
                'tags' => ['technical', 'specifications', 'hr'],
                'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'file_path' => '/documents/technical-specs-v2.0.docx'
            ],
            [
                'id' => '3',
                'name' => 'Mobile App Wireframes',
                'description' => 'UI/UX wireframes for mobile application',
                'file_type' => 'figma',
                'file_size' => '5.2 MB',
                'file_size_bytes' => 5452595,
                'status' => 'approved',
                'category' => 'design',
                'project_name' => 'Ứng dụng Mobile',
                'uploaded_by' => 'Designer',
                'uploaded_at' => '2024-01-10T11:20:00Z',
                'approved_at' => '2024-01-12T09:45:00Z',
                'download_count' => 23,
                'version' => '1.0',
                'tags' => ['design', 'wireframes', 'mobile', 'ui'],
                'mime_type' => 'application/octet-stream',
                'file_path' => '/documents/mobile-wireframes-v1.0.figma'
            ],
            [
                'id' => '4',
                'name' => 'API Integration Guide',
                'description' => 'Documentation for third-party API integration',
                'file_type' => 'md',
                'file_size' => '850 KB',
                'file_size_bytes' => 870400,
                'status' => 'rejected',
                'category' => 'documentation',
                'project_name' => 'Tích hợp API Bên thứ 3',
                'uploaded_by' => 'Developer',
                'uploaded_at' => '2024-01-18T16:30:00Z',
                'approved_at' => null,
                'download_count' => 3,
                'version' => '1.1',
                'tags' => ['api', 'documentation', 'integration'],
                'mime_type' => 'text/markdown',
                'file_path' => '/documents/api-integration-guide-v1.1.md'
            ],
            [
                'id' => '5',
                'name' => 'Security Audit Report',
                'description' => 'Comprehensive security audit report',
                'file_type' => 'pdf',
                'file_size' => '3.1 MB',
                'file_size_bytes' => 3250585,
                'status' => 'approved',
                'category' => 'security',
                'project_name' => 'Cải tiến Hệ thống Bảo mật',
                'uploaded_by' => 'Security Expert',
                'uploaded_at' => '2024-01-22T13:45:00Z',
                'approved_at' => '2024-01-23T08:15:00Z',
                'download_count' => 12,
                'version' => '1.0',
                'tags' => ['security', 'audit', 'report'],
                'mime_type' => 'application/pdf',
                'file_path' => '/documents/security-audit-report-v1.0.pdf'
            ],
            [
                'id' => '6',
                'name' => 'Database Schema Design',
                'description' => 'Database schema and relationships design',
                'file_type' => 'sql',
                'file_size' => '450 KB',
                'file_size_bytes' => 460800,
                'status' => 'approved',
                'category' => 'database',
                'project_name' => 'Hệ thống Quản lý Nhân sự',
                'uploaded_by' => 'Database Admin',
                'uploaded_at' => '2024-01-25T09:30:00Z',
                'approved_at' => '2024-01-26T14:20:00Z',
                'download_count' => 7,
                'version' => '2.1',
                'tags' => ['database', 'schema', 'sql'],
                'mime_type' => 'application/sql',
                'file_path' => '/documents/database-schema-v2.1.sql'
            ],
            [
                'id' => '7',
                'name' => 'User Interface Mockups',
                'description' => 'High-fidelity UI mockups for web application',
                'file_type' => 'psd',
                'file_size' => '8.7 MB',
                'file_size_bytes' => 9122611,
                'status' => 'pending',
                'category' => 'design',
                'project_name' => 'Dự án Website Công ty',
                'uploaded_by' => 'UI Designer',
                'uploaded_at' => '2024-01-28T16:45:00Z',
                'approved_at' => null,
                'download_count' => 4,
                'version' => '1.3',
                'tags' => ['ui', 'mockups', 'design', 'photoshop'],
                'mime_type' => 'image/vnd.adobe.photoshop',
                'file_path' => '/documents/ui-mockups-v1.3.psd'
            ],
            [
                'id' => '8',
                'name' => 'Test Cases Documentation',
                'description' => 'Comprehensive test cases for quality assurance',
                'file_type' => 'xlsx',
                'file_size' => '1.2 MB',
                'file_size_bytes' => 1258291,
                'status' => 'approved',
                'category' => 'testing',
                'project_name' => 'Ứng dụng Mobile',
                'uploaded_by' => 'QA Engineer',
                'uploaded_at' => '2024-01-30T11:15:00Z',
                'approved_at' => '2024-01-31T09:30:00Z',
                'download_count' => 9,
                'version' => '1.0',
                'tags' => ['testing', 'test-cases', 'qa'],
                'mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'file_path' => '/documents/test-cases-v1.0.xlsx'
            ]
        ]);

        // File categories for filtering
        $fileCategories = [
            'all' => 'All Categories',
            'requirements' => 'Requirements',
            'technical' => 'Technical',
            'design' => 'Design',
            'documentation' => 'Documentation',
            'security' => 'Security',
            'database' => 'Database',
            'testing' => 'Testing'
        ];

        // File types for filtering
        $fileTypes = [
            'all' => 'All Types',
            'pdf' => 'PDF Documents',
            'docx' => 'Word Documents',
            'xlsx' => 'Excel Spreadsheets',
            'figma' => 'Figma Files',
            'psd' => 'Photoshop Files',
            'md' => 'Markdown Files',
            'sql' => 'SQL Files'
        ];

        return view('documents.index', compact('mockDocuments', 'fileCategories', 'fileTypes'));
    }

    /**
     * Show the form for creating a new document.
     */
    public function create(Request $request): View
    {
        try {
            $projects = Project::select('id', 'name')->get();
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
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'project_id' => 'nullable|exists:projects,id',
            'description' => 'nullable|string|max:1000',
        ]);

        try {
            $file = $request->file('file');
            $projectId = $request->input('project_id');
            $description = $request->input('description');
            
            $metadata = [
                'description' => $description,
                'uploaded_at' => now()->toISOString(),
            ];
            
            if ($projectId) {
                $metadata['project_id'] = $projectId;
            }
            
            $result = $this->uploadService->uploadFile(
                $file,
                Auth::id(),
                'default', // tenant_id
                $metadata
            );
            
            if ($result['success']) {
                return redirect()
                    ->route('documents.index')
                    ->with('success', 'Document đã được upload thành công!');
            } else {
                return redirect()
                    ->back()
                    ->withInput()
                    ->withErrors(['error' => $result['message']]);
            }
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
            $document = Document::with(['project', 'uploadedBy'])->findOrFail($documentId);
            
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
            $query = Document::with(['project', 'uploadedBy'])
                ->where('is_active', true);
            
            // Apply filters
            if ($request->filled('project_id')) {
                $query->where('project_id', $request->input('project_id'));
            }
            
            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }
            
            $documents = $query->orderBy('created_at', 'desc')->paginate(15);
            $projects = Project::select('id', 'name')->get();
            
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