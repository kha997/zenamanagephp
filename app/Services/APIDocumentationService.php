<?php declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use ReflectionClass;
use ReflectionMethod;

/**
 * APIDocumentationService - Service cho comprehensive API documentation
 */
class APIDocumentationService
{
    private array $docConfig;

    public function __construct()
    {
        $this->docConfig = [
            'enabled' => config('api_docs.enabled', true),
            'version' => config('api_docs.version', '1.0'),
            'title' => config('api_docs.title', 'ZenaManage API'),
            'description' => config('api_docs.description', 'Comprehensive API documentation for ZenaManage'),
            'base_url' => config('api_docs.base_url', 'http://localhost:8000/api'),
            'cache_ttl' => config('api_docs.cache_ttl', 3600),
            'include_examples' => config('api_docs.include_examples', true),
            'include_schemas' => config('api_docs.include_schemas', true),
            'authentication' => [
                'type' => 'bearer',
                'scheme' => 'Bearer',
                'bearerFormat' => 'JWT'
            ]
        ];
    }

    /**
     * Generate complete API documentation
     */
    public function generateDocumentation(): array
    {
        $cacheKey = 'api_documentation_' . $this->docConfig['version'];
        
        return Cache::remember($cacheKey, $this->docConfig['cache_ttl'], function () {
            return [
                'openapi' => '3.0.0',
                'info' => [
                    'title' => $this->docConfig['title'],
                    'version' => $this->docConfig['version'],
                    'description' => $this->docConfig['description'],
                    'contact' => [
                        'name' => 'ZenaManage Support',
                        'email' => 'support@zenamanage.com'
                    ]
                ],
                'servers' => [
                    [
                        'url' => $this->docConfig['base_url'],
                        'description' => 'Development server'
                    ]
                ],
                'security' => [
                    [
                        'bearerAuth' => []
                    ]
                ],
                'paths' => $this->generatePaths(),
                'components' => [
                    'securitySchemes' => [
                        'bearerAuth' => $this->docConfig['authentication']
                    ],
                    'schemas' => $this->generateSchemas(),
                    'responses' => $this->generateResponses()
                ],
                'tags' => $this->generateTags()
            ];
        });
    }

    /**
     * Get API endpoints by category
     */
    public function getEndpointsByCategory(): array
    {
        $cacheKey = 'api_endpoints_by_category';
        
        return Cache::remember($cacheKey, $this->docConfig['cache_ttl'], function () {
            $routes = $this->getApiRoutes();
            $categories = [];
            
            foreach ($routes as $route) {
                $category = $this->getRouteCategory($route);
                if (!isset($categories[$category])) {
                    $categories[$category] = [];
                }
                $categories[$category][] = $route;
            }
            
            return $categories;
        });
    }

