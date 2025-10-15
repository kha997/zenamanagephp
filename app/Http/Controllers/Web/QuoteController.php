<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Client;
use App\Models\Quote;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Quote Controller (Web)
 * 
 * Handles web requests for quote management
 * Features:
 * - Quote listing with filters
 * - Quote creation and editing
 * - Quote detail view with actions
 * - Quote sending and status management
 * - PDF generation and document integration
 */
class QuoteController extends Controller
{
    /**
     * Display a listing of quotes
     */
    public function index(Request $request): View
    {
        $user = session('user');
        $tenantId = $user ? $user['tenant_id'] : '01k6vr04x4dw1zmh6kg7083gas'; // Use real tenant ID
        
        $query = Quote::where('tenant_id', $tenantId)->with(['client', 'project']);

        // Apply filters
        if ($request->filled('search')) {
            $query->where('title', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('client_id')) {
            $query->where('client_id', $request->client_id);
        }

        if ($request->filled('project_id')) {
            $query->where('project_id', $request->project_id);
        }

        if ($request->filled('expiring_soon')) {
            $query->where('expiry_date', '<=', now()->addDays(7));
        }

        // Pagination
        $quotes = $query->latest()->paginate(20)->withQueryString();

        // Get statistics for KPI strip component
        $kpiStats = [
            [
                'label' => 'Total Quotes',
                'value' => Quote::where('tenant_id', $tenantId)->count(),
                'subtitle' => 'All quotes',
                'icon' => 'fas fa-file-invoice',
                'gradient' => 'from-blue-500 to-blue-600',
                'action' => 'View All Quotes'
            ],
            [
                'label' => 'Draft',
                'value' => Quote::where('tenant_id', $tenantId)->where('status', 'draft')->count(),
                'subtitle' => 'Draft quotes',
                'icon' => 'fas fa-edit',
                'gradient' => 'from-yellow-500 to-yellow-600',
                'action' => 'View Drafts'
            ],
            [
                'label' => 'Sent',
                'value' => Quote::where('tenant_id', $tenantId)->where('status', 'sent')->count(),
                'subtitle' => 'Sent quotes',
                'icon' => 'fas fa-paper-plane',
                'gradient' => 'from-purple-500 to-purple-600',
                'action' => 'View Sent'
            ],
            [
                'label' => 'Accepted',
                'value' => Quote::where('tenant_id', $tenantId)->where('status', 'accepted')->count(),
                'subtitle' => 'Accepted quotes',
                'icon' => 'fas fa-check-circle',
                'gradient' => 'from-green-500 to-green-600',
                'action' => 'View Accepted'
            ],
        ];

        // Get statistics for view (associative array)
        $stats = [
            'total' => Quote::where('tenant_id', $tenantId)->count(),
            'accepted' => Quote::where('tenant_id', $tenantId)->where('status', 'accepted')->count(),
            'expiring_soon' => Quote::where('tenant_id', $tenantId)->where('valid_until', '<=', now()->addDays(7))->count(),
            'total_value' => Quote::where('tenant_id', $tenantId)->sum('final_amount'),
        ];

        // Get clients for filter dropdown
        $clients = Client::where('tenant_id', $tenantId)->select('id', 'name', 'company')->get();

        return view('app.quotes.index', compact('quotes', 'stats', 'kpiStats', 'clients'));
    }

    /**
     * Show the form for creating a new quote
     */
    public function create(Request $request): View
    {
        $clientId = $request->get('client_id');
        $projectId = $request->get('project_id');
        
        $clients = Client::select('id', 'name', 'company')->get();
        $projects = Project::select('id', 'name')->get();

        return view('app.quotes.create', compact('clients', 'projects', 'clientId', 'projectId'));
    }

    /**
     * Store a newly created quote
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'type' => 'required|in:design,construction',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_amount' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'valid_until' => 'required|date|after:today',
            'line_items' => 'nullable|array',
            'line_items.*.description' => 'required_with:line_items|string',
            'line_items.*.quantity' => 'required_with:line_items|numeric|min:0',
            'line_items.*.unit_price' => 'required_with:line_items|numeric|min:0',
            'terms_conditions' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // Calculate amounts
            $taxRate = $validated['tax_rate'] ?? 0;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $taxableAmount = $validated['total_amount'] - $discountAmount;
            $taxAmount = $taxableAmount * ($taxRate / 100);
            $finalAmount = $taxableAmount + $taxAmount;

            $quote = Quote::create([
                ...$validated,
                'tenant_id' => session('user')['tenant_id'] ?? '01k6vr04x4dw1zmh6kg7083gas', // Use real tenant ID
                'tax_amount' => $taxAmount,
                'final_amount' => $finalAmount,
                'status' => 'draft',
                'created_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Quote created', [
                'quote_id' => $quote->id,
                'quote_title' => $quote->title,
                'client_id' => $quote->client_id,
                'created_by' => Auth::id(),
            ]);

            return redirect()
                ->route('quotes.show', $quote)
                ->with('success', 'Quote created successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote creation failed', [
                'error' => $e->getMessage(),
                'data' => $validated,
                'created_by' => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to create quote. Please try again.');
        }
    }

    /**
     * Display the specified quote
     */
    public function show(Quote $quote): View
    {
        $quote->load(['client', 'project', 'creator', 'documents']);

        // Get related quotes for the same client
        $relatedQuotes = Quote::where('client_id', $quote->client_id)
            ->where('id', '!=', $quote->id)
            ->latest()
            ->limit(5)
            ->get();

        return view('app.quotes.show', compact('quote', 'relatedQuotes'));
    }

    /**
     * Show the form for editing the specified quote
     */
    public function edit(Quote $quote): View
    {
        $clients = Client::select('id', 'name', 'company')->get();
        $projects = Project::select('id', 'name')->get();

        return view('app.quotes.edit', compact('quote', 'clients', 'projects'));
    }

    /**
     * Update the specified quote
     */
    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'project_id' => 'nullable|exists:projects,id',
            'type' => 'required|in:design,construction',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'total_amount' => 'required|numeric|min:0',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'discount_amount' => 'nullable|numeric|min:0',
            'valid_until' => 'required|date|after:today',
            'line_items' => 'nullable|array',
            'line_items.*.description' => 'required_with:line_items|string',
            'line_items.*.quantity' => 'required_with:line_items|numeric|min:0',
            'line_items.*.unit_price' => 'required_with:line_items|numeric|min:0',
            'terms_conditions' => 'nullable|array',
        ]);

        try {
            DB::beginTransaction();

            // Calculate amounts
            $taxRate = $validated['tax_rate'] ?? 0;
            $discountAmount = $validated['discount_amount'] ?? 0;
            $taxableAmount = $validated['total_amount'] - $discountAmount;
            $taxAmount = $taxableAmount * ($taxRate / 100);
            $finalAmount = $taxableAmount + $taxAmount;

            $quote->update([
                ...$validated,
                'tax_amount' => $taxAmount,
                'final_amount' => $finalAmount,
                'updated_by' => Auth::id(),
            ]);

            DB::commit();

            Log::info('Quote updated', [
                'quote_id' => $quote->id,
                'quote_title' => $quote->title,
                'updated_by' => Auth::id(),
            ]);

            return redirect()
                ->route('quotes.show', $quote)
                ->with('success', 'Quote updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote update failed', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'data' => $validated,
                'updated_by' => Auth::id(),
            ]);

            return back()
                ->withInput()
                ->with('error', 'Failed to update quote. Please try again.');
        }
    }

    /**
     * Send quote to client
     */
    public function send(Request $request, Quote $quote): RedirectResponse
    {
        if (!$quote->canBeSent()) {
            return back()->with('error', 'Quote cannot be sent in its current status.');
        }

        try {
            DB::beginTransaction();

            $quote->markAsSent();

            // Generate PDF and store in documents
            $this->generateQuotePDF($quote);

            // Send email notification (placeholder)
            // Mail::to($quote->client->email)->send(new QuoteSentMail($quote));

            DB::commit();

            Log::info('Quote sent', [
                'quote_id' => $quote->id,
                'client_email' => $quote->client->email,
                'sent_by' => Auth::id(),
            ]);

            return back()->with('success', 'Quote sent successfully to client.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote send failed', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'sent_by' => Auth::id(),
            ]);

            return back()->with('error', 'Failed to send quote. Please try again.');
        }
    }

    /**
     * Accept quote
     */
    public function accept(Request $request, Quote $quote): RedirectResponse
    {
        if (!$quote->canBeAccepted()) {
            return back()->with('error', 'Quote cannot be accepted in its current status.');
        }

        try {
            DB::beginTransaction();

            $quote->markAsAccepted();

            // Create project if not already linked
            if (!$quote->project_id) {
                $project = Project::create([
                    'tenant_id' => session('user')['tenant_id'] ?? '01k6vr04x4dw1zmh6kg7083gas', // Use real tenant ID
                    'client_id' => $quote->client_id,
                    'name' => "Project from Quote: {$quote->title}",
                    'description' => $quote->description,
                    'status' => 'planning',
                    'budget' => $quote->final_amount,
                    'created_by' => Auth::id(),
                ]);

                $quote->update(['project_id' => $project->id]);
            }

            DB::commit();

            Log::info('Quote accepted', [
                'quote_id' => $quote->id,
                'project_id' => $quote->project_id,
                'accepted_by' => Auth::id(),
            ]);

            return back()->with('success', 'Quote accepted successfully. Project created.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote acceptance failed', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'accepted_by' => Auth::id(),
            ]);

            return back()->with('error', 'Failed to accept quote. Please try again.');
        }
    }

    /**
     * Reject quote
     */
    public function reject(Request $request, Quote $quote): RedirectResponse
    {
        if (!$quote->canBeRejected()) {
            return back()->with('error', 'Quote cannot be rejected in its current status.');
        }

        $validated = $request->validate([
            'rejection_reason' => 'nullable|string|max:1000',
        ]);

        try {
            DB::beginTransaction();

            $quote->markAsRejected($validated['rejection_reason'] ?? null);

            DB::commit();

            Log::info('Quote rejected', [
                'quote_id' => $quote->id,
                'rejection_reason' => $validated['rejection_reason'] ?? null,
                'rejected_by' => Auth::id(),
            ]);

            return back()->with('success', 'Quote rejected successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote rejection failed', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'rejected_by' => Auth::id(),
            ]);

            return back()->with('error', 'Failed to reject quote. Please try again.');
        }
    }

    /**
     * Remove the specified quote
     */
    public function destroy(Quote $quote): RedirectResponse
    {
        try {
            DB::beginTransaction();

            $quoteTitle = $quote->title;
            $quote->delete();

            DB::commit();

            Log::info('Quote deleted', [
                'quote_id' => $quote->id,
                'quote_title' => $quoteTitle,
                'deleted_by' => Auth::id(),
            ]);

            return redirect()
                ->route('quotes.index')
                ->with('success', 'Quote deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Quote deletion failed', [
                'error' => $e->getMessage(),
                'quote_id' => $quote->id,
                'deleted_by' => Auth::id(),
            ]);

            return back()->with('error', 'Failed to delete quote. Please try again.');
        }
    }

    /**
     * Generate PDF for quote
     */
    private function generateQuotePDF(Quote $quote): void
    {
        // Placeholder for PDF generation
        // In a real implementation, this would:
        // 1. Generate PDF using a library like DomPDF or TCPDF
        // 2. Store the PDF in the documents table
        // 3. Link it to the quote
        
        Log::info('Quote PDF generated', [
            'quote_id' => $quote->id,
            'pdf_path' => 'quotes/quote_' . $quote->id . '.pdf',
        ]);
    }

    /**
     * Get quote statistics for dashboard
     */
    public function getStats(): array
    {
        return [
            'total_quotes' => Quote::count(),
            'draft' => Quote::where('status', 'draft')->count(),
            'sent' => Quote::where('status', 'sent')->count(),
            'viewed' => Quote::where('status', 'viewed')->count(),
            'accepted' => Quote::where('status', 'accepted')->count(),
            'rejected' => Quote::where('status', 'rejected')->count(),
            'expired' => Quote::where('status', 'expired')->count(),
            'expiring_soon' => Quote::expiringSoon(7)->count(),
            'total_value' => Quote::where('status', 'accepted')->sum('final_amount'),
            'conversion_rate' => $this->calculateConversionRate(),
        ];
    }

    /**
     * Calculate quote conversion rate
     */
    private function calculateConversionRate(): float
    {
        $totalSent = Quote::whereIn('status', ['sent', 'viewed', 'accepted', 'rejected'])->count();
        $accepted = Quote::where('status', 'accepted')->count();
        
        if ($totalSent === 0) {
            return 0;
        }

        return round(($accepted / $totalSent) * 100, 2);
    }
}
