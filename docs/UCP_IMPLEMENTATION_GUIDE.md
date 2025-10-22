# UCP Implementation Guide

## Getting Started

This guide provides practical implementation steps for adopting UCP (Universal Component Protocol) in the ZenaManage system.

## Quick Start

### 1. Frontend Component Setup

```typescript
// Define component interface
interface TaskComponentProps {
  taskId: string;
  onUpdate: (task: Task) => void;
  onError: (error: UCPError) => void;
}

// Implement component with UCP compliance
const TaskComponent: React.FC<TaskComponentProps> = ({ taskId, onUpdate, onError }) => {
  const [task, setTask] = useState<Task | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTask = async () => {
      try {
        const response = await apiClient.get<Task>(`/api/tasks/${taskId}`);
        setTask(response.data);
      } catch (error) {
        onError(error as UCPError);
      } finally {
        setLoading(false);
      }
    };

    fetchTask();
  }, [taskId, onError]);

  if (loading) return <LoadingSpinner />;
  if (!task) return <EmptyState />;

  return <TaskDisplay task={task} onUpdate={onUpdate} />;
};
```

### 2. Backend Service Implementation

```php
<?php

namespace App\Services;

use App\Models\Task;
use App\Support\ApiResponse;
use Illuminate\Http\Request;

class TaskService
{
    public function getTask(string $taskId): ApiResponse
    {
        try {
            $task = Task::findOrFail($taskId);
            
            return ApiResponse::success($task);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'TASK_NOT_FOUND',
                'Task not found',
                ['task_id' => $taskId]
            );
        }
    }

    public function updateTask(string $taskId, array $data): ApiResponse
    {
        try {
            $task = Task::findOrFail($taskId);
            $task->update($data);
            
            return ApiResponse::success($task);
        } catch (\Exception $e) {
            return ApiResponse::error(
                'TASK_UPDATE_FAILED',
                'Failed to update task',
                ['task_id' => $taskId, 'data' => $data]
            );
        }
    }
}
```

### 3. API Controller Setup

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TaskService;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    public function __construct(
        private TaskService $taskService
    ) {}

    public function show(string $taskId)
    {
        $response = $this->taskService->getTask($taskId);
        return response()->json($response->toArray(), $response->getStatusCode());
    }

    public function update(Request $request, string $taskId)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:backlog,in_progress,blocked,done,cancelled',
            'priority' => 'required|in:low,normal,high,urgent',
        ]);

        $response = $this->taskService->updateTask($taskId, $validated);
        return response()->json($response->toArray(), $response->getStatusCode());
    }
}
```

## Best Practices

### Error Handling

```typescript
// Frontend error handling
const handleApiError = (error: UCPError) => {
  switch (error.code) {
    case 'TASK_NOT_FOUND':
      showNotification('Task not found', 'error');
      break;
    case 'VALIDATION_ERROR':
      showValidationErrors(error.details);
      break;
    default:
      showNotification('An unexpected error occurred', 'error');
  }
};
```

```php
// Backend error handling
class ApiResponse
{
    public static function error(string $code, string $message, array $details = []): self
    {
        return new self([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'details' => $details,
                'timestamp' => now()->toISOString(),
            ]
        ], 400);
    }
}
```

### Type Safety

```typescript
// Define strict interfaces
interface Task {
  id: string;
  title: string;
  description: string;
  status: TaskStatus;
  priority: TaskPriority;
  assigneeId?: string;
  projectId: string;
  createdAt: string;
  updatedAt: string;
}

type TaskStatus = 'backlog' | 'in_progress' | 'blocked' | 'done' | 'cancelled';
type TaskPriority = 'low' | 'normal' | 'high' | 'urgent';

