import React, { useState, useMemo } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '../../../../shared/ui/card';
import { Button } from '../../../../shared/ui/button';
import { Badge } from '../../../../shared/ui/badge';
import { Input } from '../../../../components/ui/primitives/Input';
import { Select } from '../../../../components/ui/primitives/Select';
import { useInvitations, useResendInvitation } from '../hooks';
import type { InvitationsFilters } from '../invitation-types';
import { LoadingSpinner } from '../../../../components/shared/LoadingSpinner';
import toast from 'react-hot-toast';

interface InvitationListProps {
  tenantId?: string;
}

const STATUS_OPTIONS = [
  { value: 'all', label: 'All Status' },
  { value: 'pending', label: 'Pending' },
  { value: 'accepted', label: 'Accepted' },
  { value: 'expired', label: 'Expired' },
  { value: 'cancelled', label: 'Cancelled' },
];

export const InvitationList: React.FC<InvitationListProps> = ({ tenantId }) => {
  const [filters, setFilters] = useState<InvitationsFilters>({
    page: 1,
    per_page: 15,
    tenant_id: tenantId ? Number(tenantId) : undefined,
  });
  const [searchInput, setSearchInput] = useState('');

  const { data: invitationsResponse, isLoading, error } = useInvitations(filters);
  const resendMutation = useResendInvitation();

  const invitations = invitationsResponse?.data || [];
  const meta = invitationsResponse?.meta;

  // Debounce search
  React.useEffect(() => {
    const timer = setTimeout(() => {
      setFilters((prev) => ({
        ...prev,
        search: searchInput || undefined,
        page: 1,
      }));
    }, 500);

    return () => clearTimeout(timer);
  }, [searchInput]);

  const handleResend = async (id: number) => {
    try {
      const result = await resendMutation.mutateAsync(id);
      toast.success('Invitation resent successfully');
      if (result.email_sent) {
        toast.success('Email notification sent');
      } else {
        toast('Email not configured. You can copy the invitation link manually.', {
          duration: 5000,
          icon: 'ℹ️',
        });
      }
    } catch (error: any) {
      const errorMessage = error?.response?.data?.error?.message || error?.message || 'Failed to resend invitation';
      toast.error(errorMessage);
    }
  };

  const handleCopyLink = (link: string) => {
    navigator.clipboard.writeText(link);
    toast.success('Invitation link copied to clipboard');
  };

  const getStatusBadge = (status: string) => {
    const variants: Record<string, 'default' | 'success' | 'warning' | 'destructive'> = {
      pending: 'default',
      accepted: 'success',
      expired: 'warning',
      cancelled: 'destructive',
    };
    return <Badge variant={variants[status] || 'default'}>{status}</Badge>;
  };

  if (isLoading) {
    return (
      <Card>
        <CardContent className="flex items-center justify-center h-64">
          <LoadingSpinner size="lg" message="Loading invitations..." />
        </CardContent>
      </Card>
    );
  }

  if (error) {
    return (
      <Card>
        <CardContent className="text-center py-12">
          <div className="text-[var(--color-semantic-danger-600)]">
            <h3 className="text-lg font-medium mb-2">Failed to load invitations</h3>
            <p className="text-sm text-[var(--color-text-secondary)]">
              {error instanceof Error ? error.message : 'There was an error loading invitations. Please try again.'}
            </p>
          </div>
        </CardContent>
      </Card>
    );
  }

  return (
    <Card>
      <CardHeader>
        <div className="flex justify-between items-center">
          <CardTitle>Invitations ({meta?.total || 0})</CardTitle>
        </div>
      </CardHeader>
      <CardContent>
        {/* Filters */}
        <div className="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
          <div>
            <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
              Search
            </label>
            <Input
              placeholder="Search by email or name..."
              value={searchInput}
              onChange={(e) => setSearchInput(e.target.value)}
              leadingIcon={<span>🔍</span>}
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
              Status
            </label>
            <Select
              options={STATUS_OPTIONS}
              value={filters.status || 'all'}
              onChange={(value) =>
                setFilters((prev) => ({
                  ...prev,
                  status: value === 'all' ? undefined : (value as InvitationsFilters['status']),
                  page: 1,
                }))
              }
            />
          </div>

          <div>
            <label className="block text-sm font-medium text-[var(--color-text-secondary)] mb-2">
              Per Page
            </label>
            <select
              value={filters.per_page || 15}
              onChange={(e) =>
                setFilters((prev) => ({
                  ...prev,
                  per_page: Number(e.target.value),
                  page: 1,
                }))
              }
              className="w-full px-3 py-2 border border-[var(--color-border-default)] rounded-[var(--radius-md)] bg-[var(--color-surface-base)] text-[var(--color-text-primary)] focus:outline-none focus:ring-2 focus:ring-[var(--color-semantic-primary-200)]"
            >
              <option value={10}>10 per page</option>
              <option value={15}>15 per page</option>
              <option value={25}>25 per page</option>
              <option value={50}>50 per page</option>
            </select>
          </div>
        </div>

        {/* Invitations Table */}
        {invitations.length === 0 ? (
          <div className="text-center py-12">
            <div className="text-[var(--color-text-secondary)]">
              <span className="text-4xl mb-4 block">✉️</span>
              <h3 className="text-lg font-medium mb-2 text-[var(--color-text-primary)]">No invitations found</h3>
              <p className="text-sm text-[var(--color-text-secondary)]">Try adjusting your filters.</p>
            </div>
          </div>
        ) : (
          <div className="overflow-x-auto">
            <table className="w-full">
              <thead>
                <tr className="border-b border-[var(--color-border-subtle)]">
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Email</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Name</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Role</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Tenant</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Status</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Expires</th>
                  <th className="text-left py-3 px-4 text-sm font-medium text-[var(--color-text-secondary)]">Actions</th>
                </tr>
              </thead>
              <tbody>
                {invitations.map((invitation) => (
                  <tr
                    key={invitation.id}
                    className="border-b border-[var(--color-border-subtle)] hover:bg-[var(--color-surface-muted)]"
                  >
                    <td className="py-3 px-4 text-sm text-[var(--color-text-primary)]">{invitation.email}</td>
                    <td className="py-3 px-4 text-sm text-[var(--color-text-primary)]">
                      {invitation.first_name || invitation.last_name
                        ? `${invitation.first_name || ''} ${invitation.last_name || ''}`.trim()
                        : '-'}
                    </td>
                    <td className="py-3 px-4 text-sm text-[var(--color-text-primary)]">{invitation.role}</td>
                    <td className="py-3 px-4 text-sm text-[var(--color-text-primary)]">{invitation.tenant_name}</td>
                    <td className="py-3 px-4">{getStatusBadge(invitation.status)}</td>
                    <td className="py-3 px-4 text-sm text-[var(--color-text-secondary)]">
                      {new Date(invitation.expires_at).toLocaleDateString()}
                    </td>
                    <td className="py-3 px-4">
                      <div className="flex gap-2">
                        {invitation.status === 'pending' && (
                          <>
                            <Button
                              variant="outline"
                              size="sm"
                              onClick={() => handleResend(invitation.id)}
                              disabled={resendMutation.isPending}
                            >
                              Resend
                            </Button>
                            <Button
                              variant="ghost"
                              size="sm"
                              onClick={() => handleCopyLink(invitation.link)}
                            >
                              Copy Link
                            </Button>
                          </>
                        )}
                        {invitation.status === 'accepted' && (
                          <span className="text-xs text-[var(--color-text-muted)]">
                            Accepted {invitation.accepted_at ? new Date(invitation.accepted_at).toLocaleDateString() : ''}
                          </span>
                        )}
                      </div>
                    </td>
                  </tr>
                ))}
              </tbody>
            </table>
          </div>
        )}

        {/* Pagination */}
        {meta && meta.last_page > 1 && (
          <nav className="flex items-center justify-between mt-6 pt-4 border-t border-[var(--color-border-subtle)]">
            <div className="text-sm text-[var(--color-text-secondary)]">
              Showing {((meta.current_page - 1) * meta.per_page) + 1} to{' '}
              {Math.min(meta.current_page * meta.per_page, meta.total)} of {meta.total} invitations
            </div>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                onClick={() => setFilters((prev) => ({ ...prev, page: (prev.page || 1) - 1 }))}
                disabled={meta.current_page === 1}
              >
                Previous
              </Button>
              <Button
                variant="outline"
                size="sm"
                onClick={() => setFilters((prev) => ({ ...prev, page: (prev.page || 1) + 1 }))}
                disabled={meta.current_page === meta.last_page}
              >
                Next
              </Button>
            </div>
          </nav>
        )}
      </CardContent>
    </Card>
  );
};

