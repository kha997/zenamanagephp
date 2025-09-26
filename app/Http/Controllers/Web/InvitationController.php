<?php

namespace App\Http\Controllers\Web;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use App\Http\Controllers\Controller;

class InvitationController extends Controller
{
    // protected $emailService;

    // public function __construct(EmailService $emailService = null)
    // {
    //     $this->emailService = $emailService;
    // }
    /**
     * Display invitation management page
     */
    public function index(Request $request): View
    {
        // Get authenticated user from session
        $user = $request->get('authenticated_user');
        $organizationId = $user->organization_id ?? 1;
        
        $invitations = Invitation::with(['project', 'inviter', 'accepter'])
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->paginate(15);

        return view('invitations.index', compact('invitations'));
    }

    /**
     * Show the form for creating a new invitation
     */
    public function create(Request $request): View
    {
        // Get authenticated user from session
        $user = $request->get('authenticated_user');
        $organizationId = $user->organization_id ?? 1;
        
        // Get organization or create a demo one
        $organization = Organization::find($organizationId);
        
        if (!$organization) {
            // Create a demo organization if none exists
            $organization = Organization::create([
                'name' => 'Demo Organization',
                'slug' => 'demo-org',
                'domain' => 'demo.com',
                'description' => 'Demo organization for testing',
                'status' => 'active'
            ]);
        }
        
        try {
            $projects = Project::where('organization_id', $organization->id)->get();
        } catch (\Exception $e) {
            $projects = collect(); // Empty collection if projects table doesn't exist or has issues
        }
        
        $roles = $organization->getAvailableRoles();

        return view('invitations.create', compact('projects', 'roles'));
    }

    /**
     * Store a newly created invitation
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|max:255',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'role' => 'required|string',
            'project_id' => 'nullable|exists:projects,id',
            'message' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Get authenticated user from session
        $user = $request->get('authenticated_user');
        $organizationId = $user->organization_id ?? 1;
        $organization = Organization::find($organizationId);

        // Check if user already exists
        $existingUser = User::where('email', $request->email)
            ->where('organization_id', $organization->id)
            ->first();

        if ($existingUser) {
            return response()->json([
                'success' => false,
                'message' => 'User with this email already exists in your organization'
            ], 400);
        }

        try {
            $invitation = Invitation::create([
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'role' => $request->role,
                'project_id' => $request->project_id,
                'message' => $request->message,
                'organization_id' => $organization->id,
                'invited_by' => $user->id,
                'expires_at' => Carbon::now()->addDays(7),
            ]);

            
            // $emailSent = $this->emailService->sendInvitationEmail($invitation);

            return response()->json([
                'success' => true,
                'message' => 'Invitation created successfully!',
                'data' => $invitation,
                'email_sent' => false // Will be true when email is implemented
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Show invitation acceptance form
     */
    public function accept(Request $request, string $token): View
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation || !$invitation->canBeAccepted()) {
            return view('invitations.expired');
        }

        return view('invitations.accept', compact('invitation'));
    }

    /**
     * Process invitation acceptance
     */
    public function processAcceptance(Request $request, string $token): JsonResponse
    {
        $invitation = Invitation::findByToken($token);

        if (!$invitation || !$invitation->canBeAccepted()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired invitation'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8|confirmed',
            'phone' => 'nullable|string|max:20',
            'job_title' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Create user
            $user = User::create([
                'name' => $invitation->full_name ?: $invitation->email,
                'email' => $invitation->email,
                'password' => bcrypt($request->password),
                'organization_id' => $invitation->organization_id,
                'invitation_id' => $invitation->id,
                'invited_at' => $invitation->created_at,
                'joined_at' => now(),
                'status' => 'active',
                'email_verified_at' => now(),
                'first_name' => $invitation->first_name,
                'last_name' => $invitation->last_name,
                'phone' => $request->phone,
                'job_title' => $request->job_title,
            ]);

            // Mark invitation as accepted
            $invitation->markAsAccepted($user->id);

            return response()->json([
                'success' => true,
                'message' => 'Account created successfully',
                'data' => $user
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create account: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display invitation management page for admins
     */
    public function manage(Request $request): View
    {
        $user = $request->get('authenticated_user');
        $organizationId = $user->organization_id ?? 1;
        
        $invitations = Invitation::with(['project', 'inviter', 'accepter'])
            ->where('organization_id', $organizationId)
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return view('invitations.manage', compact('invitations'));
    }

    /**
     * Bulk create invitations
     */
    public function bulkCreate(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'invitations' => 'required|array|min:1|max:50',
            'invitations.*.email' => 'required|email|max:255',
            'invitations.*.first_name' => 'nullable|string|max:255',
            'invitations.*.last_name' => 'nullable|string|max:255',
            'invitations.*.role' => 'required|string',
            'invitations.*.project_id' => 'nullable|exists:projects,id',
            'role' => 'required|string', // Default role for all
            'project_id' => 'nullable|exists:projects,id', // Default project for all
            'message' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->get('authenticated_user');
        $organizationId = $user->organization_id ?? 1;
        $organization = Organization::find($organizationId);
        
        $createdInvitations = [];
        $errors = [];

        foreach ($request->invitations as $index => $invitationData) {
            try {
                // Check for existing user
                $existingUser = User::where('email', $invitationData['email'])
                    ->where('organization_id', $organization->id)
                    ->first();

                if ($existingUser) {
                    $errors[] = "Row {$index}: User already exists";
                    continue;
                }

                // Check for existing invitation
                $existingInvitation = Invitation::where('email', $invitationData['email'])
                    ->where('organization_id', $organization->id)
                    ->where('status', 'pending')
                    ->first();

                if ($existingInvitation) {
                    $errors[] = "Row {$index}: Pending invitation already exists";
                    continue;
                }

                $invitation = Invitation::create([
                    'email' => $invitationData['email'],
                    'first_name' => $invitationData['first_name'] ?? null,
                    'last_name' => $invitationData['last_name'] ?? null,
                    'role' => $invitationData['role'] ?? $request->role,
                    'project_id' => $invitationData['project_id'] ?? $request->project_id,
                    'message' => $request->message,
                    'organization_id' => $organization->id,
                    'invited_by' => $user->id,
                    'expires_at' => Carbon::now()->addDays(7),
                ]);

                $createdInvitations[] = $invitation;

            } catch (\Exception $e) {
                $errors[] = "Row {$index}: " . $e->getMessage();
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Created " . count($createdInvitations) . " invitations successfully",
            'data' => [
                'created_count' => count($createdInvitations),
                'error_count' => count($errors),
                'errors' => $errors
            ]
        ]);
    }

    /**
     * Resend invitation
     */
    public function resend(Request $request, Invitation $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Can only resend pending invitations'
            ], 400);
        }

        try {
            $invitation->resend();
            

            return response()->json([
                'success' => true,
                'message' => 'Invitation resent successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cancel invitation
     */
    public function cancel(Request $request, Invitation $invitation): JsonResponse
    {
        if ($invitation->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'Can only cancel pending invitations'
            ], 400);
        }

        try {
            $invitation->markAsCancelled();

            return response()->json([
                'success' => true,
                'message' => 'Invitation cancelled successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to cancel invitation: ' . $e->getMessage()
            ], 500);
        }
    }
}