import React from 'react';
import { Layout } from '../../components/layout';
import { ProjectList } from '../../components/projects/ProjectList';

const ProjectsNext: React.FC = () => {
  return (
    <Layout>
      <div className="p-4">
        <div className="mb-6">
          <h1 className="text-2xl font-bold text-gray-900 mb-2">Projects (New Design)</h1>
          <p className="text-sm text-gray-600">Manage and track your projects</p>
        </div>
        <ProjectList />
      </div>
    </Layout>
  );
};

export default ProjectsNext;
