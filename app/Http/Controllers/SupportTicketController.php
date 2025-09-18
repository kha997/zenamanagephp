<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use App\Notifications\SupportTicketCreated;
use App\Notifications\SupportTicketUpdated;
use App\Notifications\SupportTicketResolved;
use Carbon\Carbon;

class SupportTicketController extends Controller
{
    /**
     * Display a listing of support tickets
     */
    public function index(Request $request)
    {
        $query = SupportTicket::with(['user', 'assignedTo', 'messages']);

        // Filter by status
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        // Filter by priority
        if ($request->has('priority')) {
            $query->where('priority', $request->priority);
        }

        // Filter by category
        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        // Search
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('ticket_number', 'like', "%{$search}%");
            });
        }

        // Sort
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $tickets = $query->paginate(20);

        return view('admin.support.tickets.index', compact('tickets'));
    }

    /**
     * Show the form for creating a new support ticket
     */
    public function create()
    {
        $categories = [
            'technical' => 'Technical Issue',
            'billing' => 'Billing Question',
            'feature_request' => 'Feature Request',
            'bug_report' => 'Bug Report',
            'general' => 'General Inquiry'
        ];

        $priorities = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];

        return view('admin.support.tickets.create', compact('categories', 'priorities'));
    }

    /**
     * Store a newly created support ticket
     */
    public function store(Request $request)
    {
        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:technical,billing,feature_request,bug_report,general',
            'priority' => 'required|in:low,medium,high,urgent',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240' // 10MB max per file
        ]);

        $ticket = SupportTicket::create([
            'ticket_number' => $this->generateTicketNumber(),
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority,
            'status' => 'open',
            'user_id' => auth()->id(),
            'assigned_to' => null,
            'due_date' => $this->calculateDueDate($request->priority)
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('support-tickets/' . $ticket->id, 'public');
                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ];
            }
            $ticket->update(['attachments' => $attachments]);
        }

        // Send notification to support team
        $this->notifySupportTeam($ticket);

        // Log the ticket creation
        activity()
            ->performedOn($ticket)
            ->withProperties(['ticket_number' => $ticket->ticket_number])
            ->log('Support ticket created');

        return redirect()->route('support.tickets.show', $ticket)
            ->with('success', 'Support ticket created successfully. Ticket number: ' . $ticket->ticket_number);
    }

    /**
     * Display the specified support ticket
     */
    public function show(SupportTicket $ticket)
    {
        $ticket->load(['user', 'assignedTo', 'messages.user']);

        // Mark as read if user is assigned to or created the ticket
        if (auth()->user()->can('view', $ticket)) {
            $ticket->markAsRead();
        }

        return view('admin.support.tickets.show', compact('ticket'));
    }

    /**
     * Show the form for editing the specified support ticket
     */
    public function edit(SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);

        $categories = [
            'technical' => 'Technical Issue',
            'billing' => 'Billing Question',
            'feature_request' => 'Feature Request',
            'bug_report' => 'Bug Report',
            'general' => 'General Inquiry'
        ];

        $priorities = [
            'low' => 'Low',
            'medium' => 'Medium',
            'high' => 'High',
            'urgent' => 'Urgent'
        ];

        $statuses = [
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'pending_customer' => 'Pending Customer',
            'resolved' => 'Resolved',
            'closed' => 'Closed'
        ];

        $supportAgents = User::where('role', 'support_agent')
            ->orWhere('role', 'admin')
            ->get();

        return view('admin.support.tickets.edit', compact('ticket', 'categories', 'priorities', 'statuses', 'supportAgents'));
    }

    /**
     * Update the specified support ticket
     */
    public function update(Request $request, SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);

        $request->validate([
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|in:technical,billing,feature_request,bug_report,general',
            'priority' => 'required|in:low,medium,high,urgent',
            'status' => 'required|in:open,in_progress,pending_customer,resolved,closed',
            'assigned_to' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date|after:today'
        ]);

        $oldStatus = $ticket->status;
        $oldAssignedTo = $ticket->assigned_to;

        $ticket->update([
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority,
            'status' => $request->status,
            'assigned_to' => $request->assigned_to,
            'due_date' => $request->due_date
        ]);

        // Send notifications for status changes
        if ($oldStatus !== $request->status) {
            $this->notifyStatusChange($ticket, $oldStatus, $request->status);
        }

        // Send notifications for assignment changes
        if ($oldAssignedTo !== $request->assigned_to) {
            $this->notifyAssignmentChange($ticket, $oldAssignedTo, $request->assigned_to);
        }

        // Log the update
        activity()
            ->performedOn($ticket)
            ->withProperties([
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'old_assigned_to' => $oldAssignedTo,
                'new_assigned_to' => $request->assigned_to
            ])
            ->log('Support ticket updated');

        return redirect()->route('support.tickets.show', $ticket)
            ->with('success', 'Support ticket updated successfully');
    }

    /**
     * Add a message to the support ticket
     */
    public function addMessage(Request $request, SupportTicket $ticket)
    {
        $request->validate([
            'message' => 'required|string',
            'attachments' => 'nullable|array|max:5',
            'attachments.*' => 'file|max:10240'
        ]);

        $message = SupportTicketMessage::create([
            'ticket_id' => $ticket->id,
            'user_id' => auth()->id(),
            'message' => $request->message,
            'is_internal' => $request->boolean('is_internal', false)
        ]);

        // Handle attachments
        if ($request->hasFile('attachments')) {
            $attachments = [];
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('support-tickets/' . $ticket->id . '/messages', 'public');
                $attachments[] = [
                    'filename' => $file->getClientOriginalName(),
                    'path' => $path,
                    'size' => $file->getSize(),
                    'mime_type' => $file->getMimeType()
                ];
            }
            $message->update(['attachments' => $attachments]);
        }

        // Update ticket status if message is from support agent
        if (auth()->user()->hasRole(['support_agent', 'admin']) && !$request->boolean('is_internal')) {
            $ticket->update(['status' => 'in_progress']);
        }

        // Send notifications
        $this->notifyNewMessage($ticket, $message);

        // Log the message
        activity()
            ->performedOn($message)
            ->withProperties(['ticket_id' => $ticket->id])
            ->log('Support ticket message added');

        return redirect()->route('support.tickets.show', $ticket)
            ->with('success', 'Message added successfully');
    }

    /**
     * Close the specified support ticket
     */
    public function close(SupportTicket $ticket)
    {
        $this->authorize('update', $ticket);

        $ticket->update([
            'status' => 'closed',
            'closed_at' => now(),
            'closed_by' => auth()->id()
        ]);

        // Send notification
        $this->notifyTicketClosed($ticket);

        // Log the closure
        activity()
            ->performedOn($ticket)
            ->log('Support ticket closed');

        return redirect()->route('support.tickets.show', $ticket)
            ->with('success', 'Support ticket closed successfully');
    }

    /**
     * Generate unique ticket number
     */
    private function generateTicketNumber()
    {
        do {
            $number = 'TKT-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        } while (SupportTicket::where('ticket_number', $number)->exists());

        return $number;
    }

    /**
     * Calculate due date based on priority
     */
    private function calculateDueDate($priority)
    {
        $days = match($priority) {
            'urgent' => 1,
            'high' => 3,
            'medium' => 7,
            'low' => 14,
            default => 7
        };

        return Carbon::now()->addDays($days);
    }

    /**
     * Notify support team of new ticket
     */
    private function notifySupportTeam(SupportTicket $ticket)
    {
        $supportAgents = User::where('role', 'support_agent')
            ->orWhere('role', 'admin')
            ->get();

        foreach ($supportAgents as $agent) {
            $agent->notify(new SupportTicketCreated($ticket));
        }
    }

    /**
     * Notify status change
     */
    private function notifyStatusChange(SupportTicket $ticket, $oldStatus, $newStatus)
    {
        $ticket->user->notify(new SupportTicketUpdated($ticket, 'status', $oldStatus, $newStatus));

        if ($ticket->assignedTo) {
            $ticket->assignedTo->notify(new SupportTicketUpdated($ticket, 'status', $oldStatus, $newStatus));
        }
    }

    /**
     * Notify assignment change
     */
    private function notifyAssignmentChange(SupportTicket $ticket, $oldAssignedTo, $newAssignedTo)
    {
        if ($newAssignedTo) {
            $newAgent = User::find($newAssignedTo);
            $newAgent->notify(new SupportTicketCreated($ticket));
        }
    }

    /**
     * Notify new message
     */
    private function notifyNewMessage(SupportTicket $ticket, SupportTicketMessage $message)
    {
        // Notify ticket creator
        if ($message->user_id !== $ticket->user_id) {
            $ticket->user->notify(new SupportTicketUpdated($ticket, 'message', null, $message->message));
        }

        // Notify assigned agent
        if ($ticket->assignedTo && $message->user_id !== $ticket->assignedTo->id) {
            $ticket->assignedTo->notify(new SupportTicketUpdated($ticket, 'message', null, $message->message));
        }
    }

    /**
     * Notify ticket closed
     */
    private function notifyTicketClosed(SupportTicket $ticket)
    {
        $ticket->user->notify(new SupportTicketResolved($ticket));

        if ($ticket->assignedTo) {
            $ticket->assignedTo->notify(new SupportTicketResolved($ticket));
        }
    }

    /**
     * Get support ticket statistics
     */
    public function statistics()
    {
        $stats = [
            'total_tickets' => SupportTicket::count(),
            'open_tickets' => SupportTicket::where('status', 'open')->count(),
            'in_progress_tickets' => SupportTicket::where('status', 'in_progress')->count(),
            'resolved_tickets' => SupportTicket::where('status', 'resolved')->count(),
            'closed_tickets' => SupportTicket::where('status', 'closed')->count(),
            'urgent_tickets' => SupportTicket::where('priority', 'urgent')->count(),
            'overdue_tickets' => SupportTicket::where('due_date', '<', now())->where('status', '!=', 'closed')->count(),
            'avg_resolution_time' => $this->getAverageResolutionTime(),
            'tickets_by_category' => SupportTicket::selectRaw('category, COUNT(*) as count')
                ->groupBy('category')
                ->get(),
            'tickets_by_priority' => SupportTicket::selectRaw('priority, COUNT(*) as count')
                ->groupBy('priority')
                ->get()
        ];

        return view('admin.support.statistics', compact('stats'));
    }

    /**
     * Get average resolution time
     */
    private function getAverageResolutionTime()
    {
        $resolvedTickets = SupportTicket::where('status', 'closed')
            ->whereNotNull('closed_at')
            ->get();

        if ($resolvedTickets->isEmpty()) {
            return 0;
        }

        $totalHours = $resolvedTickets->sum(function ($ticket) {
            return $ticket->created_at->diffInHours($ticket->closed_at);
        });

        return round($totalHours / $resolvedTickets->count(), 2);
    }
}
