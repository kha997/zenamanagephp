<?php

namespace App\Http\Controllers;
use Illuminate\Support\Facades\Auth;


use App\Models\SupportDocumentation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class SupportDocumentationController extends Controller
{
    /**
     * Display a listing of support documentation
     */
    public function index(Request $request)
    {
        $query = SupportDocumentation::query();

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) 
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $documents = $query->paginate(20);

        $categories = [
            'getting_started' => 'Getting Started',
            'user_guide' => 'User Guide',
            'troubleshooting' => 'Troubleshooting',
            'faq' => 'Frequently Asked Questions',
            'api_documentation' => 'API Documentation',
            'admin_guide' => 'Admin Guide',
            'maintenance' => 'Maintenance',
            'security' => 'Security'
        ];

        return view('admin.support.documentation.index', compact('documents', 'categories'));
    }

    /**
     * Show the form for creating a new documentation
     */
    public function create()
    {
        $categories = [
            'getting_started' => 'Getting Started',
            'user_guide' => 'User Guide',
            'troubleshooting' => 'Troubleshooting',
            'faq' => 'Frequently Asked Questions',
            'api_documentation' => 'API Documentation',
            'admin_guide' => 'Admin Guide',
            'maintenance' => 'Maintenance',
            'security' => 'Security'
        ];

        $statuses = [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived'
        ];

        return view('admin.support.documentation.create', compact('categories', 'statuses'));
    }

    /**
     * Store a newly created documentation
     */
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string',
            'status' => 'required|in:draft,published,archived',
            'tags' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240' // 10MB max per file
        ]);

        $document = SupportDocumentation::create([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'content' => $request->content,
            'category' => $request->category,
            'status' => $request->status,
            'tags' => $request->tags,
            'author_id' => Auth::id(),
            'published_at' => $request->status === 'published' ? now() : null
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('support-docs/' . $document->id, 'public');
                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ];
            }
            $document->update(['attachments' => $attachments]);
        }

        return redirect()->route('support.documentation.show', $document)
            ->with('success', 'Documentation created successfully');
    }

    /**
     * Display the specified documentation
     */
    public function show(SupportDocumentation $document)
    {
        $document->load(['author', 'versions']);

        // Increment view count
        $document->increment('view_count');

        return view('admin.support.documentation.show', compact('document'));
    }

    /**
     * Show the form for editing the specified documentation
     */
    public function edit(SupportDocumentation $document)
    {
        $categories = [
            'getting_started' => 'Getting Started',
            'user_guide' => 'User Guide',
            'troubleshooting' => 'Troubleshooting',
            'faq' => 'Frequently Asked Questions',
            'api_documentation' => 'API Documentation',
            'admin_guide' => 'Admin Guide',
            'maintenance' => 'Maintenance',
            'security' => 'Security'
        ];

        $statuses = [
            'draft' => 'Draft',
            'published' => 'Published',
            'archived' => 'Archived'
        ];

        return view('admin.support.documentation.edit', compact('document', 'categories', 'statuses'));
    }

    /**
     * Update the specified documentation
     */
    public function update(Request $request, SupportDocumentation $document)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'category' => 'required|string',
            'status' => 'required|in:draft,published,archived',
            'tags' => 'nullable|string',
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:10240'
        ]);

        // Create version before updating
        $this->createVersion($document);

        $document->update([
            'title' => $request->title,
            'slug' => Str::slug($request->title),
            'content' => $request->content,
            'category' => $request->category,
            'status' => $request->status,
            'tags' => $request->tags,
            'updated_by' => Auth::id(),
            'published_at' => $request->status === 'published' && $document->status !== 'published' ? now() : $document->published_at
        ]);

        // Handle new attachments
        if ($request->hasFile('attachments')) {
            $existingAttachments = $document->attachments ?? [];
            $newAttachments = [];
            
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('support-docs/' . $document->id, 'public');
                $newAttachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ];
            }
            
            $document->update(['attachments' => array_merge($existingAttachments, $newAttachments)]);
        }

        return redirect()->route('support.documentation.show', $document)
            ->with('success', 'Documentation updated successfully');
    }

    /**
     * Delete the specified documentation
     */
    public function destroy(SupportDocumentation $document)
    {
        // Delete attachments
        if ($document->attachments) {
            foreach ($document->attachments as $attachment) {
                Storage::disk('public')->delete($attachment['path']);
            }
        }

        $document->delete();

        return redirect()->route('support.documentation.index')
            ->with('success', 'Documentation deleted successfully');
    }

    /**
     * Create version of documentation
     */
    private function createVersion(SupportDocumentation $document)
    {
        $document->versions()->create([
            'title' => $document->title,
            'content' => $document->content,
            'version_number' => $document->versions()->count() + 1,
            'created_by' => Auth::id()
        ]);
    }

    /**
     * Get documentation by category for public access
     */
    public function getByCategory($category)
    {
        $documents = SupportDocumentation::where('category', $category)
            ->where('status', 'published')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($documents);
    }

    /**
     * Search documentation
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        $category = $request->get('category');

        $documents = SupportDocumentation::where('status', 'published');

        if ($category) {
            $documents->where('category', $category);
        }

        if ($query) {
            $documents->where(function($q) 
            });
        }

        $results = $documents->orderBy('created_at', 'desc')->paginate(10);

        return view('admin.support.documentation.search', compact('results', 'query', 'category'));
    }

    /**
     * Get documentation statistics
     */
    public function statistics()
    {
        $stats = [
            'total_documents' => SupportDocumentation::count(),
            'published_documents' => SupportDocumentation::where('status', 'published')->count(),
            'draft_documents' => SupportDocumentation::where('status', 'draft')->count(),
            'archived_documents' => SupportDocumentation::where('status', 'archived')->count(),
            'total_views' => SupportDocumentation::sum('view_count'),
            'documents_by_category' => SupportDocumentation::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->get(),
            'recent_documents' => SupportDocumentation::orderBy('created_at', 'desc')
                ->limit(10)
                ->get(),
            'most_viewed' => SupportDocumentation::orderBy('view_count', 'desc')
                ->limit(10)
                ->get()
        ];

        return view('admin.support.documentation.statistics', compact('stats'));
    }

    /**
     * Export documentation
     */
    public function export(Request $request)
    {
        $format = $request->get('format', 'json');
        $category = $request->get('category');

        $query = SupportDocumentation::where('status', 'published');

        if ($category) {
            $query->where('category', $category);
        }

        $documents = $query->get();

        switch ($format) {
            case 'json':
                return response()->json($documents);
            case 'csv':
                return $this->exportToCsv($documents);
            case 'pdf':
                return $this->exportToPdf($documents);
            default:
                return response()->json($documents);
        }
    }

    /**
     * Export to CSV
     */
    private function exportToCsv($documents)
    {
        $filename = 'documentation_' . date('Y-m-d_H-i-s') . '.csv';
        
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ];

        $callback = function() 
            
            // Headers
            fputcsv($file, ['Title', 'Category', 'Status', 'Views', 'Created At', 'Updated At']);

            // Data
            foreach ($documents as $document) {
                fputcsv($file, [
                    $document->title,
                    $document->category,
                    $document->status,
                    $document->view_count,
                    $document->created_at->format('Y-m-d H:i:s'),
                    $document->updated_at->format('Y-m-d H:i:s')
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Export to PDF
     */
    private function exportToPdf($documents)
    {
        // This would require a PDF library like dompdf or tcpdf
        // For now, return a simple HTML representation
        $html = '<h1>Documentation Export</h1>';
        
        foreach ($documents as $document) {
            $html .= '<h2>' . $document->title . '</h2>';
            $html .= '<p><strong>Category:</strong> ' . $document->category . '</p>';
            $html .= '<p><strong>Status:</strong> ' . $document->status . '</p>';
            $html .= '<div>' . $document->content . '</div>';
            $html .= '<hr>';
        }

        return response($html)->header('Content-Type', 'text/html');
    }
}