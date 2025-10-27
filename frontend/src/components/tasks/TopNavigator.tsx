// frontend/src/components/tasks/TopNavigator.tsx

import React from 'react';

const TopNavigator: React.FC = () => {
  const tabs = [
    { label: 'All', count: 0 },
    { label: 'My Tasks', count: 0 },
    { label: 'Assigned', count: 0 },
    { label: 'Overdue', count: 0 },
    { label: 'Completed', count: 0 },
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
