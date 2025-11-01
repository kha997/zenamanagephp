<?php

namespace App\Listeners;
use Illuminate\Support\Facades\Auth;


use App\Models\Document;
use App\Models\Notification;
use App\Models\User;
use App\Events\DocumentCreated;
use App\Events\DocumentUpdated;
use App\Events\DocumentDeleted;
use App\Events\DocumentApproved;
use App\Events\DocumentVersionChanged;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class DocumentEventListener implements ShouldQueue
{
    use InteractsWithQueue;

    /**
     * Handle document created event.
     */
    public function handleDocumentCreated(DocumentCreated $event)
    {
        $document = $event->document;
        
        Log::info('Document created', [
            'document_id' => $document->id,
            'document_name' => $document->name,
            'tenant_id' => $document->tenant_id,
            'created_by' => $document->created_by
        ]);

        // Notify project team members
        if ($document->project) {
            $teamMembers = $document->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $document->tenant_id,
                    'project_id' => $document->project_id,
                    'type' => 'document_created',
                    'title' => 'New Document Created',
                    'message' => "Document '{$document->name}' has been created",
                    'data' => json_encode([
                        'document_id' => $document->id,
                        'document_name' => $document->name,
                        'project_id' => $document->project_id
                    ]),
                    'priority' => 'normal',
                    'channels' => json_encode(['inapp', 'email'])
                ]);
            }
        }
    }

    /**
     * Handle document updated event.
     */
    public function handleDocumentUpdated(DocumentUpdated $event)
    {
        $document = $event->document;
        
        Log::info('Document updated', [
            'document_id' => $document->id,
            'document_name' => $document->name,
            'tenant_id' => $document->tenant_id,
            'updated_by' => $document->updated_by
        ]);

        // Notify document watchers
        $watchers = User::whereHas('documentWatchers', function($query) use ($document) {
            $query->where('document_id', $document->id);
        })->get();

        foreach ($watchers as $watcher) {
            Notification::create([
                'user_id' => $watcher->id,
                'tenant_id' => $document->tenant_id,
                'project_id' => $document->project_id,
                'type' => 'document_updated',
                'title' => 'Document Updated',
                'message' => "Document '{$document->name}' has been updated",
                'data' => json_encode([
                    'document_id' => $document->id,
                    'document_name' => $document->name,
                    'project_id' => $document->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }

    /**
     * Handle document deleted event.
     */
    public function handleDocumentDeleted(DocumentDeleted $event)
    {
        $document = $event->document;
        
        Log::info('Document deleted', [
            'document_id' => $document->id,
            'document_name' => $document->name,
            'tenant_id' => $document->tenant_id,
            'deleted_by' => Auth::id()
        ]);

        // Notify project team members
        if ($document->project) {
            $teamMembers = $document->project->teams()
                ->with('members')
                ->get()
                ->pluck('members')
                ->flatten()
                ->unique('id');

            foreach ($teamMembers as $member) {
                Notification::create([
                    'user_id' => $member->id,
                    'tenant_id' => $document->tenant_id,
                    'project_id' => $document->project_id,
                    'type' => 'document_deleted',
                    'title' => 'Document Deleted',
                    'message' => "Document '{$document->name}' has been deleted",
                    'data' => json_encode([
                        'document_id' => $document->id,
                        'document_name' => $document->name,
                        'project_id' => $document->project_id
                    ]),
                    'priority' => 'high',
                    'channels' => json_encode(['inapp', 'email'])
                ]);
            }
        }
    }

    /**
     * Handle document approved event.
     */
    public function handleDocumentApproved(DocumentApproved $event)
    {
        $document = $event->document;
        
        Log::info('Document approved', [
            'document_id' => $document->id,
            'document_name' => $document->name,
            'tenant_id' => $document->tenant_id,
            'approved_by' => Auth::id()
        ]);

        // Notify document creator
        if ($document->created_by) {
            Notification::create([
                'user_id' => $document->created_by,
                'tenant_id' => $document->tenant_id,
                'project_id' => $document->project_id,
                'type' => 'document_approved',
                'title' => 'Document Approved',
                'message' => "Document '{$document->name}' has been approved",
                'data' => json_encode([
                    'document_id' => $document->id,
                    'document_name' => $document->name,
                    'project_id' => $document->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp', 'email'])
            ]);
        }
    }

    /**
     * Handle document version changed event.
     */
    public function handleDocumentVersionChanged(DocumentVersionChanged $event)
    {
        $document = $event->document;
        $oldVersion = $event->oldVersion;
        $newVersion = $event->newVersion;
        
        Log::info('Document version changed', [
            'document_id' => $document->id,
            'document_name' => $document->name,
            'old_version' => $oldVersion,
            'new_version' => $newVersion,
            'tenant_id' => $document->tenant_id
        ]);

        // Notify document watchers about version change
        $watchers = User::whereHas('documentWatchers', function($query) use ($document) {
            $query->where('document_id', $document->id);
        })->get();

        foreach ($watchers as $watcher) {
            Notification::create([
                'user_id' => $watcher->id,
                'tenant_id' => $document->tenant_id,
                'project_id' => $document->project_id,
                'type' => 'document_version_changed',
                'title' => 'Document Version Updated',
                'message' => "Document '{$document->name}' version changed from {$oldVersion} to {$newVersion}",
                'data' => json_encode([
                    'document_id' => $document->id,
                    'document_name' => $document->name,
                    'old_version' => $oldVersion,
                    'new_version' => $newVersion,
                    'project_id' => $document->project_id
                ]),
                'priority' => 'normal',
                'channels' => json_encode(['inapp'])
            ]);
        }
    }
}