    /**
     * Get endpoint documentation
     */
    public function getEndpointDocumentation(string $method, string $path): array
    {
        try {
            $route = $this->findRoute($method, $path);
            
            if (!$route) {
                return ['error' => 'Route not found'];
            }
            
            return [
                'method' => $method,
                'path' => $path,
                'summary' => $this->getRouteSummary($route),
                'description' => $this->getRouteDescription($route),
                'parameters' => $this->getRouteParameters($route),
                'request_body' => $this->getRequestBody($route),
                'responses' => $this->getRouteResponses($route),
                'examples' => $this->getRouteExamples($route),
                'authentication' => $this->getRouteAuthentication($route)
            ];
            
        } catch (\Exception $e) {
            Log::error('Failed to get endpoint documentation', [
                'method' => $method,
                'path' => $path,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get API statistics
     */
    public function getAPIStatistics(): array
    {
        $cacheKey = 'api_statistics';
        
        return Cache::remember($cacheKey, $this->docConfig['cache_ttl'], function () {
            $routes = $this->getApiRoutes();
            
            $stats = [
                'total_endpoints' => count($routes),
                'methods' => [],
                'categories' => [],
                'authentication_required' => 0,
                'public_endpoints' => 0
            ];
            
            foreach ($routes as $route) {
                // Count methods
                $method = $route['method'];
                $stats['methods'][$method] = ($stats['methods'][$method] ?? 0) + 1;
                
                // Count categories
                $category = $this->getRouteCategory($route);
                $stats['categories'][$category] = ($stats['categories'][$category] ?? 0) + 1;
                
                // Count authentication
                if ($this->requiresAuthentication($route)) {
                    $stats['authentication_required']++;
                } else {
                    $stats['public_endpoints']++;
                }
            }
            
            return $stats;
        });
    }

    /**
     * Generate OpenAPI specification
     */
    public function generateOpenAPISpec(): string
    {
        $documentation = $this->generateDocumentation();
        return json_encode($documentation, JSON_PRETTY_PRINT);
    }

    /**
     * Generate Postman collection
     */
    public function generatePostmanCollection(): array
    {
        $cacheKey = 'postman_collection';
        
        return Cache::remember($cacheKey, $this->docConfig['cache_ttl'], function () {
            $routes = $this->getApiRoutes();
            
            $collection = [
                'info' => [
                    'name' => $this->docConfig['title'],
                    'description' => $this->docConfig['description'],
                    'version' => $this->docConfig['version']
                ],
                'auth' => [
                    'type' => 'bearer',
                    'bearer' => [
                        [
                            'key' => 'token',
                            'value' => '{{auth_token}}',
                            'type' => 'string'
                        ]
                    ]
                ],
                'item' => []
            ];
            
            $folders = [];
            foreach ($routes as $route) {
                $category = $this->getRouteCategory($route);
                
                if (!isset($folders[$category])) {
                    $folders[$category] = [
                        'name' => $category,
                        'item' => []
                    ];
                }
                
                $folders[$category]['item'][] = [
                    'name' => $this->getRouteSummary($route),
                    'request' => [
                        'method' => $route['method'],
                        'header' => [
                            [
                                'key' => 'Content-Type',
                                'value' => 'application/json',
                                'type' => 'text'
                            ]
                        ],
                        'url' => [
                            'raw' => $this->docConfig['base_url'] . $route['uri'],
                            'host' => [parse_url($this->docConfig['base_url'], PHP_URL_HOST)],
                            'path' => explode('/', trim(parse_url($this->docConfig['base_url'], PHP_URL_PATH) . $route['uri'], '/'))
                        ]
                    ]
                ];
            }
            
            $collection['item'] = array_values($folders);
            
            return $collection;
        });
    }

    /**
     * Helper Methods
     */
    private function generatePaths(): array
    {
        $routes = $this->getApiRoutes();
        $paths = [];
        
        foreach ($routes as $route) {
            $path = $route['uri'];
            $method = strtolower($route['method']);
            
            if (!isset($paths[$path])) {
                $paths[$path] = [];
            }
            
            $paths[$path][$method] = [
                'summary' => $this->getRouteSummary($route),
                'description' => $this->getRouteDescription($route),
                'parameters' => $this->getRouteParameters($route),
                'requestBody' => $this->getRequestBody($route),
                'responses' => $this->getRouteResponses($route),
                'security' => $this->requiresAuthentication($route) ? [['bearerAuth' => []]] : []
            ];
        }
        
        return $paths;
    }

    private function generateSchemas(): array
    {
        return [
            'Project' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['draft', 'active', 'completed', 'cancelled']],
                    'progress' => ['type' => 'number', 'format' => 'float'],
                    'start_date' => ['type' => 'string', 'format' => 'date'],
                    'end_date' => ['type' => 'string', 'format' => 'date']
                ]
            ],
            'Task' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'completed', 'cancelled']],
                    'priority' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'urgent']],
                    'due_date' => ['type' => 'string', 'format' => 'date-time']
                ]
            ],
            'User' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'email' => ['type' => 'string', 'format' => 'email'],
                    'role' => ['type' => 'string'],
                    'is_active' => ['type' => 'boolean']
                ]
            ]
        ];
    }

    private function generateResponses(): array
    {
        return [
            'Success' => [
                'description' => 'Successful response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'success' => ['type' => 'boolean'],
                                'data' => ['type' => 'object'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            'Error' => [
                'description' => 'Error response',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'success' => ['type' => 'boolean'],
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ],
            'Unauthorized' => [
                'description' => 'Unauthorized',
                'content' => [
                    'application/json' => [
                        'schema' => [
                            'type' => 'object',
                            'properties' => [
                                'error' => ['type' => 'string'],
                                'message' => ['type' => 'string']
                            ]
                        ]
                    ]
                ]
            ]
        ];
    }

    private function generateTags(): array
    {
        return [
            ['name' => 'Authentication', 'description' => 'Authentication endpoints'],
            ['name' => 'Projects', 'description' => 'Project management endpoints'],
            ['name' => 'Tasks', 'description' => 'Task management endpoints'],
            ['name' => 'Users', 'description' => 'User management endpoints'],
            ['name' => 'Reports', 'description' => 'Reporting endpoints'],
            ['name' => 'Mobile', 'description' => 'Mobile API endpoints'],
            ['name' => 'Dashboard', 'description' => 'Dashboard endpoints'],
            ['name' => 'Integrations', 'description' => 'Integration endpoints'],
            ['name' => 'Performance', 'description' => 'Performance optimization endpoints']
        ];
    }

    private function getApiRoutes(): array
    {
        $routes = [];
        $routeCollection = Route::getRoutes();
        
        foreach ($routeCollection as $route) {
            if (str_starts_with($route->uri(), 'api/')) {
                $routes[] = [
                    'method' => $route->methods()[0],
                    'uri' => '/' . $route->uri(),
                    'name' => $route->getName(),
                    'action' => $route->getActionName(),
                    'middleware' => $route->middleware()
                ];
            }
        }
        
        return $routes;
    }

    private function getRouteCategory(array $route): string
    {
        $uri = $route['uri'];
        
        if (str_contains($uri, '/auth')) return 'Authentication';
        if (str_contains($uri, '/projects')) return 'Projects';
        if (str_contains($uri, '/tasks')) return 'Tasks';
        if (str_contains($uri, '/users')) return 'Users';
        if (str_contains($uri, '/reports')) return 'Reports';
        if (str_contains($uri, '/mobile')) return 'Mobile';
        if (str_contains($uri, '/dashboard')) return 'Dashboard';
        if (str_contains($uri, '/integrations')) return 'Integrations';
        if (str_contains($uri, '/performance')) return 'Performance';
        
        return 'General';
    }

    private function findRoute(string $method, string $path): ?array
    {
        $routes = $this->getApiRoutes();
        
        foreach ($routes as $route) {
            if (strtolower($route['method']) === strtolower($method) && $route['uri'] === $path) {
                return $route;
            }
        }
        
        return null;
    }

    private function getRouteSummary(array $route): string
    {
        $uri = $route['uri'];
        $method = $route['method'];
        
        return match(true) {
            str_contains($uri, '/projects') && $method === 'GET' => 'Get projects',
            str_contains($uri, '/projects') && $method === 'POST' => 'Create project',
            str_contains($uri, '/tasks') && $method === 'GET' => 'Get tasks',
            str_contains($uri, '/tasks') && $method === 'POST' => 'Create task',
            str_contains($uri, '/users') && $method === 'GET' => 'Get users',
            str_contains($uri, '/users') && $method === 'POST' => 'Create user',
            default => ucfirst($method) . ' ' . $uri
        };
    }

    private function getRouteDescription(array $route): string
    {
        return 'API endpoint for ' . $this->getRouteSummary($route);
    }

    private function getRouteParameters(array $route): array
    {
        $parameters = [];
        $uri = $route['uri'];
        
        // Extract path parameters
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        foreach ($matches[1] as $param) {
            $parameters[] = [
                'name' => $param,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string']
            ];
        }
        
        return $parameters;
    }

    private function getRequestBody(array $route): ?array
    {
        if (!in_array($route['method'], ['POST', 'PUT', 'PATCH'])) {
            return null;
        }
        
        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => ['type' => 'object']
                ]
            ]
        ];
    }

    private function getRouteResponses(array $route): array
    {
        return [
            '200' => ['$ref' => '#/components/responses/Success'],
            '400' => ['$ref' => '#/components/responses/Error'],
            '401' => ['$ref' => '#/components/responses/Unauthorized'],
            '500' => ['$ref' => '#/components/responses/Error']
        ];
    }

    private function getRouteExamples(array $route): array
    {
        if (!$this->docConfig['include_examples']) {
            return [];
        }
        
        return [
            'request' => [
                'method' => $route['method'],
                'url' => $this->docConfig['base_url'] . $route['uri'],
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => 'Bearer {token}'
                ]
            ],
            'response' => [
                'status' => 200,
                'body' => [
                    'success' => true,
                    'data' => [],
                    'message' => 'Success'
                ]
            ]
        ];
    }

    private function getRouteAuthentication(array $route): array
    {
        return $this->requiresAuthentication($route) ? [
            'type' => 'bearer',
            'required' => true
        ] : [
            'type' => 'none',
            'required' => false
        ];
    }

    private function requiresAuthentication(array $route): bool
    {
        $middleware = $route['middleware'] ?? [];
        return in_array('auth', $middleware) || in_array('auth:api', $middleware);
    }
}
