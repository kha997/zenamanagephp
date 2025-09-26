<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ThirdPartyIntegrationService - Service cho third-party API integrations
 */
class ThirdPartyIntegrationService
{
    /**
     * Slack Integration
     */
    public function sendSlackNotification(string $webhookUrl, array $data): bool
    {
        try {
            $response = Http::post($webhookUrl, [
                'text' => $data['message'] ?? 'Project Update',
                'attachments' => [
                    [
                        'color' => $data['color'] ?? 'good',
                        'fields' => [
                            [
                                'title' => 'Project',
                                'value' => $data['project_name'] ?? 'Unknown',
                                'short' => true
                            ],
                            [
                                'title' => 'Status',
                                'value' => $data['status'] ?? 'Unknown',
                                'short' => true
                            ],
                            [
                                'title' => 'Progress',
                                'value' => ($data['progress'] ?? 0) . '%',
                                'short' => true
                            ],
                            [
                                'title' => 'Updated By',
                                'value' => $data['updated_by'] ?? 'System',
                                'short' => true
                            ]
                        ],
                        'footer' => 'ZenaManage',
                        'ts' => time()
                    ]
                ]
            ]);

            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Failed to send Slack notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return false;
        }
    }

    /**
     * Microsoft Teams Integration
     */
    public function sendTeamsNotification(string $webhookUrl, array $data): bool
    {
        try {
            $response = Http::post($webhookUrl, [
                '@type' => 'MessageCard',
                '@context' => 'https://schema.org/extensions',
                'summary' => $data['message'] ?? 'Project Update',
                'themeColor' => $data['color'] ?? '0078D4',
                'sections' => [
                    [
                        'activityTitle' => $data['project_name'] ?? 'Project Update',
                        'activitySubtitle' => $data['message'] ?? 'Project has been updated',
                        'facts' => [
                            [
                                'name' => 'Status',
                                'value' => $data['status'] ?? 'Unknown'
                            ],
                            [
                                'name' => 'Progress',
                                'value' => ($data['progress'] ?? 0) . '%'
                            ],
                            [
                                'name' => 'Updated By',
                                'value' => $data['updated_by'] ?? 'System'
                            ],
                            [
                                'name' => 'Timestamp',
                                'value' => now()->format('Y-m-d H:i:s')
                            ]
                        ]
                    ]
                ]
            ]);

            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Failed to send Teams notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return false;
        }
    }

    /**
     * Discord Integration
     */
    public function sendDiscordNotification(string $webhookUrl, array $data): bool
    {
        try {
            $response = Http::post($webhookUrl, [
                'content' => $data['message'] ?? 'Project Update',
                'embeds' => [
                    [
                        'title' => $data['project_name'] ?? 'Project Update',
                        'description' => $data['description'] ?? 'Project has been updated',
                        'color' => $this->hexToDecimal($data['color'] ?? '#00ff00'),
                        'fields' => [
                            [
                                'name' => 'Status',
                                'value' => $data['status'] ?? 'Unknown',
                                'inline' => true
                            ],
                            [
                                'name' => 'Progress',
                                'value' => ($data['progress'] ?? 0) . '%',
                                'inline' => true
                            ],
                            [
                                'name' => 'Updated By',
                                'value' => $data['updated_by'] ?? 'System',
                                'inline' => true
                            ]
                        ],
                        'footer' => [
                            'text' => 'ZenaManage'
                        ],
                        'timestamp' => now()->toISOString()
                    ]
                ]
            ]);

            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Failed to send Discord notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return false;
        }
    }

