import React from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Container } from '../../../components/ui/layout/Container';
import { Card, CardContent, CardHeader, CardTitle } from '../../../shared/ui/card';
import { Button } from '../../../components/ui/primitives/Button';
import { Table } from '../../../components/ui/Table';
import { MoneyCell } from '../../reports/components/MoneyCell';
import { useProjectContracts, useProject } from '../hooks';
import { usePermissions } from '../../../hooks/usePermissions';
import type { ContractSummary } from '../api';

/**
 * Project Contracts Page
 * 
 * Round 225: Contract & Change Order Drilldown
 * 
 * Displays a list of all contracts for a project with key financial metrics.
 */
export const ProjectContractsPage: React.FC = () => {
  const { id: projectId } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { canViewCost, canEditCost } = usePermissions();

  const { data: projectData } = useProject(projectId);
  const { data: contractsData, isLoading, error } = useProjectContracts(projectId);

  const contracts = contractsData?.data || [];
  const currency = contracts[0]?.currency || projectData?.data?.metadata?.currency || 'VND';

  // Permission check - Round 229
  if (!canViewCost(Number(projectId))) {
    return (
      <Container>
        <Card>
          <CardContent className="py-8">
            <div className="text-center text-[var(--muted)]">
              <p>You do not have permission to view cost data for this project.</p>
            </div>
          </CardContent>
        </Card>
      </Container>
    );
  }

  const handleRowClick = (contract: ContractSummary) => {
    navigate(`/app/projects/${projectId}/contracts/${contract.id}`);
  };

  const columns = [
    {
      key: 'code',
      title: 'Code',
      width: '120px',
    },
    {
      key: 'name',
      title: 'Name',
    },
    {
      key: 'party_name',
      title: 'Contractor',
      width: '200px',
    },
    {
      key: 'base_amount',
      title: 'Base Amount',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractSummary) => (
        <MoneyCell value={record.base_amount} currency={currency} />
      ),
    },
    {
      key: 'current_amount',
      title: 'Current Amount',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractSummary) => (
        <MoneyCell value={record.current_amount} currency={currency} />
      ),
    },
    {
      key: 'total_certified_amount',
      title: 'Certified',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractSummary) => (
        <MoneyCell value={record.total_certified_amount} currency={currency} />
      ),
    },
    {
      key: 'total_paid_amount',
      title: 'Paid',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractSummary) => (
        <MoneyCell value={record.total_paid_amount} currency={currency} />
      ),
    },
    {
      key: 'outstanding_amount',
      title: 'Outstanding',
      align: 'right' as const,
      width: '150px',
      render: (_value: unknown, record: ContractSummary) => (
        <MoneyCell 
          value={record.outstanding_amount} 
          currency={currency}
          tone={record.outstanding_amount && record.outstanding_amount > 0 ? 'danger' : 'normal'}
        />
      ),
    },
  ];

  return (
    <Container>
      <div className="space-y-6">
        {/* Header */}
        <div className="flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-semibold text-[var(--text)]">Contracts</h1>
            {projectData?.data && (
              <p className="text-sm text-[var(--muted)] mt-1">
                Project: {projectData.data.name}
              </p>
            )}
          </div>
          <div className="flex gap-2">
            {canEditCost(Number(projectId)) && (
              <Button
                variant="primary"
                onClick={() => navigate(`/app/projects/${projectId}/contracts/new`)}
              >
                Add Contract
              </Button>
            )}
            <Button
              variant="secondary"
              onClick={() => navigate(`/app/projects/${projectId}`)}
            >
              Back to Project
            </Button>
          </div>
        </div>

        {/* Contracts Table */}
        <Card>
          <CardHeader>
            <CardTitle>All Contracts</CardTitle>
          </CardHeader>
          <CardContent>
            {isLoading ? (
              <div className="py-12 text-center text-[var(--muted)]">
                <div className="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-[var(--accent)]"></div>
                <p className="mt-2">Loading contracts...</p>
              </div>
            ) : error ? (
              <div className="py-12 text-center">
                <p className="text-[var(--color-semantic-danger-600)] mb-4">
                  Error loading contracts: {(error as Error).message}
                </p>
                <Button variant="primary" onClick={() => window.location.reload()}>
                  Retry
                </Button>
              </div>
            ) : contracts.length === 0 ? (
              <div className="py-12 text-center text-[var(--muted)]">
                <p>No contracts found for this project.</p>
              </div>
            ) : (
              <Table
                columns={columns}
                data={contracts}
                onRow={(record) => ({
                  onClick: () => handleRowClick(record),
                  className: 'cursor-pointer',
                })}
                size="md"
              />
            )}
          </CardContent>
        </Card>
      </div>
    </Container>
  );
};
