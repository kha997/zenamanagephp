import React from 'react';
import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CostWorkflowTimeline, CostWorkflowTimelineItem } from '../CostWorkflowTimeline';

describe('CostWorkflowTimeline', () => {
  const mockItems: CostWorkflowTimelineItem[] = [
    {
      id: '1',
      timestamp: '2024-01-15T10:00:00Z',
      action: 'change_order_proposed',
      action_label: 'Change Order Proposed',
      userName: 'John Doe',
      userEmail: 'john@example.com',
      metadata: { status_before: 'draft', status_after: 'proposed' },
      description: 'Change order was proposed',
    },
    {
      id: '2',
      timestamp: '2024-01-16T14:30:00Z',
      action: 'change_order_approved',
      action_label: 'Change Order Approved',
      userName: 'Jane Smith',
      userEmail: 'jane@example.com',
      metadata: { status_before: 'proposed', status_after: 'approved', amount_delta: 50000 },
      description: 'Change order was approved',
    },
  ];

  it('renders timeline with items', () => {
    render(<CostWorkflowTimeline items={mockItems} />);
    
    expect(screen.getByText('Workflow Timeline')).toBeInTheDocument();
    expect(screen.getByText('Change Order Proposed')).toBeInTheDocument();
    expect(screen.getByText('Change Order Approved')).toBeInTheDocument();
    expect(screen.getByText('by John Doe')).toBeInTheDocument();
    expect(screen.getByText('by Jane Smith')).toBeInTheDocument();
  });

  it('displays empty state when no items', () => {
    render(<CostWorkflowTimeline items={[]} />);
    
    expect(screen.getByText('Workflow Timeline')).toBeInTheDocument();
    expect(screen.getByText('No workflow history yet.')).toBeInTheDocument();
  });

  it('displays custom title when provided', () => {
    render(<CostWorkflowTimeline items={mockItems} title="Approval History" />);
    
    expect(screen.getByText('Approval History')).toBeInTheDocument();
    expect(screen.queryByText('Workflow Timeline')).not.toBeInTheDocument();
  });

  it('displays metadata summary when available', () => {
    render(<CostWorkflowTimeline items={mockItems} />);
    
    // Check for status change metadata
    expect(screen.getByText(/Status: draft → proposed/)).toBeInTheDocument();
    expect(screen.getByText(/Status: proposed → approved/)).toBeInTheDocument();
    // Check for amount metadata
    expect(screen.getByText(/Amount: \+50000/)).toBeInTheDocument();
  });

  it('formats timestamps correctly', () => {
    render(<CostWorkflowTimeline items={mockItems} />);
    
    // Should display formatted date (exact format may vary by locale)
    const timestamps = screen.getAllByText(/Jan|January/);
    expect(timestamps.length).toBeGreaterThan(0);
  });

  it('displays description when available', () => {
    render(<CostWorkflowTimeline items={mockItems} />);
    
    expect(screen.getByText('Change order was proposed')).toBeInTheDocument();
    expect(screen.getByText('Change order was approved')).toBeInTheDocument();
  });

  it('handles items without user information', () => {
    const itemsWithoutUser: CostWorkflowTimelineItem[] = [
      {
        id: '1',
        timestamp: '2024-01-15T10:00:00Z',
        action: 'change_order_proposed',
      },
    ];
    
    render(<CostWorkflowTimeline items={itemsWithoutUser} />);
    
    expect(screen.getByText('Change Order Proposed')).toBeInTheDocument();
    expect(screen.queryByText(/by /)).not.toBeInTheDocument();
  });

  it('sorts items chronologically (oldest to newest)', () => {
    const unsortedItems: CostWorkflowTimelineItem[] = [
      {
        id: '2',
        timestamp: '2024-01-16T14:30:00Z',
        action: 'change_order_approved',
        action_label: 'Change Order Approved',
      },
      {
        id: '1',
        timestamp: '2024-01-15T10:00:00Z',
        action: 'change_order_proposed',
        action_label: 'Change Order Proposed',
      },
    ];
    
    render(<CostWorkflowTimeline items={unsortedItems} />);
    
    const items = screen.getAllByText(/Change Order/);
    // First item should be "Proposed" (older), second "Approved" (newer)
    expect(items[0]).toHaveTextContent('Change Order Proposed');
    expect(items[1]).toHaveTextContent('Change Order Approved');
  });

  it('renders in compact mode', () => {
    const { container } = render(<CostWorkflowTimeline items={mockItems} compact />);
    
    // Compact mode should still render all items
    expect(screen.getByText('Change Order Proposed')).toBeInTheDocument();
    expect(screen.getByText('Change Order Approved')).toBeInTheDocument();
  });
});