    /**
     * GitHub Integration
     */
    public function createGitHubIssue(string $token, string $repo, array $issueData): array
    {
        try {
            $response = Http::withToken($token)
                ->post("https://api.github.com/repos/{$repo}/issues", [
                    'title' => $issueData['title'],
                    'body' => $issueData['body'],
                    'labels' => $issueData['labels'] ?? [],
                    'assignees' => $issueData['assignees'] ?? []
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'issue' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create GitHub issue', [
                'error' => $e->getMessage(),
                'repo' => $repo,
                'issue_data' => $issueData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Jira Integration
     */
    public function createJiraIssue(string $baseUrl, string $username, string $apiToken, array $issueData): array
    {
        try {
            $response = Http::withBasicAuth($username, $apiToken)
                ->post("{$baseUrl}/rest/api/3/issue", [
                    'fields' => [
                        'project' => [
                            'key' => $issueData['project_key']
                        ],
                        'summary' => $issueData['summary'],
                        'description' => [
                            'type' => 'doc',
                            'version' => 1,
                            'content' => [
                                [
                                    'type' => 'paragraph',
                                    'content' => [
                                        [
                                            'type' => 'text',
                                            'text' => $issueData['description']
                                        ]
                                    ]
                                ]
                            ]
                        ],
                        'issuetype' => [
                            'name' => $issueData['issue_type'] ?? 'Task'
                        ],
                        'priority' => [
                            'name' => $issueData['priority'] ?? 'Medium'
                        ]
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'issue' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create Jira issue', [
                'error' => $e->getMessage(),
                'base_url' => $baseUrl,
                'issue_data' => $issueData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Trello Integration
     */
    public function createTrelloCard(string $apiKey, string $token, string $listId, array $cardData): array
    {
        try {
            $response = Http::post("https://api.trello.com/1/cards", [
                'key' => $apiKey,
                'token' => $token,
                'idList' => $listId,
                'name' => $cardData['name'],
                'desc' => $cardData['description'] ?? '',
                'due' => $cardData['due_date'] ?? null,
                'labels' => $cardData['labels'] ?? []
            ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'card' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create Trello card', [
                'error' => $e->getMessage(),
                'list_id' => $listId,
                'card_data' => $cardData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Asana Integration
     */
    public function createAsanaTask(string $accessToken, string $projectId, array $taskData): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->post('https://app.asana.com/api/1.0/tasks', [
                    'data' => [
                        'name' => $taskData['name'],
                        'notes' => $taskData['notes'] ?? '',
                        'projects' => [$projectId],
                        'due_on' => $taskData['due_date'] ?? null,
                        'assignee' => $taskData['assignee'] ?? null
                    ]
                ]);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'task' => $response->json()
                ];
            }

            return [
                'success' => false,
                'error' => $response->body()
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to create Asana task', [
                'error' => $e->getMessage(),
                'project_id' => $projectId,
                'task_data' => $taskData
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Zapier Integration
     */
    public function triggerZapierWebhook(string $webhookUrl, array $data): bool
    {
        try {
            $response = Http::post($webhookUrl, $data);
            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Failed to trigger Zapier webhook', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            
            return false;
        }
    }

    /**
     * Webhook Integration
     */
    public function sendWebhook(string $url, array $data, array $headers = []): bool
    {
        try {
            $response = Http::withHeaders($headers)->post($url, $data);
            return $response->successful();
            
        } catch (\Exception $e) {
            Log::error('Failed to send webhook', [
                'error' => $e->getMessage(),
                'url' => $url,
                'data' => $data
            ]);
            
            return false;
        }
    }

    /**
     * Email Integration (SendGrid, Mailgun, etc.)
     */
    public function sendEmailViaProvider(string $provider, array $config, array $emailData): bool
    {
        try {
            switch ($provider) {
                case 'sendgrid':
                    return $this->sendViaSendGrid($config, $emailData);
                case 'mailgun':
                    return $this->sendViaMailgun($config, $emailData);
                case 'ses':
                    return $this->sendViaSES($config, $emailData);
                default:
                    return false;
            }
            
        } catch (\Exception $e) {
            Log::error('Failed to send email via provider', [
                'error' => $e->getMessage(),
                'provider' => $provider,
                'email_data' => $emailData
            ]);
            
            return false;
        }
    }

    /**
     * Send via SendGrid
     */
    private function sendViaSendGrid(array $config, array $emailData): bool
    {
        $response = Http::withToken($config['api_key'])
            ->post('https://api.sendgrid.com/v3/mail/send', [
                'personalizations' => [
                    [
                        'to' => [
                            ['email' => $emailData['to']]
                        ],
                        'subject' => $emailData['subject']
                    ]
                ],
                'from' => [
                    'email' => $emailData['from']
                ],
                'content' => [
                    [
                        'type' => 'text/html',
                        'value' => $emailData['body']
                    ]
                ]
            ]);

        return $response->successful();
    }

    /**
     * Send via Mailgun
     */
    private function sendViaMailgun(array $config, array $emailData): bool
    {
        $response = Http::withBasicAuth('api', $config['api_key'])
            ->post("https://api.mailgun.net/v3/{$config['domain']}/messages", [
                'from' => $emailData['from'],
                'to' => $emailData['to'],
                'subject' => $emailData['subject'],
                'html' => $emailData['body']
            ]);

        return $response->successful();
    }

    /**
     * Send via AWS SES
     */
    private function sendViaSES(array $config, array $emailData): bool
    {
        // This would require AWS SDK implementation
        // For now, return false as placeholder
        return false;
    }

    /**
     * Get weather data
     */
    public function getWeatherData(string $apiKey, string $city): array
    {
        try {
            $cacheKey = "weather_{$city}_" . now()->format('Y-m-d-H');
            
            return Cache::remember($cacheKey, 3600, function () 

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }

                return [
                    'success' => false,
                    'error' => $response->body()
                ];
            });
            
        } catch (\Exception $e) {
            Log::error('Failed to get weather data', [
                'error' => $e->getMessage(),
                'city' => $city
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get currency exchange rates
     */
    public function getCurrencyRates(string $apiKey, string $baseCurrency = 'USD'): array
    {
        try {
            $cacheKey = "currency_rates_{$baseCurrency}_" . now()->format('Y-m-d');
            
            return Cache::remember($cacheKey, 86400, function () 

                if ($response->successful()) {
                    return [
                        'success' => true,
                        'data' => $response->json()
                    ];
                }

                return [
                    'success' => false,
                    'error' => $response->body()
                ];
            });
            
        } catch (\Exception $e) {
            Log::error('Failed to get currency rates', [
                'error' => $e->getMessage(),
                'base_currency' => $baseCurrency
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Convert hex color to decimal
     */
    private function hexToDecimal(string $hex): int
    {
        return hexdec(ltrim($hex, '#'));
    }

    /**
     * Test webhook connectivity
     */
    public function testWebhook(string $url, array $headers = []): array
    {
        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($url, [
                    'test' => true,
                    'timestamp' => now()->toISOString(),
                    'message' => 'Test webhook from ZenaManage'
                ]);

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'response_time' => $response->transferStats?->getTransferTime() ?? 0,
                'error' => $response->successful() ? null : $response->body()
            ];
            
        } catch (\Exception $e) {
            return [
                'success' => false,
                'status_code' => 0,
                'response_time' => 0,
                'error' => $e->getMessage()
            ];
        }
    }
}