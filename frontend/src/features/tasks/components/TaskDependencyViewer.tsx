import React, { useMemo } from 'react';
import { Task } from '@/lib/types';
import { Badge } from '@/components/ui/Badge';
import { Button } from '@/components/ui/Button';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { AlertTriangle } from 'lucide-react';

interface TaskDependencyViewerProps {
  tasks: Task[];
  onUpdateDependencies: (taskId: string, dependencies: string[]) => void;
}

function detectCircularDependencies(tasks: Task[]): string[] {
  const taskMap = new Map(tasks.map((task) => [task.id, task]));
  const visited = new Set<string>();
  const recursionStack = new Set<string>();
  const circular = new Set<string>();

  const dfs = (taskId: string, path: string[] = []): boolean => {
    if (recursionStack.has(taskId)) {
      const cycleStart = path.indexOf(taskId);
      path.slice(cycleStart).forEach((id) => circular.add(id));
      circular.add(taskId);
      return true;
    }

    if (visited.has(taskId)) {
      return false;
    }

    visited.add(taskId);
    recursionStack.add(taskId);

    const task = taskMap.get(taskId);
    if (task) {
      for (const depId of task.dependencies) {
        if (taskMap.has(depId) && dfs(depId, [...path, taskId])) {
          circular.add(taskId);
        }
      }
    }

    recursionStack.delete(taskId);
    return false;
  };

  tasks.forEach((task) => {
    if (!visited.has(task.id)) {
      dfs(task.id);
    }
  });

  return Array.from(circular);
}

export const TaskDependencyViewer: React.FC<TaskDependencyViewerProps> = ({
  tasks,
  onUpdateDependencies,
}) => {
  const taskIds = useMemo(() => new Set(tasks.map((task) => task.id)), [tasks]);

  const circularDependencies = useMemo(
    () => detectCircularDependencies(tasks),
    [tasks]
  );

  const totalDependencies = useMemo(
    () => tasks.reduce((sum, task) => sum + task.dependencies.length, 0),
    [tasks]
  );

  if (tasks.length === 0) {
    return (
      <div className="h-full flex items-center justify-center text-sm text-gray-600">
        Chưa có công việc để hiển thị dependency.
      </div>
    );
  }

  return (
    <div className="h-full flex flex-col">
      <div className="p-4 border-b flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold">Task Dependencies</h2>
          <p className="text-sm text-gray-600">Danh sách phụ thuộc theo từng task.</p>
        </div>
        <Badge variant="secondary">{totalDependencies} dependencies</Badge>
      </div>

      {circularDependencies.length > 0 && (
        <Alert className="m-4 border-red-200 bg-red-50">
          <AlertTriangle className="h-4 w-4 text-red-600" />
          <AlertDescription className="text-red-800">
            Phát hiện phụ thuộc vòng tròn ở {circularDependencies.length} task.
          </AlertDescription>
        </Alert>
      )}

      <div className="flex-1 overflow-auto p-4 space-y-3">
        {tasks.map((task) => {
          const missingDependencies = task.dependencies.filter((depId) => !taskIds.has(depId));

          return (
            <div key={task.id} className="rounded-md border p-3 bg-white">
              <div className="flex items-center justify-between gap-2">
                <div className="font-medium text-sm text-gray-900">{task.name}</div>
                <Badge
                  className={
                    task.status === 'completed'
                      ? 'bg-green-100 text-green-800'
                      : task.status === 'in_progress'
                        ? 'bg-blue-100 text-blue-800'
                        : task.status === 'review'
                          ? 'bg-yellow-100 text-yellow-800'
                          : 'bg-gray-100 text-gray-800'
                  }
                >
                  {task.status}
                </Badge>
              </div>

              <div className="mt-2 text-xs text-gray-600">
                Dependencies: {task.dependencies.length}
              </div>

              <div className="mt-2 flex flex-wrap gap-2">
                {task.dependencies.length === 0 && (
                  <span className="text-xs text-gray-500">Không có phụ thuộc</span>
                )}
                {task.dependencies.map((depId) => (
                  <Badge
                    key={depId}
                    variant={taskIds.has(depId) ? 'outline' : 'destructive'}
                    className="text-xs"
                  >
                    {depId}
                  </Badge>
                ))}
              </div>

              {missingDependencies.length > 0 && (
                <div className="mt-3">
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() =>
                      onUpdateDependencies(
                        task.id,
                        task.dependencies.filter((depId) => taskIds.has(depId))
                      )
                    }
                  >
                    Remove invalid dependencies ({missingDependencies.length})
                  </Button>
                </div>
              )}
            </div>
          );
        })}
      </div>
    </div>
  );
};
