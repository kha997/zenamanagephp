import React from 'react';

export default function TestProjects() {
  return (
    <div className="min-h-screen bg-gray-50 p-8">
      <div className="max-w-7xl mx-auto">
        <h1 className="text-3xl font-bold text-gray-900 mb-8">
          Test Projects Page
        </h1>
        
        <div className="dashboard-card p-6">
          <h2 className="text-xl font-semibold mb-4">Dashboard Card Test</h2>
          <p className="text-gray-600">
            This is a test page to verify the dashboard styling is working.
          </p>
        </div>

        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mt-8">
          <div className="dashboard-card metric-card blue p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-white/80 text-sm">Test Metric</p>
                <p className="text-3xl font-bold text-white">5</p>
                <p className="text-white/80 text-sm">Test projects</p>
              </div>
              <i className="fas fa-project-diagram text-4xl text-white/60"></i>
            </div>
          </div>

          <div className="dashboard-card metric-card green p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-white/80 text-sm">Active</p>
                <p className="text-3xl font-bold text-white">3</p>
                <p className="text-white/80 text-sm">In progress</p>
              </div>
              <i className="fas fa-play text-4xl text-white/60"></i>
            </div>
          </div>

          <div className="dashboard-card metric-card orange p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-white/80 text-sm">On Hold</p>
                <p className="text-3xl font-bold text-white">1</p>
                <p className="text-white/80 text-sm">Paused</p>
              </div>
              <i className="fas fa-pause text-4xl text-white/60"></i>
            </div>
          </div>

          <div className="dashboard-card metric-card purple p-6">
            <div className="flex items-center justify-between">
              <div>
                <p className="text-white/80 text-sm">Completed</p>
                <p className="text-3xl font-bold text-white">1</p>
                <p className="text-white/80 text-sm">Done</p>
              </div>
              <i className="fas fa-check-circle text-4xl text-white/60"></i>
            </div>
          </div>
        </div>

        <div className="mt-8">
          <div className="dashboard-card p-6">
            <h3 className="text-lg font-semibold mb-4">Test Controls</h3>
            <div className="flex gap-4">
              <button className="zena-btn zena-btn-primary">
                <i className="fas fa-plus mr-2"></i>
                Test Button
              </button>
              <button className="zena-btn zena-btn-outline">
                <i className="fas fa-download mr-2"></i>
                Export
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
