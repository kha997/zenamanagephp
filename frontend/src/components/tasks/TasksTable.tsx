import React from 'react';
import { useTasks } from '@/entities/tasks/hooks';
import { Skeleton } from '@/shared/ui/skeleton';

const TasksTable: React.FC = () => {
  const { data, isLoading, isError, error } = useTasks({ tenantId: 'tenant1' });

  if (isLoading) {
    return (
      <div>
        <Skeleton height={20} width={100} />
        <Skeleton height={20} width={150} />
        <Skeleton height={20} width={120} />
      </div>
    );
  }

  if (isError) {
    return <div>Error: {error.message}</div>;
  }

  const tasks = data?.data || [];

  return (
    <div className="overflow-x-auto">
      <table className="min-w-full divide-y divide-gray-200">
        <thead className="bg-gray-50">
          <tr>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Priority</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assignees</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Project</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Due</th>
            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Updated</th>
          </tr>
        </thead>
        <tbody className="bg-white divide-y divide-gray-200">
          {tasks.map((task) => (
            <tr key={task.id}>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.title}</td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.status}</td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.priority}</td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.assignees.join(', ')}</td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.project}</td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.due}</td>
              <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">{task.updated}</td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
};

export default TasksTable;
