import React from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { CostHealthBadge, type CostHealthStatus } from '../CostHealthBadge';

describe('CostHealthBadge', () => {
  const statuses: CostHealthStatus[] = ['UNDER_BUDGET', 'ON_BUDGET', 'AT_RISK', 'OVER_BUDGET'];

  statuses.forEach((status) => {
    it(`renders badge for ${status} status`, () => {
      render(<CostHealthBadge status={status} />);
      
      const badge = screen.getByText(/Under Budget|On Budget|At Risk|Over Budget/i);
      expect(badge).toBeInTheDocument();
    });
  });

  it('displays correct label for UNDER_BUDGET', () => {
    render(<CostHealthBadge status="UNDER_BUDGET" />);
    expect(screen.getByText('Under Budget')).toBeInTheDocument();
  });

  it('displays correct label for ON_BUDGET', () => {
    render(<CostHealthBadge status="ON_BUDGET" />);
    expect(screen.getByText('On Budget')).toBeInTheDocument();
  });

  it('displays correct label for AT_RISK', () => {
    render(<CostHealthBadge status="AT_RISK" />);
    expect(screen.getByText('At Risk')).toBeInTheDocument();
  });

  it('displays correct label for OVER_BUDGET', () => {
    render(<CostHealthBadge status="OVER_BUDGET" />);
    expect(screen.getByText('Over Budget')).toBeInTheDocument();
  });

  it('shows tooltip when showTooltip is true', () => {
    render(<CostHealthBadge status="UNDER_BUDGET" showTooltip={true} />);
    
    const badge = screen.getByText('Under Budget');
    const wrapper = badge.closest('span');
    expect(wrapper).toHaveAttribute('title');
    expect(wrapper?.getAttribute('title')).toContain('under budget');
  });

  it('does not show tooltip when showTooltip is false', () => {
    render(<CostHealthBadge status="UNDER_BUDGET" showTooltip={false} />);
    
    const badge = screen.getByText('Under Budget');
    const wrapper = badge.closest('span');
    // When showTooltip is false, the badge is returned directly without wrapper
    expect(wrapper?.tagName).not.toBe('SPAN');
  });
});
