<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DocumentsController extends Controller
{
    /**
     * Display the documents page
     */
    public function index(Request $request): View
    {
        $tenant = app('tenant');
        
        // Get KPI data
        $kpis = [
            'total_documents' => 15, // TODO: Calculate from actual documents
            'pdf_files' => 8, // TODO: Calculate from actual documents
            'image_files' => 5, // TODO: Calculate from actual documents
            'total_size' => '125 MB', // TODO: Calculate from actual documents
        ];
        
        // Get documents for the current tenant
        $documents = collect([
            [
                'id' => 'doc_001',
                'name' => 'Project Proposal.pdf',
                'type' => 'pdf',
                'size' => '2.5 MB',
                'status' => 'active',
                'created_at' => now()->subDays(2),
                'updated_at' => now()->subDays(1),
            ],
            [
                'id' => 'doc_002',
                'name' => 'Design Mockup.png',
                'type' => 'png',
                'size' => '1.2 MB',
                'status' => 'active',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(3),
            ],
            [
                'id' => 'doc_003',
                'name' => 'Contract Template.docx',
                'type' => 'docx',
                'size' => '850 KB',
                'status' => 'draft',
                'created_at' => now()->subDays(7),
                'updated_at' => now()->subDays(6),
            ],
            [
                'id' => 'doc_004',
                'name' => 'Meeting Notes.pdf',
                'type' => 'pdf',
                'size' => '1.8 MB',
                'status' => 'active',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(8),
            ],
            [
                'id' => 'doc_005',
                'name' => 'Budget Spreadsheet.xlsx',
                'type' => 'xlsx',
                'size' => '650 KB',
                'status' => 'active',
                'created_at' => now()->subDays(12),
                'updated_at' => now()->subDays(10),
            ],
        ]);
        
        $totalSize = '6.0 MB'; // TODO: Calculate from actual documents
        
        return view('app.documents.index', compact('kpis', 'documents', 'totalSize'));
    }
    
    /**
     * Upload a new document
     */
    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB max
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);
        
        // TODO: Implement file upload logic
        
        return redirect()->route('documents.index')
            ->with('success', 'Document uploaded successfully');
    }
    
    /**
     * Download a document
     */
    public function download(Request $request, string $id)
    {
        // TODO: Implement document download logic
        
        return response()->download('path/to/document.pdf');
    }
    
    /**
     * Delete a document
     */
    public function destroy(Request $request, string $id)
    {
        // TODO: Implement document deletion logic
        
        return redirect()->route('documents.index')
            ->with('success', 'Document deleted successfully');
    }
}
