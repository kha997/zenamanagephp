import React from 'react';
import { useParams } from 'react-router-dom';
import { useTask } from '../hooks';

export const TaskDetailPage: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const { data, isLoading, error } = useTask(id!);

  if (isLoading) {
    return <div>Loading task...</div>;
  }

  if (error) {
    return <div>Error loading task: {(error as Error).message}</div>;
  }

  return (
    <div>
      <h1 className="text-2xl font-bold mb-4">{data?.data?.title}</h1>
      <p>{data?.data?.description}</p>
    </div>
  );
};

export default TaskDetailPage;

