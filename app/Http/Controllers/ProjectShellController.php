<?php declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProjectRequest;
use App\Http\Requests\UpdateProjectRequest;
use App\Models\Project;
use App\Models\ProjectTemplate;
use App\Models\Task;
use App\Services\ProjectService;
use App\Services\ProjectAnalyticsService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

/**
 * Unified ProjectShellController
 * 
 * Consolidates functionality from:
 * - Api/ProjectsController.php
 * - Web/ProjectController.php
 * - Web/OptimizedProjectController.php
 * - Api_backup/App/ProjectController.php
 * - Api_backup/App/ProjectsController.php
 * - Api_backup/App/ProjectsAnalyticsController.php
 * - Api_backup/App/ProjectsRealtimeController.php
 * - Api_backup/App/ProjectsIntegrationsController.php
 * - Api_backup/App/ProjectsAutomationController.php
 * - Api_backup/App/ProjectsSeriesController.php
 * - Api_backup/App/ProjectsOverviewController.php
 * - Api_backup/ProjectManagerController.php
 * - Api_backup/ProjectController.php
 * - Api_backup/ProjectTemplateController.php
 * - Api_backup/ProjectAnalyticsController.php
 * - Api_backup/ProjectMilestoneController.php
 * - ProjectTaskController.php
 * - Web/ProjectBulkController.php
 * - ProjectTemplateController.php
 */
class ProjectShellController extends Controller
{
    public function __construct(
        private ProjectService $projectService,
        private ProjectAnalyticsService $analyticsService
    ) {
        $this->middleware('auth');
    }

    /**
     * Display a listing of projects based on context
     */
    public function index(Request $request): View|JsonResponse
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole('super_admin');
        $isApiRequest = $request->expectsJson();
        
        // Determine context and permissions
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        if (!$permissions['can_view']) {
            if ($isApiRequest) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            // Get projects based on context
            $projects = $this->getProjectsForContext($user, $context, $request);
            
            if ($isApiRequest) {
                return ApiResponse::success($projects, 'Projects retrieved successfully');
            }
            
            // Return view for web requests
            return $this->getViewForContext($context, compact('projects', 'permissions'));
            
        } catch (\Exception $e) {
            if ($isApiRequest) {
                return ApiResponse::error('Failed to retrieve projects', 500, null, 'PROJECTS_FETCH_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to retrieve projects');
        }
    }

    /**
     * Show the form for creating a new project
     */
    public function create(Request $request): View
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        if (!$permissions['can_create']) {
            abort(403, 'Insufficient permissions');
        }

        $templates = $this->getAvailableTemplates($user, $context);
        $users = $this->getAvailableUsers($user, $context);
        
