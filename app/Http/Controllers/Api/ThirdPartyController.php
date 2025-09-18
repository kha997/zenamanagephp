<?php declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\BaseApiController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Third Party Integration Controller
 * 
 * Handles integrations with external services like Slack, Teams, Discord, GitHub, Jira, etc.
 */
class ThirdPartyController extends BaseApiController
{
    /**
     * Send notification to Slack
     */
    public function sendSlackNotification(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'webhook_url' => 'required|url',
                'message' => 'required|string|max:4000',
                'channel' => 'nullable|string|max:255',
                'username' => 'nullable|string|max:255',
                'icon_emoji' => 'nullable|string|max:255'
            ]);

            $payload = [
                'text' => $request->input('message'),
                'channel' => $request->input('channel'),
                'username' => $request->input('username', 'ZENA Bot'),
                'icon_emoji' => $request->input('icon_emoji', ':robot_face:')
            ];

            $response = Http::post($request->input('webhook_url'), $payload);

            if ($response->successful()) {
                return $this->successResponse(null, 'Slack notification sent successfully');
            }

            return $this->errorResponse('Failed to send Slack notification', 400);

        } catch (\Exception $e) {
            Log::error('Slack notification failed: ' . $e->getMessage());
            return $this->errorResponse('Notification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send notification to Microsoft Teams
     */
    public function sendTeamsNotification(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'webhook_url' => 'required|url',
                'title' => 'required|string|max:255',
                'message' => 'required|string|max:4000',
                'theme_color' => 'nullable|string|max:7'
            ]);

            $payload = [
                '@type' => 'MessageCard',
                '@context' => 'http://schema.org/extensions',
                'summary' => $request->input('title'),
                'themeColor' => $request->input('theme_color', '0078D4'),
                'sections' => [
                    [
                        'activityTitle' => $request->input('title'),
                        'text' => $request->input('message')
                    ]
                ]
            ];

            $response = Http::post($request->input('webhook_url'), $payload);

            if ($response->successful()) {
                return $this->successResponse(null, 'Teams notification sent successfully');
            }

            return $this->errorResponse('Failed to send Teams notification', 400);

        } catch (\Exception $e) {
            Log::error('Teams notification failed: ' . $e->getMessage());
            return $this->errorResponse('Notification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Send notification to Discord
     */
    public function sendDiscordNotification(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'webhook_url' => 'required|url',
                'content' => 'required|string|max:2000',
                'username' => 'nullable|string|max:255',
                'avatar_url' => 'nullable|url'
            ]);

            $payload = [
                'content' => $request->input('content'),
                'username' => $request->input('username', 'ZENA Bot'),
                'avatar_url' => $request->input('avatar_url')
            ];

            $response = Http::post($request->input('webhook_url'), $payload);

            if ($response->successful()) {
                return $this->successResponse(null, 'Discord notification sent successfully');
            }

            return $this->errorResponse('Failed to send Discord notification', 400);

        } catch (\Exception $e) {
            Log::error('Discord notification failed: ' . $e->getMessage());
            return $this->errorResponse('Notification failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create GitHub issue
     */
    public function createGitHubIssue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'token' => 'required|string',
                'owner' => 'required|string|max:255',
                'repo' => 'required|string|max:255',
                'title' => 'required|string|max:255',
                'body' => 'required|string|max:65536',
                'labels' => 'nullable|array',
                'assignees' => 'nullable|array'
            ]);

            $payload = [
                'title' => $request->input('title'),
                'body' => $request->input('body'),
                'labels' => $request->input('labels', []),
                'assignees' => $request->input('assignees', [])
            ];

            $response = Http::withHeaders([
                'Authorization' => 'token ' . $request->input('token'),
                'Accept' => 'application/vnd.github.v3+json'
            ])->post("https://api.github.com/repos/{$request->input('owner')}/{$request->input('repo')}/issues", $payload);

            if ($response->successful()) {
                $issue = $response->json();
                return $this->successResponse([
                    'issue_number' => $issue['number'],
                    'html_url' => $issue['html_url'],
                    'state' => $issue['state']
                ], 'GitHub issue created successfully');
            }

            return $this->errorResponse('Failed to create GitHub issue: ' . $response->body(), 400);

        } catch (\Exception $e) {
            Log::error('GitHub issue creation failed: ' . $e->getMessage());
            return $this->errorResponse('Issue creation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create Jira issue
     */
    public function createJiraIssue(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'base_url' => 'required|url',
                'username' => 'required|string|max:255',
                'api_token' => 'required|string',
                'project_key' => 'required|string|max:255',
                'issue_type' => 'required|string|max:255',
                'summary' => 'required|string|max:255',
                'description' => 'required|string|max:65536',
                'priority' => 'nullable|string|max:255',
                'assignee' => 'nullable|string|max:255'
            ]);

            $payload = [
                'fields' => [
                    'project' => ['key' => $request->input('project_key')],
                    'summary' => $request->input('summary'),
                    'description' => $request->input('description'),
                    'issuetype' => ['name' => $request->input('issue_type')]
                ]
            ];

            if ($request->has('priority')) {
                $payload['fields']['priority'] = ['name' => $request->input('priority')];
            }

            if ($request->has('assignee')) {
                $payload['fields']['assignee'] = ['name' => $request->input('assignee')];
            }

            $response = Http::withBasicAuth($request->input('username'), $request->input('api_token'))
                ->post($request->input('base_url') . '/rest/api/3/issue', $payload);

            if ($response->successful()) {
                $issue = $response->json();
                return $this->successResponse([
                    'issue_key' => $issue['key'],
                    'issue_id' => $issue['id'],
                    'self' => $issue['self']
                ], 'Jira issue created successfully');
            }

            return $this->errorResponse('Failed to create Jira issue: ' . $response->body(), 400);

        } catch (\Exception $e) {
            Log::error('Jira issue creation failed: ' . $e->getMessage());
            return $this->errorResponse('Issue creation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create Trello card
     */
    public function createTrelloCard(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'api_key' => 'required|string',
                'token' => 'required|string',
                'id_list' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'desc' => 'nullable|string|max:16384',
                'due' => 'nullable|date',
                'labels' => 'nullable|array'
            ]);

            $payload = [
                'key' => $request->input('api_key'),
                'token' => $request->input('token'),
                'idList' => $request->input('id_list'),
                'name' => $request->input('name'),
                'desc' => $request->input('desc'),
                'due' => $request->input('due'),
                'idLabels' => $request->input('labels', [])
            ];

            $response = Http::post('https://api.trello.com/1/cards', $payload);

            if ($response->successful()) {
                $card = $response->json();
                return $this->successResponse([
                    'card_id' => $card['id'],
                    'short_url' => $card['shortUrl'],
                    'url' => $card['url']
                ], 'Trello card created successfully');
            }

            return $this->errorResponse('Failed to create Trello card: ' . $response->body(), 400);

        } catch (\Exception $e) {
            Log::error('Trello card creation failed: ' . $e->getMessage());
            return $this->errorResponse('Card creation failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create Asana task
     */
    public function createAsanaTask(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'access_token' => 'required|string',
                'workspace' => 'required|string|max:255',
                'name' => 'required|string|max:255',
                'notes' => 'nullable|string|max:65536',
                'due_on' => 'nullable|date',
                'assignee' => 'nullable|string|max:255',
                'projects' => 'nullable|array'
            ]);

            $payload = [
                'data' => [
                    'workspace' => $request->input('workspace'),
                    'name' => $request->input('name'),
                    'notes' => $request->input('notes'),
                    'due_on' => $request->input('due_on'),
                    'assignee' => $request->input('assignee'),
                    'projects' => $request->input('projects', [])
                ]
            ];

            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $request->input('access_token'),
                'Content-Type' => 'application/json'
            ])->post('https://app.asana.com/api/1.0/tasks', $payload);

            if ($response->successful()) {
                $task = $response->json();
                return $this->successResponse([
                    'task_id' => $task['data']['gid'],
                    'name' => $task['data']['name'],
                    'permalink_url' => $task['data']['permalink_url']
                ], 'Asana task created successfully');
            }

            return $this->errorResponse('Failed to create Asana task: ' . $response->body(), 400);

        } catch (\Exception $e) {
            Log::error('Asana task creation failed: ' . $e->getMessage());
            return $this->errorResponse('Task creation failed: ' . $e->getMessage(), 500);
        }
    }
}
