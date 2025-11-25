import { Link, useLocation } from 'react-router-dom';
import { useAuthContext } from '../contexts/AuthContext';

const ADMIN_ROLE_NAMES = new Set(['admin', 'super_admin', 'Admin', 'SuperAdmin']);

export default function Navbar() {
  const { user } = useAuthContext();
  const location = useLocation();

  // Check if user has admin role
  const hasAdminRole = user?.roles?.some((role) => {
    const roleName = typeof role === 'string' ? role : role?.name;
    return roleName && ADMIN_ROLE_NAMES.has(roleName);
  }) ?? false;

  // Helper function to check if route is active
  const isActive = (path: string) => {
    return location.pathname === path || location.pathname.startsWith(path + '/');
  };

  return (
    <nav>
      <ul>
        <li>
          <Link 
            to="/app/dashboard"
            className={isActive('/app/dashboard') ? 'active' : ''}
          >
            Dashboard
          </Link>
        </li>
        <li>
          <Link 
            to="/app/projects"
            className={isActive('/app/projects') ? 'active' : ''}
          >
            Projects
          </Link>
        </li>
        <li>
          <Link 
            to="/app/tasks"
            className={isActive('/app/tasks') ? 'active' : ''}
          >
            Tasks
          </Link>
        </li>
        <li>
          <Link 
            to="/app/documents"
            className={isActive('/app/documents') ? 'active' : ''}
          >
            Documents
          </Link>
        </li>
        <li>
          <Link 
            to="/app/team"
            className={isActive('/app/team') ? 'active' : ''}
          >
            Team
          </Link>
        </li>
        <li>
          <Link 
            to="/app/calendar"
            className={isActive('/app/calendar') ? 'active' : ''}
          >
            Calendar
          </Link>
        </li>
        <li>
          <Link 
            to="/app/alerts"
            className={isActive('/app/alerts') ? 'active' : ''}
          >
            Alerts
          </Link>
        </li>
        <li>
          <Link 
            to="/app/preferences"
            className={isActive('/app/preferences') ? 'active' : ''}
          >
            Preferences
          </Link>
        </li>
        <li>
          <Link 
            to="/app/settings"
            className={isActive('/app/settings') ? 'active' : ''}
          >
            Settings
          </Link>
        </li>
        {hasAdminRole && (
          <li>
            <Link 
              to="/admin/dashboard"
              className={isActive('/admin') ? 'active' : ''}
            >
              Admin
            </Link>
          </li>
        )}
      </ul>
    </nav>
  );
}

