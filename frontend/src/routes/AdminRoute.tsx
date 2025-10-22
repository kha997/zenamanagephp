import React from 'react';

interface AdminRouteProps {
  children: React.ReactNode;
}

const AdminRoute: React.FC<AdminRouteProps> = ({ children }) => {
  // For now, just render children
  // In production, this would check for admin permissions
  return <>{children}</>;
};

export default AdminRoute;