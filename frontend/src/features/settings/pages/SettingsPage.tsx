import React, { useState } from 'react';
import { Container } from '../../../components/ui/layout/Container';
import { AccessRestricted } from '../../../components/shared/AccessRestricted';
import { GeneralSettings } from '../components/GeneralSettings';
import { NotificationSettings } from '../components/NotificationSettings';
import { AppearanceSettings } from '../components/AppearanceSettings';
import { SecuritySettings } from '../components/SecuritySettings';
import { PrivacySettings } from '../components/PrivacySettings';
import { IntegrationSettings } from '../components/IntegrationSettings';
import { useSettings } from '../hooks';
import { useAuthStore } from '../../auth/store';

type SettingsTab = 'general' | 'notifications' | 'appearance' | 'security' | 'privacy' | 'integrations';

/**
 * SettingsPage - User and application settings page
 * 
 * Main settings page with tabbed interface for different settings categories
 */
export const SettingsPage: React.FC = () => {
  const { data: settingsData, isLoading, error } = useSettings();
  const [activeTab, setActiveTab] = useState<SettingsTab>('general');
  const { hasTenantPermission } = useAuthStore();
  const canViewSettings = hasTenantPermission('tenant.view_settings') || hasTenantPermission('tenant.manage_settings');
  const canManageSettings = hasTenantPermission('tenant.manage_settings');
  const isReadOnly = canViewSettings && !canManageSettings;

  // Early return if user doesn't have view permission
  if (!canViewSettings) {
    return (
      <Container>
        <AccessRestricted
          title="Access Restricted"
          description="You don't have permission to view this tenant's settings. Please contact an administrator to request access."
        />
      </Container>
    );
  }

  const tabs: Array<{ id: SettingsTab; label: string; icon?: string }> = [
    { id: 'general', label: 'General', icon: '⚙️' },
    { id: 'notifications', label: 'Notifications', icon: '🔔' },
    { id: 'appearance', label: 'Appearance', icon: '🎨' },
    { id: 'security', label: 'Security', icon: '🔒' },
    { id: 'privacy', label: 'Privacy', icon: '👤' },
    { id: 'integrations', label: 'Integrations', icon: '🔗' },
  ];

  const renderTabContent = () => {
    switch (activeTab) {
      case 'general':
        return <GeneralSettings />;
      case 'notifications':
        return <NotificationSettings />;
      case 'appearance':
        return <AppearanceSettings />;
      case 'security':
        return <SecuritySettings />;
      case 'privacy':
        return <PrivacySettings />;
      case 'integrations':
        return <IntegrationSettings />;
      default:
        return <GeneralSettings />;
    }
  };

  return (
    <Container>
      <div className="space-y-6">
        {/* Page Header */}
        <div className="flex items-center justify-between">
          <div>
            <div className="flex items-center gap-2">
              <h1 className="text-[var(--font-heading-3-size)] font-semibold text-[var(--text)]">
                Settings
              </h1>
              {isReadOnly && (
                <span className="px-2 py-1 text-xs font-medium rounded bg-[var(--muted-surface)] text-[var(--muted)]">
                  Read-only mode
                </span>
              )}
            </div>
            <p className="text-[var(--font-body-size)] text-[var(--muted)] mt-1">
              Manage your account settings and preferences
            </p>
          </div>
        </div>

        {/* Error State */}
        {error && (
          <div
            className="p-4 rounded-lg border border-red-200 bg-red-50"
            style={{
              borderColor: 'var(--gray-400)',
              backgroundColor: 'var(--muted-surface)',
            }}
          >
            <p className="text-sm text-[var(--text)]">
              Failed to load settings: {(error as Error).message}
            </p>
          </div>
        )}

        {/* Loading State */}
        {isLoading && !settingsData && (
          <div className="space-y-4">
            {[1, 2, 3].map((i) => (
              <div key={i} className="animate-pulse">
                <div className="h-32 bg-[var(--muted-surface)] rounded-lg"></div>
              </div>
            ))}
          </div>
        )}

        {/* Settings Content */}
        {!isLoading && (
          <div className="flex flex-col lg:flex-row gap-6">
            {/* Tabs Navigation - Vertical on desktop, horizontal on mobile */}
            <div className="lg:w-64 flex-shrink-0">
              <nav
                className="flex lg:flex-col gap-1 overflow-x-auto lg:overflow-x-visible"
                aria-label="Settings navigation"
              >
                {tabs.map((tab) => {
                  const isActive = activeTab === tab.id;
                  return (
                    <button
                      key={tab.id}
                      type="button"
                      onClick={() => setActiveTab(tab.id)}
                      className={`
                        flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-medium
                        transition-colors whitespace-nowrap
                        ${
                          isActive
                            ? 'bg-[var(--primary-button-bg)] text-[var(--primary-button-text)]'
                            : 'text-[var(--muted)] hover:bg-[var(--muted-surface)] hover:text-[var(--text)]'
                        }
                      `}
                      aria-current={isActive ? 'page' : undefined}
                      data-testid={tab.id === 'security' ? 'security-tab' : undefined}
                    >
                      {tab.icon && <span>{tab.icon}</span>}
                      <span>{tab.label}</span>
                    </button>
                  );
                })}
              </nav>
            </div>

            {/* Tab Content */}
            <div className="flex-1 min-w-0">
              <div className="space-y-6">{renderTabContent()}</div>
            </div>
          </div>
        )}
      </div>
    </Container>
  );
};

export default SettingsPage;
