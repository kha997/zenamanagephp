<?php declare(strict_types=1);

namespace App\Services;

use App\Models\CalendarEvent;
use App\Models\CalendarIntegration;
use Google\Client;
use Google\Service\Calendar;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * CalendarIntegrationService - Service cho calendar integrations
 */
class CalendarIntegrationService
{
    private Client $googleClient;

    public function __construct()
    {
        $this->googleClient = new Client();
        $this->googleClient->setClientId(config('services.google.client_id'));
        $this->googleClient->setClientSecret(config('services.google.client_secret'));
        $this->googleClient->setRedirectUri(config('services.google.redirect_uri'));
        $this->googleClient->setScopes([
            'https://www.googleapis.com/auth/calendar',
            'https://www.googleapis.com/auth/calendar.events'
        ]);
    }

    /**
     * Connect Google Calendar
     */
    public function connectGoogleCalendar(User $user, string $authCode): CalendarIntegration
    {
        try {
            $token = $this->googleClient->fetchAccessTokenWithAuthCode($authCode);
            
            if (isset($token['error'])) {
                throw new \Exception('Failed to get access token: ' . $token['error']);
            }

            $this->googleClient->setAccessToken($token);
            
            // Get calendar service
            $calendarService = new Calendar($this->googleClient);
            $calendarList = $calendarService->calendarList->listCalendarList();
            
            // Create integration for primary calendar
            $primaryCalendar = $calendarList->getItems()[0] ?? null;
            
            if (!$primaryCalendar) {
                throw new \Exception('No calendar found');
            }

            $integration = CalendarIntegration::create([
                'user_id' => $user->id,
                'provider' => CalendarIntegration::PROVIDER_GOOGLE,
                'calendar_id' => $primaryCalendar->getId(),
                'calendar_name' => $primaryCalendar->getSummary(),
                'access_token' => $token['access_token'],
                'refresh_token' => $token['refresh_token'] ?? null,
                'token_expires_at' => isset($token['expires_in']) 
                    ? now()->addSeconds($token['expires_in'])
                    : null,
                'provider_data' => [
                    'calendar_id' => $primaryCalendar->getId(),
                    'calendar_summary' => $primaryCalendar->getSummary(),
                    'calendar_description' => $primaryCalendar->getDescription(),
                    'time_zone' => $primaryCalendar->getTimeZone(),
                    'access_role' => $primaryCalendar->getAccessRole()
                ]
            ]);

            return $integration;
            
        } catch (\Exception $e) {
            Log::error('Failed to connect Google Calendar', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            
            throw $e;
        }
    }

    /**
     * Connect Outlook Calendar
     */
    public function connectOutlookCalendar(User $user, string $authCode): CalendarIntegration
    {
        try {
            $tokenResponse = Http::post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
                'client_id' => config('services.microsoft.client_id'),
                'client_secret' => config('services.microsoft.client_secret'),
                'code' => $authCode,
                'redirect_uri' => config('services.microsoft.redirect_uri'),
                'grant_type' => 'authorization_code'
            ]);

            if (!$tokenResponse->successful()) {
                throw new \Exception('Failed to get access token from Microsoft');
            }

            $tokenData = $tokenResponse->json();
            
            // Get user's calendars
            $calendarsResponse = Http::withToken($tokenData['access_token'])
                ->get('https://graph.microsoft.com/v1.0/me/calendars');

            if (!$calendarsResponse->successful()) {
                throw new \Exception('Failed to get calendars from Microsoft');
            }

            $calendars = $calendarsResponse->json();
            $primaryCalendar = $calendars['value'][0] ?? null;
            
            if (!$primaryCalendar) {
                throw new \Exception('No calendar found');
            }

            $integration = CalendarIntegration::create([
                'user_id' => $user->id,
                'provider' => CalendarIntegration::PROVIDER_OUTLOOK,
                'calendar_id' => $primaryCalendar['id'],
                'calendar_name' => $primaryCalendar['name'],
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? null,
                'token_expires_at' => isset($tokenData['expires_in']) 
                    ? now()->addSeconds($tokenData['expires_in'])
                    : null,
                'provider_data' => [
                    'calendar_id' => $primaryCalendar['id'],
                    'calendar_name' => $primaryCalendar['name'],
                    'calendar_color' => $primaryCalendar['color'] ?? null,
                    'can_edit' => $primaryCalendar['canEdit'] ?? false,
                    'can_share' => $primaryCalendar['canShare'] ?? false
                ]
            ]);

            return $integration;
            
        } catch (\Exception $e) {
            Log::error('Failed to connect Outlook Calendar', [
                'error' => $e->getMessage(),
                'user_id' => $user->id
            ]);
            
            throw $e;
        }
    }

    /**
     * Refresh access token
     */
    public function refreshAccessToken(CalendarIntegration $integration): bool
    {
        try {
            if ($integration->provider === CalendarIntegration::PROVIDER_GOOGLE) {
                return $this->refreshGoogleToken($integration);
            } elseif ($integration->provider === CalendarIntegration::PROVIDER_OUTLOOK) {
                return $this->refreshOutlookToken($integration);
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to refresh access token', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id
            ]);
            
            return false;
        }
    }

    /**
     * Refresh Google token
     */
    private function refreshGoogleToken(CalendarIntegration $integration): bool
    {
        $this->googleClient->setRefreshToken($integration->refresh_token);
        $token = $this->googleClient->fetchAccessTokenWithRefreshToken();
        
        if (isset($token['error'])) {
            return false;
        }

        $integration->update([
            'access_token' => $token['access_token'],
            'token_expires_at' => isset($token['expires_in']) 
                ? now()->addSeconds($token['expires_in'])
                : null
        ]);

        return true;
    }

    /**
     * Refresh Outlook token
     */
    private function refreshOutlookToken(CalendarIntegration $integration): bool
    {
        $response = Http::post('https://login.microsoftonline.com/common/oauth2/v2.0/token', [
            'client_id' => config('services.microsoft.client_id'),
            'client_secret' => config('services.microsoft.client_secret'),
            'refresh_token' => $integration->refresh_token,
            'grant_type' => 'refresh_token'
        ]);

        if (!$response->successful()) {
            return false;
        }

        $tokenData = $response->json();
        
        $integration->update([
            'access_token' => $tokenData['access_token'],
            'refresh_token' => $tokenData['refresh_token'] ?? $integration->refresh_token,
            'token_expires_at' => isset($tokenData['expires_in']) 
                ? now()->addSeconds($tokenData['expires_in'])
                : null
        ]);

        return true;
    }

    /**
     * Sync project milestones to calendar
     */
    public function syncProjectMilestones(Project $project, CalendarIntegration $integration): int
    {
        $syncedCount = 0;
        
        try {
            $milestones = $project->milestones()
                                ->whereNotNull('target_date')
                                ->get();

            foreach ($milestones as $milestone) {
                $event = CalendarEvent::createFromMilestone($milestone, $integration);
                
                if ($this->createExternalEvent($integration, $event)) {
                    $event->update(['is_synced' => true, 'last_synced_at' => now()]);
                    $syncedCount++;
                }
            }
            
            $integration->updateLastSync();
            
        } catch (\Exception $e) {
            Log::error('Failed to sync project milestones', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'integration_id' => $integration->id
            ]);
        }

        return $syncedCount;
    }

    /**
     * Sync project tasks to calendar
     */
    public function syncProjectTasks(Project $project, CalendarIntegration $integration): int
    {
        $syncedCount = 0;
        
        try {
            $tasks = $project->tasks()
                            ->whereNotNull('due_date')
                            ->get();

            foreach ($tasks as $task) {
                $event = CalendarEvent::createFromTask($task, $integration);
                
                if ($this->createExternalEvent($integration, $event)) {
                    $event->update(['is_synced' => true, 'last_synced_at' => now()]);
                    $syncedCount++;
                }
            }
            
            $integration->updateLastSync();
            
        } catch (\Exception $e) {
            Log::error('Failed to sync project tasks', [
                'error' => $e->getMessage(),
                'project_id' => $project->id,
                'integration_id' => $integration->id
            ]);
        }

        return $syncedCount;
    }

    /**
     * Create external calendar event
     */
    private function createExternalEvent(CalendarIntegration $integration, CalendarEvent $event): bool
    {
        try {
            if ($integration->provider === CalendarIntegration::PROVIDER_GOOGLE) {
                return $this->createGoogleEvent($integration, $event);
            } elseif ($integration->provider === CalendarIntegration::PROVIDER_OUTLOOK) {
                return $this->createOutlookEvent($integration, $event);
            }
            
            return false;
            
        } catch (\Exception $e) {
            Log::error('Failed to create external event', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id,
                'event_id' => $event->id
            ]);
            
            return false;
        }
    }

    /**
     * Create Google Calendar event
     */
    private function createGoogleEvent(CalendarIntegration $integration, CalendarEvent $event): bool
    {
        $this->googleClient->setAccessToken($integration->access_token);
        $calendarService = new Calendar($this->googleClient);

        $googleEvent = new \Google\Service\Calendar\Event([
            'summary' => $event->title,
            'description' => $event->description,
            'start' => [
                'dateTime' => $event->start_time->toRfc3339String(),
                'timeZone' => config('app.timezone')
            ],
            'end' => [
                'dateTime' => $event->end_time->toRfc3339String(),
                'timeZone' => config('app.timezone')
            ],
            'location' => $event->location
        ]);

        $createdEvent = $calendarService->events->insert($integration->calendar_id, $googleEvent);
        
        $event->update([
            'external_event_id' => $createdEvent->getId(),
            'metadata' => array_merge($event->metadata ?? [], [
                'google_event_id' => $createdEvent->getId(),
                'google_event_link' => $createdEvent->getHtmlLink()
            ])
        ]);

        return true;
    }

    /**
     * Create Outlook Calendar event
     */
    private function createOutlookEvent(CalendarIntegration $integration, CalendarEvent $event): bool
    {
        $response = Http::withToken($integration->access_token)
            ->post("https://graph.microsoft.com/v1.0/me/calendars/{$integration->calendar_id}/events", [
                'subject' => $event->title,
                'body' => [
                    'contentType' => 'text',
                    'content' => $event->description ?? ''
                ],
                'start' => [
                    'dateTime' => $event->start_time->toISOString(),
                    'timeZone' => config('app.timezone')
                ],
                'end' => [
                    'dateTime' => $event->end_time->toISOString(),
                    'timeZone' => config('app.timezone')
                ],
                'location' => [
                    'displayName' => $event->location ?? ''
                ]
            ]);

        if (!$response->successful()) {
            return false;
        }

        $outlookEvent = $response->json();
        
        $event->update([
            'external_event_id' => $outlookEvent['id'],
            'metadata' => array_merge($event->metadata ?? [], [
                'outlook_event_id' => $outlookEvent['id'],
                'outlook_event_link' => $outlookEvent['webLink']
            ])
        ]);

        return true;
    }

    /**
     * Sync external events to local database
     */
    public function syncExternalEvents(CalendarIntegration $integration, Carbon $startDate, Carbon $endDate): int
    {
        $syncedCount = 0;
        
        try {
            if ($integration->provider === CalendarIntegration::PROVIDER_GOOGLE) {
                $syncedCount = $this->syncGoogleEvents($integration, $startDate, $endDate);
            } elseif ($integration->provider === CalendarIntegration::PROVIDER_OUTLOOK) {
                $syncedCount = $this->syncOutlookEvents($integration, $startDate, $endDate);
            }
            
            $integration->updateLastSync();
            
        } catch (\Exception $e) {
            Log::error('Failed to sync external events', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id
            ]);
        }

        return $syncedCount;
    }

    /**
     * Sync Google Calendar events
     */
    private function syncGoogleEvents(CalendarIntegration $integration, Carbon $startDate, Carbon $endDate): int
    {
        $this->googleClient->setAccessToken($integration->access_token);
        $calendarService = new Calendar($this->googleClient);

        $events = $calendarService->events->listEvents(
            $integration->calendar_id,
            [
                'timeMin' => $startDate->toRfc3339String(),
                'timeMax' => $endDate->toRfc3339String(),
                'singleEvents' => true,
                'orderBy' => 'startTime'
            ]
        );

        $syncedCount = 0;
        
        foreach ($events->getItems() as $googleEvent) {
            $existingEvent = CalendarEvent::where('external_event_id', $googleEvent->getId())
                                        ->where('calendar_integration_id', $integration->id)
                                        ->first();

            if (!$existingEvent) {
                CalendarEvent::create([
                    'calendar_integration_id' => $integration->id,
                    'external_event_id' => $googleEvent->getId(),
                    'title' => $googleEvent->getSummary(),
                    'description' => $googleEvent->getDescription(),
                    'start_time' => $googleEvent->getStart()->getDateTime() ?? $googleEvent->getStart()->getDate(),
                    'end_time' => $googleEvent->getEnd()->getDateTime() ?? $googleEvent->getEnd()->getDate(),
                    'location' => $googleEvent->getLocation(),
                    'status' => $googleEvent->getStatus(),
                    'all_day' => $googleEvent->getStart()->getDate() !== null,
                    'is_synced' => true,
                    'last_synced_at' => now(),
                    'metadata' => [
                        'google_event_id' => $googleEvent->getId(),
                        'google_event_link' => $googleEvent->getHtmlLink(),
                        'creator' => $googleEvent->getCreator(),
                        'organizer' => $googleEvent->getOrganizer()
                    ]
                ]);
                
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    /**
     * Sync Outlook Calendar events
     */
    private function syncOutlookEvents(CalendarIntegration $integration, Carbon $startDate, Carbon $endDate): int
    {
        $response = Http::withToken($integration->access_token)
            ->get("https://graph.microsoft.com/v1.0/me/calendars/{$integration->calendar_id}/events", [
                'startDateTime' => $startDate->toISOString(),
                'endDateTime' => $endDate->toISOString(),
                '$orderby' => 'start/dateTime'
            ]);

        if (!$response->successful()) {
            return 0;
        }

        $events = $response->json();
        $syncedCount = 0;
        
        foreach ($events['value'] as $outlookEvent) {
            $existingEvent = CalendarEvent::where('external_event_id', $outlookEvent['id'])
                                        ->where('calendar_integration_id', $integration->id)
                                        ->first();

            if (!$existingEvent) {
                CalendarEvent::create([
                    'calendar_integration_id' => $integration->id,
                    'external_event_id' => $outlookEvent['id'],
                    'title' => $outlookEvent['subject'],
                    'description' => $outlookEvent['body']['content'] ?? '',
                    'start_time' => $outlookEvent['start']['dateTime'],
                    'end_time' => $outlookEvent['end']['dateTime'],
                    'location' => $outlookEvent['location']['displayName'] ?? '',
                    'status' => $outlookEvent['isCancelled'] ? 'cancelled' : 'confirmed',
                    'all_day' => $outlookEvent['isAllDay'] ?? false,
                    'is_synced' => true,
                    'last_synced_at' => now(),
                    'metadata' => [
                        'outlook_event_id' => $outlookEvent['id'],
                        'outlook_event_link' => $outlookEvent['webLink'],
                        'organizer' => $outlookEvent['organizer'],
                        'attendees' => $outlookEvent['attendees'] ?? []
                    ]
                ]);
                
                $syncedCount++;
            }
        }

        return $syncedCount;
    }

    /**
     * Get user's calendar overview
     */
    public function getUserCalendarOverview(User $user, int $days = 30): array
    {
        $integrations = $user->calendarIntegrations()
                           ->active()
                           ->syncEnabled()
                           ->get();

        $overview = [
            'total_integrations' => $integrations->count(),
            'healthy_integrations' => $integrations->filter(fn($i) => $i->isHealthy())->count(),
            'total_events' => 0,
            'project_events' => 0,
            'upcoming_events' => collect(),
            'today_events' => collect(),
            'conflicts' => collect()
        ];

        foreach ($integrations as $integration) {
            $events = CalendarEvent::getUserCalendarOverview($user->id, $days);
            $overview['total_events'] += $events['total_events'];
            $overview['project_events'] += $events['project_events'];
            $overview['upcoming_events'] = $overview['upcoming_events']->merge($events['upcoming_events']);
            $overview['today_events'] = $overview['today_events']->merge($events['today_events']);
            $overview['conflicts'] = $overview['conflicts']->merge($events['conflicts']);
        }

        return $overview;
    }

    /**
     * Disconnect calendar integration
     */
    public function disconnectCalendar(CalendarIntegration $integration): bool
    {
        try {
            // Delete all associated events
            $integration->events()->delete();
            
            // Deactivate integration
            $integration->update([
                'is_active' => false,
                'sync_enabled' => false,
                'access_token' => null,
                'refresh_token' => null
            ]);

            return true;
            
        } catch (\Exception $e) {
            Log::error('Failed to disconnect calendar', [
                'error' => $e->getMessage(),
                'integration_id' => $integration->id
            ]);
            
            return false;
        }
    }
}