        return $this->getCreateViewForContext($context, compact('templates', 'users', 'permissions'));
    }

    /**
     * Store a newly created project
     */
    public function store(StoreProjectRequest $request): JsonResponse|Response
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        if (!$permissions['can_create']) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $projectData = $this->prepareProjectData($request, $context);
            $newProject = $this->projectService->createProject($projectData, $user);
            
            if ($request->expectsJson()) {
                return ApiResponse::created($newProject, 'Project created successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'Project created successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to create project', 500, null, 'PROJECT_CREATE_ERROR');
            }
            
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create project: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified project
     */
    public function show(Request $request, Project $project): View|JsonResponse
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($currentUser, $context);
        
        // Check if user can view this specific project
        if (!$this->canViewProject($currentUser, $project, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $projectData = $this->enrichProjectData($project, $context);
            
            if ($request->expectsJson()) {
                return ApiResponse::success($projectData, 'Project retrieved successfully');
            }
            
            return $this->getShowViewForContext($context, compact('project', 'projectData', 'permissions'));
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to retrieve project', 500, null, 'PROJECT_FETCH_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to retrieve project');
        }
    }

    /**
     * Show the form for editing the specified project
     */
    public function edit(Request $request, Project $project): View
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($currentUser, $context);
        
        if (!$this->canEditProject($currentUser, $project, $context)) {
            abort(403, 'Insufficient permissions');
        }

        $templates = $this->getAvailableTemplates($currentUser, $context);
        $users = $this->getAvailableUsers($currentUser, $context);
        
        return $this->getEditViewForContext($context, compact('project', 'templates', 'users', 'permissions'));
    }

    /**
     * Update the specified project
     */
    public function update(UpdateProjectRequest $request, Project $project): JsonResponse|Response
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($currentUser, $context);
        
        if (!$this->canEditProject($currentUser, $project, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $projectData = $this->prepareProjectData($request, $context, $project);
            $updatedProject = $this->projectService->updateProject($project, $projectData, $currentUser);
            
            if ($request->expectsJson()) {
                return ApiResponse::success($updatedProject, 'Project updated successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'Project updated successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to update project', 500, null, 'PROJECT_UPDATE_ERROR');
            }
            
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to update project: ' . $e->getMessage());
        }
    }

    /**
     * Remove the specified project from storage
     */
    public function destroy(Request $request, Project $project): JsonResponse|Response
    {
        $currentUser = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($currentUser, $context);
        
        if (!$this->canDeleteProject($currentUser, $project, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $this->projectService->deleteProject($project, $currentUser);
            
            if ($request->expectsJson()) {
                return ApiResponse::success(null, 'Project deleted successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'Project deleted successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to delete project', 500, null, 'PROJECT_DELETE_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to delete project: ' . $e->getMessage());
        }
    }

    /**
     * Bulk operations on projects
     */
    public function bulkAction(Request $request): JsonResponse|Response
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        $validator = Validator::make($request->all(), [
            'action' => 'required|in:activate,deactivate,delete,archive,change_status,assign_owner',
            'project_ids' => 'required|array|min:1',
            'project_ids.*' => 'exists:projects,id'
        ]);
        
        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return ApiResponse::validationError($validator->errors());
            }
            return redirect()->back()->withErrors($validator);
        }
        
        $action = $request->input('action');
        $projectIds = $request->input('project_ids');
        
        if (!$this->canPerformBulkAction($user, $action, $context)) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions for bulk action', 403);
            }
            abort(403, 'Insufficient permissions for bulk action');
        }

        try {
            $result = $this->projectService->bulkAction($action, $projectIds, $user, $request->all());
            
            if ($request->expectsJson()) {
                return ApiResponse::success($result, 'Bulk action completed successfully');
            }
            
            return redirect()->back()->with('success', 'Bulk action completed successfully');
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to perform bulk action', 500, null, 'BULK_ACTION_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to perform bulk action: ' . $e->getMessage());
        }
    }

    /**
     * Get project analytics and statistics
     */
    public function analytics(Request $request, ?Project $project = null): JsonResponse
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        if (!$permissions['can_view']) {
            return ApiResponse::error('Insufficient permissions', 403);
        }

        try {
            $analytics = $this->analyticsService->getProjectAnalytics($user, $context, $project);
            return ApiResponse::success($analytics, 'Project analytics retrieved successfully');
            
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to retrieve project analytics', 500, null, 'PROJECT_ANALYTICS_ERROR');
        }
    }

    /**
     * Get project templates
     */
    public function templates(Request $request): JsonResponse|View
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        if (!$permissions['can_view']) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $templates = $this->getAvailableTemplates($user, $context);
            
            if ($request->expectsJson()) {
                return ApiResponse::success($templates, 'Project templates retrieved successfully');
            }
            
            return view('projects.templates', compact('templates', 'permissions'));
            
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to retrieve project templates', 500, null, 'TEMPLATES_FETCH_ERROR');
            }
            
            return redirect()->back()->with('error', 'Failed to retrieve project templates');
        }
    }

    /**
     * Create project from template
     */
    public function createFromTemplate(Request $request, ProjectTemplate $template): JsonResponse|Response
    {
        $user = Auth::user();
        $context = $this->determineContext($request);
        $permissions = $this->getProjectPermissions($user, $context);
        
        if (!$permissions['can_create']) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Insufficient permissions', 403);
            }
            abort(403, 'Insufficient permissions');
        }

        try {
            $projectData = $this->prepareProjectDataFromTemplate($request, $template, $context);
            $newProject = $this->projectService->createProjectFromTemplate($template, $projectData, $user);
            
            if ($request->expectsJson()) {
                return ApiResponse::created($newProject, 'Project created from template successfully');
            }
            
            return redirect()->route($this->getIndexRouteForContext($context))
                           ->with('success', 'Project created from template successfully');
                           
        } catch (\Exception $e) {
            if ($request->expectsJson()) {
                return ApiResponse::error('Failed to create project from template', 500, null, 'PROJECT_TEMPLATE_CREATE_ERROR');
            }
            
            return redirect()->back()
                           ->withInput()
                           ->with('error', 'Failed to create project from template: ' . $e->getMessage());
        }
    }

    // Private helper methods

    private function determineContext(Request $request): string
    {
        $route = $request->route();
        $routeName = $route ? $route->getName() : '';
        
        if (str_contains($routeName, 'admin')) {
            return 'admin';
        } elseif (str_contains($routeName, 'app')) {
            return 'app';
        } elseif (str_contains($routeName, 'api')) {
            return 'api';
        }
        
        return 'web';
    }

    private function getProjectPermissions(User $user, string $context): array
    {
        $isSuperAdmin = $user->hasRole('super_admin');
        $isAdmin = $user->hasRole('admin');
        $isPM = $user->hasRole('project_manager');
        
        switch ($context) {
            case 'admin':
                return [
                    'can_view' => $isSuperAdmin,
                    'can_create' => $isSuperAdmin,
                    'can_edit' => $isSuperAdmin,
                    'can_delete' => $isSuperAdmin,
                    'can_bulk_action' => $isSuperAdmin
                ];
            case 'app':
                return [
                    'can_view' => $isAdmin || $isPM || $isSuperAdmin,
                    'can_create' => $isAdmin || $isPM || $isSuperAdmin,
                    'can_edit' => $isAdmin || $isPM || $isSuperAdmin,
                    'can_delete' => $isAdmin || $isSuperAdmin,
                    'can_bulk_action' => $isAdmin || $isPM || $isSuperAdmin
                ];
            default:
                return [
                    'can_view' => true,
                    'can_create' => false,
                    'can_edit' => false,
                    'can_delete' => false,
                    'can_bulk_action' => false
                ];
        }
    }

    private function getProjectsForContext(User $user, string $context, Request $request)
    {
        $query = Project::query();
        
        switch ($context) {
            case 'admin':
                // Super admin can see all projects
                if ($user->hasRole('super_admin')) {
                    $query->with(['tenant', 'owner', 'tasks']);
                } else {
                    $query->where('owner_id', $user->id);
                }
                break;
            case 'app':
                // App context - projects within same tenant
                $query->where('tenant_id', $user->tenant_id)
                      ->with(['owner', 'tasks']);
                break;
            default:
                // Default - only user's projects
                $query->where('owner_id', $user->id);
        }
        
        // Apply filters
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('code', 'like', "%{$search}%");
            });
        }
        
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }
        
        if ($request->has('owner_id')) {
            $query->where('owner_id', $request->input('owner_id'));
        }
        
        if ($request->has('priority')) {
            $query->where('priority', $request->input('priority'));
        }
        
        // Sorting
        $sortBy = $request->input('sort_by', 'updated_at');
        $sortDirection = $request->input('sort_direction', 'desc');
        $query->orderBy($sortBy, $sortDirection);
        
        // Pagination
        $perPage = $request->input('per_page', 15);
        return $query->paginate($perPage);
    }

    private function canViewProject(User $currentUser, Project $project, string $context): bool
    {
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app') {
            return $project->tenant_id === $currentUser->tenant_id;
        }
        
        return $project->owner_id === $currentUser->id || 
               $project->team->contains($currentUser->id);
    }

    private function canEditProject(User $currentUser, Project $project, string $context): bool
    {
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app') {
            return $project->tenant_id === $currentUser->tenant_id && 
                   ($project->owner_id === $currentUser->id || 
                    $currentUser->hasRole('admin') || 
                    $currentUser->hasRole('project_manager'));
        }
        
        return $project->owner_id === $currentUser->id;
    }

    private function canDeleteProject(User $currentUser, Project $project, string $context): bool
    {
        if ($currentUser->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app') {
            return $project->tenant_id === $currentUser->tenant_id && 
                   ($project->owner_id === $currentUser->id || 
                    $currentUser->hasRole('admin'));
        }
        
        return $project->owner_id === $currentUser->id;
    }

    private function canPerformBulkAction(User $user, string $action, string $context): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        
        if ($context === 'app') {
            return $user->hasRole('admin') || 
                   $user->hasRole('project_manager') && 
                   in_array($action, ['activate', 'deactivate', 'change_status', 'assign_owner']);
        }
        
        return false;
    }

    private function getAvailableTemplates(User $user, string $context): array
    {
        $query = ProjectTemplate::query();
        
        if ($context === 'admin' && $user->hasRole('super_admin')) {
            return $query->where('is_active', true)->get()->toArray();
        }
        
        return $query->where('is_active', true)
                    ->where('is_public', true)
                    ->get()
                    ->toArray();
    }

    private function getAvailableUsers(User $user, string $context): array
    {
        if ($context === 'admin' && $user->hasRole('super_admin')) {
            return \App\Models\User::with('tenant')->get()->toArray();
        }
        
        return \App\Models\User::where('tenant_id', $user->tenant_id)
                              ->where('is_active', true)
                              ->get()
                              ->toArray();
    }

    private function prepareProjectData(Request $request, string $context, ?Project $project = null): array
    {
        $data = $request->validated();
        
        // Set tenant_id based on context
        if ($context === 'app') {
            $data['tenant_id'] = Auth::user()->tenant_id;
        }
        
        // Set default values
        if (!isset($data['status'])) {
            $data['status'] = 'planning';
        }
        
        if (!isset($data['priority'])) {
            $data['priority'] = 'medium';
        }
        
        return $data;
    }

    private function prepareProjectDataFromTemplate(Request $request, ProjectTemplate $template, string $context): array
    {
        $data = $request->validated();
        
        // Merge template data
        $data = array_merge($template->toArray(), $data);
        
        // Set tenant_id based on context
        if ($context === 'app') {
            $data['tenant_id'] = Auth::user()->tenant_id;
        }
        
        // Remove template-specific fields
        unset($data['id'], $data['created_at'], $data['updated_at']);
        
        return $data;
    }

    private function enrichProjectData(Project $project, string $context): array
    {
        $projectData = $project->toArray();
        
        // Add additional data based on context
        $projectData['tasks'] = $project->tasks;
        $projectData['team'] = $project->team;
        $projectData['owner'] = $project->owner;
        
        if ($context === 'admin') {
            $projectData['tenant'] = $project->tenant;
            $projectData['analytics'] = $this->analyticsService->getProjectAnalytics(Auth::user(), $context, $project);
        }
        
        return $projectData;
    }

    private function getViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.projects.index', $data);
            case 'app':
                return view('app.projects.index', $data);
            default:
                return view('projects.index', $data);
        }
    }

    private function getCreateViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.projects.create', $data);
            case 'app':
                return view('app.projects.create', $data);
            default:
                return view('projects.create', $data);
        }
    }

    private function getShowViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.projects.show', $data);
            case 'app':
                return view('app.projects.show', $data);
            default:
                return view('projects.show', $data);
        }
    }

    private function getEditViewForContext(string $context, array $data): View
    {
        switch ($context) {
            case 'admin':
                return view('admin.projects.edit', $data);
            case 'app':
                return view('app.projects.edit', $data);
            default:
                return view('projects.edit', $data);
        }
    }

    private function getIndexRouteForContext(string $context): string
    {
        switch ($context) {
            case 'admin':
                return 'admin.projects.index';
            case 'app':
                return 'app.projects.index';
            default:
                return 'projects.index';
        }
    }
}
