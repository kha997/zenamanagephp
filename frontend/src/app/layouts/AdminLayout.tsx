import React from 'react';
import { Outlet } from 'react-router-dom';

const AdminLayout: React.FC = () => {
  return (
    <div className="min-h-screen bg-[var(--color-surface-primary)]">
      <div className="flex">
        {/* Sidebar */}
        <div className="w-64 bg-[var(--color-surface-secondary)] border-r border-[var(--color-border-primary)]">
          <div className="p-6">
            <h1 className="text-xl font-bold text-[var(--color-text-primary)]">
              Admin Panel
            </h1>
          </div>
          <nav className="px-4 py-2">
            <ul className="space-y-2">
              <li>
                <a href="/admin/dashboard" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Dashboard
                </a>
              </li>
              <li>
                <a href="/admin/users" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Users
                </a>
              </li>
              <li>
                <a href="/admin/roles" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Roles
                </a>
              </li>
              <li>
                <a href="/admin/roles-permissions" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Roles & Permissions
                </a>
              </li>
              <li>
                <a href="/admin/audit-logs" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Audit Logs
                </a>
              </li>
              <li>
                <a href="/admin/permission-inspector" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Permission Inspector
                </a>
              </li>
              <li>
                <a href="/admin/cost-approval-policy" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Cost Policies
                </a>
              </li>
              <li>
                <a href="/admin/cost-governance" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Cost Governance
                </a>
              </li>
              <li>
                <a href="/admin/role-profiles" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Role Profiles
                </a>
              </li>
              <li>
                <a href="/admin/tenants" className="block px-3 py-2 text-[var(--color-text-primary)] hover:bg-[var(--color-surface-muted)] rounded">
                  Tenants
                </a>
              </li>
            </ul>
          </nav>
        </div>
        
        {/* Main Content */}
        <div className="flex-1">
          <Outlet />
        </div>
      </div>
    </div>
  );
};

export default AdminLayout;
