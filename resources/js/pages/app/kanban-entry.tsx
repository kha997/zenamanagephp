import React from 'react';
import { createRoot } from 'react-dom/client';
import TasksPage from './Tasks';

// Initialize React Kanban Board
const rootElement = document.getElementById('kanban-react-root');
if (rootElement) {
  const tasks = JSON.parse(rootElement.dataset.tasks ?? '[]');
  const filters = JSON.parse(rootElement.dataset.filters ?? '{}');
  
  const root = createRoot(rootElement);
  root.render(
    React.createElement(TasksPage, { 
      initialTasks: tasks,
      initialFilters: filters 
    })
  );
} else {
  console.error('Kanban root element not found');
}
