import React from 'react';
import { useTasks } from '@/entities/tasks/hooks';

const TopNavigator: React.FC = () => {
  const { data: allTasksData } = useTasks({ tenantId: 'tenant1' });
  const allTasks = allTasksData?.data || [];

  const myTasksCount = allTasks.filter((task) => task.assignees.includes('user1')).length; // Replace 'user1' with actual user ID
  const assignedCount = allTasks.filter((task) => task.assignees.length > 0).length;
  const overdueCount = allTasks.filter((task) => new Date(task.dueDate) < new Date()).length;
  const completedCount = allTasks.filter((task) => task.status === 'Done').length;

  const tabs = [
    { label: 'All', count: allTasks.length },
    { label: 'My Tasks', count: myTasksCount },
    { label: 'Assigned', count: assignedCount },
    { label: 'Overdue', count: overdueCount },
    { label: 'Completed', count: completedCount },
  ];

  return (
    <div className="bg-white border-b">
      <div className="container mx-auto">
        <ul
          className="flex space-x-4 overflow-x-auto py-2"
          role="tablist"
          aria-label="Task filters"
        >
          {tabs.map((tab) => (
            <li key={tab.label} role="tab">
              <button className="px-4 py-2 rounded-full bg-gray-100 hover:bg-gray-200 focus:outline-none focus:ring-2 focus:ring-blue-500">
                {tab.label} ({tab.count})
              </button>
            </li>
          ))}
        </ul>
      </div>
    </div>
  );
};

export default TopNavigator;
