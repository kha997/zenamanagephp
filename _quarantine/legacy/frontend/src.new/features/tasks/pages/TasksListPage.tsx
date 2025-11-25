import React from 'react';
import { useTasks } from '../hooks';

export const TasksListPage: React.FC = () => {
  const { data, isLoading, error } = useTasks();

  if (isLoading) {
    return <div>Loading tasks...</div>;
  }

  if (error) {
    return <div>Error loading tasks: {(error as Error).message}</div>;
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">Tasks</h1>
      <div className="space-y-4">
        {data?.data?.map((task) => (
          <div key={task.id} className="p-4 border rounded">
            <h2 className="font-semibold">{task.title}</h2>
            <p className="text-sm text-gray-600">{task.status}</p>
          </div>
        ))}
      </div>
    </div>
  );
};

export default TasksListPage;

