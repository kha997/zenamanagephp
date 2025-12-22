import React, { useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { TasksListPage } from './TasksListPage';

/**
 * TasksKanbanPage
 * 
 * Dedicated Kanban view for tasks.
 * This page ensures the TasksListPage displays in Kanban mode.
 * 
 * Route: /app/tasks/kanban
 */
export const TasksKanbanPage: React.FC = () => {
  // Set view mode to kanban in localStorage before rendering
  useEffect(() => {
    localStorage.setItem('tasks_view_mode', 'kanban');
  }, []);

  // TasksListPage reads viewMode from localStorage
  // By setting it to 'kanban' here, the page will render in Kanban mode
  return <TasksListPage />;
};

export default TasksKanbanPage;

