<?php declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TemplatesController extends Controller
{
    /**
     * Display the templates page
     */
    public function index(Request $request): View
    {
        $tenant = app('tenant');
        
        // Get KPI data
        $kpis = [
            'total_templates' => 8, // TODO: Calculate from actual templates
            'active_templates' => 6, // TODO: Calculate from actual templates
            'templates_used' => 15, // TODO: Calculate from actual usage
            'last_used' => '2 days ago', // TODO: Calculate from actual usage
        ];
        
        // Get templates for the current tenant
        $templates = collect([
            [
                'id' => 'template_001',
                'name' => 'Residential Construction',
                'description' => 'Template for residential construction projects',
                'category' => 'project',
                'status' => 'active',
                'created_at' => now()->subDays(5),
                'updated_at' => now()->subDays(2),
            ],
            [
                'id' => 'template_002',
                'name' => 'Commercial Building',
                'description' => 'Template for commercial building projects',
                'category' => 'project',
                'status' => 'active',
                'created_at' => now()->subDays(10),
                'updated_at' => now()->subDays(7),
            ],
            [
                'id' => 'template_003',
                'name' => 'Task Checklist',
                'description' => 'Standard task checklist template',
                'category' => 'task',
                'status' => 'active',
                'created_at' => now()->subDays(15),
                'updated_at' => now()->subDays(12),
            ],
            [
                'id' => 'template_004',
                'name' => 'Project Proposal',
                'description' => 'Template for project proposals',
                'category' => 'document',
                'status' => 'draft',
                'created_at' => now()->subDays(20),
                'updated_at' => now()->subDays(18),
            ],
            [
                'id' => 'template_005',
                'name' => 'Site Inspection',
                'description' => 'Template for site inspection reports',
                'category' => 'document',
                'status' => 'active',
                'created_at' => now()->subDays(25),
                'updated_at' => now()->subDays(22),
            ],
        ]);
        
        $templatesUsed = 15; // TODO: Calculate from actual usage
        $lastUsed = '2 days ago'; // TODO: Calculate from actual usage
        
        return view('app.templates.index', compact('kpis', 'templates', 'templatesUsed', 'lastUsed'));
    }
    
    /**
     * Show template builder
     */
    public function builder(Request $request): View
    {
        return view('app.templates.builder');
    }
    
    /**
     * Show template library
     */
    public function library(Request $request): View
    {
        return view('app.templates.library');
    }
    
    /**
     * Show template details
     */
    public function show(Request $request, string $id): View
    {
        // TODO: Get template from database
        $template = [
            'id' => $id,
            'name' => 'Sample Template',
            'description' => 'Sample template description',
        ];
        
        return view('app.templates.show', compact('template'));
    }
    
    /**
     * Create a new template
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:project,task,document',
            'content' => 'required|string',
        ]);
        
        // TODO: Implement template creation logic
        
        return redirect()->route('templates.index')
            ->with('success', 'Template created successfully');
    }
    
    /**
     * Update a template
     */
    public function update(Request $request, string $id)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category' => 'required|in:project,task,document',
            'content' => 'required|string',
        ]);
        
        // TODO: Implement template update logic
        
        return redirect()->route('templates.index')
            ->with('success', 'Template updated successfully');
    }
    
    /**
     * Delete a template
     */
    public function destroy(Request $request, string $id)
    {
        // TODO: Implement template deletion logic
        
        return redirect()->route('templates.index')
            ->with('success', 'Template deleted successfully');
    }
}