// Use type guards
const isValidTask = (data: unknown): data is Task => {
  return typeof data === 'object' && 
         data !== null && 
         'id' in data && 
         'title' in data && 
         'status' in data;
};
```

### Testing

```typescript
// Component testing
describe('TaskComponent', () => {
  it('should display task information', async () => {
    const mockTask: Task = {
      id: 'task-1',
      title: 'Test Task',
      description: 'Test Description',
      status: 'in_progress',
      priority: 'normal',
      projectId: 'project-1',
      createdAt: '2025-01-01T00:00:00Z',
      updatedAt: '2025-01-01T00:00:00Z',
    };

    render(<TaskComponent taskId="task-1" onUpdate={jest.fn()} onError={jest.fn()} />);
    
    await waitFor(() => {
      expect(screen.getByText('Test Task')).toBeInTheDocument();
    });
  });
});
```

```php
// Service testing
class TaskServiceTest extends TestCase
{
    public function test_get_task_returns_success_response()
    {
        $task = Task::factory()->create();
        
        $response = $this->taskService->getTask($task->id);
        
        $this->assertTrue($response->isSuccess());
        $this->assertEquals($task->id, $response->getData()['id']);
    }

    public function test_get_task_returns_error_for_nonexistent_task()
    {
        $response = $this->taskService->getTask('nonexistent-id');
        
        $this->assertFalse($response->isSuccess());
        $this->assertEquals('TASK_NOT_FOUND', $response->getError()['code']);
    }
}
```

## Migration Guide

### From Legacy Components

1. **Identify Legacy Patterns**
   - Find components using inconsistent error handling
   - Locate components with tight coupling
   - Identify components missing type safety

2. **Create UCP Interfaces**
   - Define component contracts
   - Implement error handling standards
   - Add type safety

3. **Gradual Migration**
   - Start with new components
   - Migrate high-impact components first
   - Use feature flags for gradual rollout

### Example Migration

```typescript
// Before: Legacy component
const LegacyTaskComponent = ({ taskId }) => {
  const [task, setTask] = useState(null);
  
  useEffect(() => {
    fetch(`/api/tasks/${taskId}`)
      .then(res => res.json())
      .then(setTask)
      .catch(console.error);
  }, [taskId]);

  return <div>{task?.title}</div>;
};

// After: UCP-compliant component
const UCPTaskComponent: React.FC<TaskComponentProps> = ({ taskId, onUpdate, onError }) => {
  const [task, setTask] = useState<Task | null>(null);
  const [loading, setLoading] = useState(true);

  useEffect(() => {
    const fetchTask = async () => {
      try {
        const response = await apiClient.get<Task>(`/api/tasks/${taskId}`);
        setTask(response.data);
      } catch (error) {
        onError(error as UCPError);
      } finally {
        setLoading(false);
      }
    };

    fetchTask();
  }, [taskId, onError]);

  if (loading) return <LoadingSpinner />;
  if (!task) return <EmptyState />;

  return <TaskDisplay task={task} onUpdate={onUpdate} />;
};
```

## Performance Considerations

### Caching Strategy

```typescript
// Implement component-level caching
const useTaskCache = (taskId: string) => {
  const cacheKey = `task-${taskId}`;
  
  return useQuery({
    queryKey: [cacheKey],
    queryFn: () => apiClient.get<Task>(`/api/tasks/${taskId}`),
    staleTime: 5 * 60 * 1000, // 5 minutes
    cacheTime: 10 * 60 * 1000, // 10 minutes
  });
};
```

### Lazy Loading

```typescript
// Implement lazy loading for large components
const LazyTaskList = lazy(() => import('./TaskList'));

const TaskDashboard = () => {
  return (
    <Suspense fallback={<LoadingSpinner />}>
      <LazyTaskList />
    </Suspense>
  );
};
```

## Troubleshooting

### Common Issues

1. **Type Errors**
   - Ensure all interfaces are properly defined
   - Use type guards for runtime validation
   - Check TypeScript configuration

2. **Error Handling**
   - Verify error codes are consistent
   - Check error propagation paths
   - Test error recovery scenarios

3. **Performance Issues**
   - Monitor component re-renders
   - Check API response times
   - Verify caching effectiveness

### Debug Tools

```typescript
// Add debugging utilities
const debugUCP = (component: string, action: string, data: any) => {
  if (process.env.NODE_ENV === 'development') {
    console.log(`[UCP Debug] ${component}:${action}`, data);
  }
};

// Use in components
const TaskComponent = ({ taskId, onUpdate, onError }) => {
  useEffect(() => {
    debugUCP('TaskComponent', 'mount', { taskId });
  }, [taskId]);

  // ... component logic
};
```

---

*For more detailed information, refer to the main UCP documentation and COMPLETE_SYSTEM_DOCUMENTATION.md.*
