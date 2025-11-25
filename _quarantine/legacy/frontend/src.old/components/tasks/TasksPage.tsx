// frontend/src/components/tasks/TasksPage.tsx

import React from 'react';
import HeaderShell from '@/components/layout/HeaderShell';
import TopNavigator from '@/components/tasks/TopNavigator';
import TasksTable from '@/components/tasks/TasksTable';
// import TasksKanban from '@/components/tasks/TasksKanban'; // Uncomment when Kanban view is implemented
import { LayoutWrapper } from '@/shared/layout-wrapper';

const TasksPage: React.FC = () => {
  // TODO: Fetch view mode from local storage
  const viewMode = 'table'; // Default to table view

  return (
    <>
      <HeaderShell />
      <TopNavigator />
      <LayoutWrapper>
        {viewMode === 'table' ? <TasksTable /> : null /* <TasksKanban /> */}
        {/* {viewMode === 'kanban' ? <TasksKanban /> : null} */}
      </LayoutWrapper>
    </>
  );
};

export default TasksPage;
