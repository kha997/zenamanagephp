<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SearchService
{
    /**
     * Perform intelligent search with fuzzy matching
     */
    public function search(string $query, string $context = 'all'): array
    {
        if (empty(trim($query))) {
            return [];
        }
        
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "search_{$tenantId}_" . md5($query . $context);
        
        return Cache::remember($cacheKey, 60, function () use ($query, $context, $tenantId) {
            return $this->performSearch($query, $context, $tenantId);
        });
    }
    
    /**
     * Perform the actual search
     */
    private function performSearch(string $query, string $context, string $tenantId): array
    {
        $isAdmin = Auth::user()->hasRole('super_admin');
        $results = [];
        
        // Search in different contexts
        switch ($context) {
            case 'projects':
                $results = array_merge($results, $this->searchProjects($query, $tenantId));
                break;
            case 'tasks':
                $results = array_merge($results, $this->searchTasks($query, $tenantId));
                break;
            case 'documents':
                $results = array_merge($results, $this->searchDocuments($query, $tenantId));
                break;
            case 'users':
                if ($isAdmin) {
                    $results = array_merge($results, $this->searchUsers($query));
                }
                break;
            case 'all':
            default:
                $results = array_merge($results, $this->searchProjects($query, $tenantId));
                $results = array_merge($results, $this->searchTasks($query, $tenantId));
                $results = array_merge($results, $this->searchDocuments($query, $tenantId));
                if ($isAdmin) {
                    $results = array_merge($results, $this->searchUsers($query));
                    $results = array_merge($results, $this->searchTenants($query));
                }
                break;
        }
        
        // Sort by relevance score
        usort($results, fn($a, $b) => $b['score'] <=> $a['score']);
        
        return array_slice($results, 0, 10); // Limit to 10 results
    }
    
    /**
     * Search projects
     */
    private function searchProjects(string $query, string $tenantId): array
    {
        $projects = $this->getMockProjects($tenantId);
        $results = [];
        
        foreach ($projects as $project) {
            $score = $this->calculateRelevanceScore($query, [
                $project['name'],
                $project['code'],
                $project['description']
            ]);
            
            if ($score > 0) {
                $results[] = [
                    'id' => $project['id'],
                    'title' => $project['name'],
                    'description' => $project['description'],
                    'url' => "/app/projects/{$project['id']}",
                    'icon' => 'fas fa-project-diagram',
                    'type' => 'project',
                    'score' => $score,
                    'metadata' => [
                        'code' => $project['code'],
                        'status' => $project['status'],
                        'progress' => $project['progress']
                    ]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Search tasks
     */
    private function searchTasks(string $query, string $tenantId): array
    {
        $tasks = $this->getMockTasks($tenantId);
        $results = [];
        
        foreach ($tasks as $task) {
            $score = $this->calculateRelevanceScore($query, [
                $task['title'],
                $task['description'],
                $task['project_name']
            ]);
            
            if ($score > 0) {
                $results[] = [
                    'id' => $task['id'],
                    'title' => $task['title'],
                    'description' => $task['description'],
                    'url' => "/app/tasks/{$task['id']}",
                    'icon' => 'fas fa-tasks',
                    'type' => 'task',
                    'score' => $score,
                    'metadata' => [
                        'project' => $task['project_name'],
                        'status' => $task['status'],
                        'priority' => $task['priority']
                    ]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Search documents
     */
    private function searchDocuments(string $query, string $tenantId): array
    {
        $documents = $this->getMockDocuments($tenantId);
        $results = [];
        
        foreach ($documents as $document) {
            $score = $this->calculateRelevanceScore($query, [
                $document['title'],
                $document['filename'],
                $document['description']
            ]);
            
            if ($score > 0) {
                $results[] = [
                    'id' => $document['id'],
                    'title' => $document['title'],
                    'description' => $document['filename'],
                    'url' => "/app/documents/{$document['id']}",
                    'icon' => 'fas fa-file-alt',
                    'type' => 'document',
                    'score' => $score,
                    'metadata' => [
                        'filename' => $document['filename'],
                        'type' => $document['type'],
                        'size' => $document['size']
                    ]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Search users (admin only)
     */
    private function searchUsers(string $query): array
    {
        $users = $this->getMockUsers();
        $results = [];
        
        foreach ($users as $user) {
            $score = $this->calculateRelevanceScore($query, [
                $user['name'],
                $user['email'],
                $user['role']
            ]);
            
            if ($score > 0) {
                $results[] = [
                    'id' => $user['id'],
                    'title' => $user['name'],
                    'description' => $user['email'],
                    'url' => "/admin/users/{$user['id']}",
                    'icon' => 'fas fa-user',
                    'type' => 'user',
                    'score' => $score,
                    'metadata' => [
                        'email' => $user['email'],
                        'role' => $user['role'],
                        'status' => $user['status']
                    ]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Search tenants (admin only)
     */
    private function searchTenants(string $query): array
    {
        $tenants = $this->getMockTenants();
        $results = [];
        
        foreach ($tenants as $tenant) {
            $score = $this->calculateRelevanceScore($query, [
                $tenant['name'],
                $tenant['domain'],
                $tenant['description']
            ]);
            
            if ($score > 0) {
                $results[] = [
                    'id' => $tenant['id'],
                    'title' => $tenant['name'],
                    'description' => $tenant['domain'],
                    'url' => "/admin/tenants/{$tenant['id']}",
                    'icon' => 'fas fa-building',
                    'type' => 'tenant',
                    'score' => $score,
                    'metadata' => [
                        'domain' => $tenant['domain'],
                        'plan' => $tenant['plan'],
                        'status' => $tenant['status']
                    ]
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Calculate relevance score using fuzzy matching
     */
    private function calculateRelevanceScore(string $query, array $fields): float
    {
        $maxScore = 0;
        $query = strtolower($query);
        
        foreach ($fields as $field) {
            if (empty($field)) continue;
            
            $field = strtolower($field);
            
            // Exact match gets highest score
            if ($field === $query) {
                $maxScore = max($maxScore, 100);
                continue;
            }
            
            // Starts with query gets high score
            if (str_starts_with($field, $query)) {
                $maxScore = max($maxScore, 90);
                continue;
            }
            
            // Contains query gets medium score
            if (str_contains($field, $query)) {
                $maxScore = max($maxScore, 70);
                continue;
            }
            
            // Fuzzy matching using Levenshtein distance
            $distance = levenshtein($query, $field);
            $maxLength = max(strlen($query), strlen($field));
            $similarity = (1 - $distance / $maxLength) * 100;
            
            if ($similarity > 60) {
                $maxScore = max($maxScore, $similarity);
            }
        }
        
        return $maxScore;
    }
    
    /**
     * Get recent searches for the user
     */
    public function getRecentSearches(): array
    {
        $userId = Auth::id();
        $cacheKey = "recent_searches_{$userId}";
        
        return Cache::get($cacheKey, []);
    }
    
    /**
     * Save search to recent searches
     */
    public function saveRecentSearch(string $query): void
    {
        $userId = Auth::id();
        $cacheKey = "recent_searches_{$userId}";
        
        $recentSearches = Cache::get($cacheKey, []);
        
        // Remove if already exists
        $recentSearches = array_filter($recentSearches, fn($search) => $search['query'] !== $query);
        
        // Add to beginning
        array_unshift($recentSearches, [
            'query' => $query,
            'timestamp' => now()->toISOString()
        ]);
        
        // Keep only last 10 searches
        $recentSearches = array_slice($recentSearches, 0, 10);
        
        Cache::put($cacheKey, $recentSearches, 3600); // 1 hour
    }
    
    /**
     * Get search suggestions based on query
     */
    public function getSuggestions(string $query): array
    {
        if (strlen($query) < 2) {
            return [];
        }
        
        $tenantId = Auth::user()->tenant_id ?? 'default';
        $cacheKey = "search_suggestions_{$tenantId}_" . md5($query);
        
        return Cache::remember($cacheKey, 300, function () use ($query, $tenantId) {
            return $this->generateSuggestions($query, $tenantId);
        });
    }
    
    /**
     * Generate search suggestions
     */
    private function generateSuggestions(string $query, string $tenantId): array
    {
        $suggestions = [];
        $query = strtolower($query);
        
        // Common search terms
        $commonTerms = [
            'project', 'task', 'document', 'team', 'user', 'report',
            'budget', 'schedule', 'deadline', 'status', 'priority'
        ];
        
        foreach ($commonTerms as $term) {
            if (str_contains($term, $query)) {
                $suggestions[] = [
                    'text' => $term,
                    'type' => 'common'
                ];
            }
        }
        
        // Project names
        $projects = $this->getMockProjects($tenantId);
        foreach ($projects as $project) {
            if (str_contains(strtolower($project['name']), $query)) {
                $suggestions[] = [
                    'text' => $project['name'],
                    'type' => 'project'
                ];
            }
        }
        
        return array_slice($suggestions, 0, 5);
    }
    
    // Mock data methods - these would be replaced with actual database queries
    
    private function getMockProjects(string $tenantId): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Website Redesign',
                'code' => 'WR-2024',
                'description' => 'Complete redesign of company website',
                'status' => 'active',
                'progress' => 75
            ],
            [
                'id' => 2,
                'name' => 'Mobile App Development',
                'code' => 'MAD-2024',
                'description' => 'iOS and Android mobile application',
                'status' => 'planning',
                'progress' => 25
            ],
            [
                'id' => 3,
                'name' => 'Database Migration',
                'code' => 'DBM-2024',
                'description' => 'Migrate to new database system',
                'status' => 'completed',
                'progress' => 100
            ]
        ];
    }
    
    private function getMockTasks(string $tenantId): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Design Homepage',
                'description' => 'Create new homepage design',
                'project_name' => 'Website Redesign',
                'status' => 'in_progress',
                'priority' => 'high'
            ],
            [
                'id' => 2,
                'title' => 'Setup Development Environment',
                'description' => 'Configure development tools',
                'project_name' => 'Mobile App Development',
                'status' => 'pending',
                'priority' => 'medium'
            ],
            [
                'id' => 3,
                'title' => 'Test Database Connection',
                'description' => 'Verify database connectivity',
                'project_name' => 'Database Migration',
                'status' => 'completed',
                'priority' => 'low'
            ]
        ];
    }
    
    private function getMockDocuments(string $tenantId): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Project Requirements',
                'filename' => 'requirements.pdf',
                'description' => 'Detailed project requirements document',
                'type' => 'pdf',
                'size' => '2.5 MB'
            ],
            [
                'id' => 2,
                'title' => 'Design Mockups',
                'filename' => 'mockups.zip',
                'description' => 'UI/UX design mockups',
                'type' => 'zip',
                'size' => '15.2 MB'
            ],
            [
                'id' => 3,
                'title' => 'Technical Specification',
                'filename' => 'tech-spec.docx',
                'description' => 'Technical implementation details',
                'type' => 'docx',
                'size' => '1.8 MB'
            ]
        ];
    }
    
    private function getMockUsers(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'role' => 'Project Manager',
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'role' => 'Developer',
                'status' => 'active'
            ],
            [
                'id' => 3,
                'name' => 'Mike Johnson',
                'email' => 'mike@example.com',
                'role' => 'Designer',
                'status' => 'inactive'
            ]
        ];
    }
    
    private function getMockTenants(): array
    {
        return [
            [
                'id' => 1,
                'name' => 'Acme Corporation',
                'domain' => 'acme.com',
                'description' => 'Software development company',
                'plan' => 'enterprise',
                'status' => 'active'
            ],
            [
                'id' => 2,
                'name' => 'TechStart Inc',
                'domain' => 'techstart.io',
                'description' => 'Startup technology company',
                'plan' => 'professional',
                'status' => 'active'
            ]
        ];
    }
}
