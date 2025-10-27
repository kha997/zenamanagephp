import React from 'react';
import { Link, useLocation } from 'react-router-dom';
import { cn } from '@/lib/utils';

interface NavItem {
  href: string;
  label: string;
  icon?: React.ComponentType<{ className?: string }>;
  requiredPermission?: string;
}

interface PrimaryNavProps {
  /** Navigation items to display */
  items: NavItem[];
  /** Additional CSS classes */
  className?: string;
}

/**
 * PrimaryNav - Standardized primary navigation component
 * 
 * Features:
 * - Active state styling based on current route
 * - Icon support for navigation items
 * - Accessibility attributes (aria-current)
 * - Responsive design
 */
export const PrimaryNav: React.FC<PrimaryNavProps> = ({ items, className }) => {
  const location = useLocation();

  return (
    <nav 
      className={cn("flex space-x-1", className)}
      aria-label="Primary navigation"
    >
      {items.map((item, index) => {
        const isActive = location.pathname === item.href || location.pathname.startsWith(`${item.href}/`);
        
        return (
          <Link
            key={index}
            to={item.href}
            className={cn(
              "inline-flex items-center px-3 py-2 text-sm font-medium rounded-md transition-colors",
              isActive
                ? "bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-200"
                : "text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-800",
              "focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            )}
            aria-current={isActive ? 'page' : undefined}
          >
            {item.icon && <item.icon className="mr-2 h-4 w-4" />}
            {item.label}
          </Link>
        );
      })}
    </nav>
  );
};

export default PrimaryNav;

