<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Template;
use App\Models\Project;
use App\Services\TemplateService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * TemplateController - Web Template Management
 * 
 * Handles web-based template operations with proper authentication and tenant isolation
 */
class TemplateController extends Controller
{
    protected TemplateService $templateService;

    public function __construct(TemplateService $templateService)
    {
        $this->templateService = $templateService;
        $this->middleware('auth');
    }

    /**
     * Display templates index page
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Get filters from request
        $filters = [
            'category' => $request->input('category'),
            'status' => $request->input('status'),
            'is_public' => $request->input('is_public'),
            'search' => $request->input('search'),
            'tags' => $request->input('tags')
        ];

        // Remove null filters
        $filters = array_filter($filters, fn($value) => $value !== null);

        $templates = $this->templateService->searchTemplates($tenantId, $filters);
        $analytics = $this->templateService->getTemplateAnalytics($tenantId);

        return view('app.templates.index', compact('templates', 'filters', 'analytics'));
    }

    /**
     * Show template creation form
     */
    public function create(): View
    {
        $categories = Template::VALID_CATEGORIES;
        $statuses = Template::VALID_STATUSES;

        return view('app.templates.create', compact('categories', 'statuses'));
    }

    /**
     * Show template builder interface
     */
    public function builder(Request $request): View
    {
        $templateId = $request->input('template_id');
        $template = null;

        if ($templateId) {
            $user = Auth::user();
            $template = Template::byTenant($user->tenant_id)->find($templateId);
        }

        $categories = Template::VALID_CATEGORIES;
        $statuses = Template::VALID_STATUSES;

        return view('app.templates.builder', compact('template', 'categories', 'statuses'));
    }

    /**
     * Show template library
     */
    public function library(Request $request): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        // Get public templates and user's templates
        $publicTemplates = Template::byTenant($tenantId)
            ->public()
            ->published()
            ->active()
            ->orderBy('usage_count', 'desc')
            ->get();

        $userTemplates = Template::byTenant($tenantId)
            ->byUser($user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        $categories = Template::VALID_CATEGORIES;

        return view('app.templates.library', compact('publicTemplates', 'userTemplates', 'categories'));
    }

    /**
     * Show template analytics
     */
    public function analytics(): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $analytics = $this->templateService->getTemplateAnalytics($tenantId);

        return view('app.templates.analytics', compact('analytics'));
    }

    /**
     * Display a specific template
     */
    public function show(string $templateId): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $template = Template::byTenant($tenantId)
            ->with(['creator', 'updater', 'versions'])
            ->findOrFail($templateId);

        // Get projects using this template
        $projects = Project::byTenant($tenantId)
            ->where('template_id', $templateId)
            ->with(['owner'])
            ->orderBy('created_at', 'desc')
            ->get();

        return view('app.templates.show', compact('template', 'projects'));
    }

    /**
     * Show template edit form
     */
    public function edit(string $templateId): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $template = Template::byTenant($tenantId)->findOrFail($templateId);
        $categories = Template::VALID_CATEGORIES;
        $statuses = Template::VALID_STATUSES;

        return view('app.templates.edit', compact('template', 'categories', 'statuses'));
    }

    /**
     * Show template duplication form
     */
    public function duplicate(string $templateId): View
    {
        $user = Auth::user();
        $tenantId = $user->tenant_id;

        $template = Template::byTenant($tenantId)->findOrFail($templateId);

        return view('app.templates.duplicate', compact('template'));
    }
}
