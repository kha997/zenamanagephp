import React from 'react';
import { useNavigate } from 'react-router-dom';
import { Card, CardHeader, CardTitle, CardContent, CardDescription } from '../../shared/ui/card';
import { Button } from '../../shared/ui/button';
import { useI18n } from '../../app/i18n-context';

export interface QuickAction {
  id: string;
  label: string;
  icon?: React.ReactNode;
  onClick: () => void;
  variant?: 'primary' | 'secondary' | 'outline' | 'ghost';
}

export interface QuickActionsCardProps {
  /** Quick actions to display */
  actions?: QuickAction[];
  /** Optional className */
  className?: string;
  /** Optional onAction handler */
  onAction?: (actionId: string) => void;
}

/**
 * QuickActionsCard - Dashboard component for quick action buttons
 * 
 * Displays commonly used actions:
 * - New Project
 * - New Task
 * - Add Member
 * - Reports
 * 
 * Features:
 * - Grid layout (2x2)
 * - Customizable actions
 * - Accessibility support
 */
export const QuickActionsCard: React.FC<QuickActionsCardProps> = ({
  actions,
  className,
  onAction,
}) => {
  const { t } = useI18n();
  const navigate = useNavigate();

  const defaultActions: QuickAction[] = [
    {
      id: 'newProject',
      label: t('dashboard.newProject', { defaultValue: 'New Project' }),
      icon: '📊',
      onClick: () => {
        navigate('/app/projects/create');
        onAction?.('newProject');
      },
      variant: 'outline',
    },
    {
      id: 'newTask',
      label: t('dashboard.newTask', { defaultValue: 'New Task' }),
      icon: '✅',
      onClick: () => {
        navigate('/app/tasks/create');
        onAction?.('newTask');
      },
      variant: 'outline',
    },
    {
      id: 'addMember',
      label: t('dashboard.addMember', { defaultValue: 'Add Member' }),
      icon: '👥',
      onClick: () => {
        navigate('/app/users');
        onAction?.('addMember');
      },
      variant: 'outline',
    },
    {
      id: 'reports',
      label: t('dashboard.reports', { defaultValue: 'Reports' }),
      icon: '📈',
      onClick: () => {
        navigate('/app/reports');
        onAction?.('reports');
      },
      variant: 'outline',
    },
  ];

  const actionsToDisplay = actions || defaultActions;

  return (
    <Card
      role="region"
      aria-label="Quick actions"
      className={className}
    >
      <CardHeader>
        <CardTitle>{t('dashboard.quickActions', { defaultValue: 'Quick Actions' })}</CardTitle>
        <CardDescription>
          {t('dashboard.quickActionsDescription', { defaultValue: 'Common tasks and shortcuts' })}
        </CardDescription>
      </CardHeader>

      <CardContent>
        <div className="grid grid-cols-2 gap-3" role="group" aria-label="Quick action buttons">
          {actionsToDisplay.map((action) => (
            <Button
              key={action.id}
              variant={action.variant || 'outline'}
              className="h-20 flex-col gap-2"
              onClick={action.onClick}
              aria-label={action.label}
            >
              {action.icon && <span className="text-lg" aria-hidden="true">{action.icon}</span>}
              <span className="text-sm">{action.label}</span>
            </Button>
          ))}
        </div>
      </CardContent>
    </Card>
  );
};

export default QuickActionsCard;

