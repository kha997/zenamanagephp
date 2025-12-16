import React from 'react';
import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MoneyCell } from '../MoneyCell';

describe('MoneyCell', () => {
  describe('Null/undefined handling', () => {
    it('should display fallback when value is null', () => {
      render(<MoneyCell value={null} currency="USD" fallback="-" />);
      expect(screen.getByText('-')).toBeInTheDocument();
    });

    it('should display default fallback "-" when value is null and fallback not provided', () => {
      render(<MoneyCell value={null} currency="USD" />);
      expect(screen.getByText('-')).toBeInTheDocument();
    });

    it('should display custom fallback when value is null', () => {
      render(<MoneyCell value={null} currency="USD" fallback="N/A" />);
      expect(screen.getByText('N/A')).toBeInTheDocument();
    });

    it('should display fallback when value is undefined', () => {
      render(<MoneyCell value={undefined} currency="USD" fallback="-" />);
      expect(screen.getByText('-')).toBeInTheDocument();
    });
  });

  describe('Zero handling', () => {
    it('should format 0 as currency, not use fallback', () => {
      render(<MoneyCell value={0} currency="USD" fallback="-" />);
      const element = screen.getByText(/0/);
      expect(element).toBeInTheDocument();
      // Should contain formatted 0, not the fallback
      expect(screen.queryByText('-')).not.toBeInTheDocument();
    });

    it('should format 0 with VND currency', () => {
      render(<MoneyCell value={0} currency="VND" />);
      const element = screen.getByText(/0/);
      expect(element).toBeInTheDocument();
    });
  });

  describe('Positive values', () => {
    it('should format positive value without plus prefix by default', () => {
      render(<MoneyCell value={1000000} currency="USD" />);
      // Flexible regex: accepts 1,000,000 or 1.000.000 (locale-dependent)
      const element = screen.getByText(/1[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
      // Should not have + prefix
      const text = element.textContent || '';
      expect(text).not.toMatch(/\+/);
    });

    it('should format positive value with plus prefix when showPlusWhenPositive is true', () => {
      render(<MoneyCell value={1000000} currency="USD" showPlusWhenPositive={true} />);
      // Flexible regex: accepts +1,000,000 or +1.000.000 (locale-dependent)
      const element = screen.getByText(/\+.*1[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
    });

    it('should format positive value without plus prefix when showPlusWhenPositive is false', () => {
      render(<MoneyCell value={1000000} currency="USD" showPlusWhenPositive={false} />);
      // Flexible regex: accepts 1,000,000 or 1.000.000 (locale-dependent)
      const element = screen.getByText(/1[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
      const text = element.textContent || '';
      expect(text).not.toMatch(/\+/);
    });
  });

  describe('Currency formatting', () => {
    it('should format USD currency correctly', () => {
      render(<MoneyCell value={1000000} currency="USD" />);
      // Flexible regex: accepts 1,000,000 or 1.000.000 (locale-dependent)
      const element = screen.getByText(/1[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
    });

    it('should format VND currency correctly', () => {
      render(<MoneyCell value={1000000} currency="VND" />);
      // Flexible regex: accepts 1,000,000 or 1.000.000 (locale-dependent)
      const element = screen.getByText(/1[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
    });

    it('should use USD as default currency', () => {
      render(<MoneyCell value={1000} />);
      // Flexible regex: accepts 1,000 or 1.000 (locale-dependent)
      const element = screen.getByText(/1[.,]0{3}/);
      expect(element).toBeInTheDocument();
    });
  });

  describe('Tone styling', () => {
    it('should apply danger tone data attribute when tone is danger', () => {
      const { container } = render(<MoneyCell value={1000} tone="danger" />);
      const element = container.querySelector('span');
      expect(element).toHaveAttribute('data-tone', 'danger');
      expect(element).toHaveClass('text-[var(--color-semantic-danger-600)]');
      expect(element).toHaveClass('font-semibold');
    });

    it('should apply muted tone data attribute when tone is muted', () => {
      const { container } = render(<MoneyCell value={1000} tone="muted" />);
      const element = container.querySelector('span');
      expect(element).toHaveAttribute('data-tone', 'muted');
      expect(element).toHaveClass('text-[var(--color-text-muted)]');
    });

    it('should apply normal tone data attribute when tone is normal', () => {
      const { container } = render(<MoneyCell value={1000} tone="normal" />);
      const element = container.querySelector('span');
      expect(element).toHaveAttribute('data-tone', 'normal');
      expect(element).not.toHaveClass('text-[var(--color-semantic-danger-600)]');
      expect(element).not.toHaveClass('text-[var(--color-text-muted)]');
    });

    it('should apply danger tone data attribute to fallback when value is null', () => {
      const { container } = render(<MoneyCell value={null} tone="danger" fallback="-" />);
      const element = container.querySelector('span');
      expect(element).toHaveAttribute('data-tone', 'danger');
      expect(element).toHaveClass('text-[var(--color-semantic-danger-600)]');
      expect(element).toHaveClass('font-semibold');
    });

    it('should apply muted tone data attribute to fallback when value is null', () => {
      const { container } = render(<MoneyCell value={null} tone="muted" fallback="-" />);
      const element = container.querySelector('span');
      expect(element).toHaveAttribute('data-tone', 'muted');
      expect(element).toHaveClass('text-[var(--color-text-muted)]');
    });

    it('should apply normal tone data attribute to fallback when value is null and tone is normal', () => {
      const { container } = render(<MoneyCell value={null} tone="normal" fallback="-" />);
      const element = container.querySelector('span');
      expect(element).toHaveAttribute('data-tone', 'normal');
    });
  });

  describe('Custom className', () => {
    it('should apply custom className', () => {
      const { container } = render(<MoneyCell value={1000} className="custom-class" />);
      const element = container.querySelector('span');
      expect(element).toHaveClass('custom-class');
    });

    it('should combine custom className with tone classes', () => {
      const { container } = render(<MoneyCell value={1000} tone="danger" className="custom-class" />);
      const element = container.querySelector('span');
      expect(element).toHaveClass('custom-class');
      expect(element).toHaveClass('text-[var(--color-semantic-danger-600)]');
    });
  });

  describe('Whitespace handling', () => {
    it('should always apply whitespace-nowrap class', () => {
      const { container } = render(<MoneyCell value={1000} />);
      const element = container.querySelector('span');
      expect(element).toHaveClass('whitespace-nowrap');
    });
  });

  describe('Combined scenarios', () => {
    it('should handle positive value with plus prefix and danger tone', () => {
      const { container } = render(
        <MoneyCell
          value={5000000}
          currency="USD"
          showPlusWhenPositive={true}
          tone="danger"
        />
      );
      // Flexible regex: accepts +5,000,000 or +5.000.000 (locale-dependent)
      const element = screen.getByText(/\+.*5[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
      const span = container.querySelector('span');
      expect(span).toHaveAttribute('data-tone', 'danger');
      expect(span).toHaveClass('text-[var(--color-semantic-danger-600)]');
    });

    it('should handle zero with muted tone', () => {
      const { container } = render(
        <MoneyCell
          value={0}
          currency="USD"
          tone="muted"
        />
      );
      const element = screen.getByText(/0/);
      expect(element).toBeInTheDocument();
      const span = container.querySelector('span');
      expect(span).toHaveAttribute('data-tone', 'muted');
      expect(span).toHaveClass('text-[var(--color-text-muted)]');
    });

    it('should handle null with custom fallback and danger tone', () => {
      const { container } = render(
        <MoneyCell
          value={null}
          currency="USD"
          fallback="N/A"
          tone="danger"
        />
      );
      expect(screen.getByText('N/A')).toBeInTheDocument();
      const span = container.querySelector('span');
      expect(span).toHaveAttribute('data-tone', 'danger');
      expect(span).toHaveClass('text-[var(--color-semantic-danger-600)]');
    });

    it('should handle value={null} with tone="danger" and data-tone attribute', () => {
      const { container } = render(
        <MoneyCell
          value={null}
          currency="USD"
          fallback="-"
          tone="danger"
        />
      );
      const span = container.querySelector('span');
      expect(span).toHaveAttribute('data-tone', 'danger');
      expect(screen.getByText('-')).toBeInTheDocument();
    });

    it('should handle value={0} with tone="muted" and data-tone attribute', () => {
      const { container } = render(
        <MoneyCell
          value={0}
          currency="USD"
          fallback="0"
          tone="muted"
        />
      );
      const span = container.querySelector('span');
      expect(span).toHaveAttribute('data-tone', 'muted');
      const element = screen.getByText(/0/);
      expect(element).toBeInTheDocument();
    });

    it('should handle value={1_000_000} with showPlusWhenPositive and data-tone', () => {
      const { container } = render(
        <MoneyCell
          value={1_000_000}
          currency="USD"
          showPlusWhenPositive={true}
          tone="danger"
        />
      );
      // Flexible regex: accepts +1,000,000 or +1.000.000 (locale-dependent)
      const element = screen.getByText(/\+.*1[.,]0{3}[.,]0{3}/);
      expect(element).toBeInTheDocument();
      const span = container.querySelector('span');
      expect(span).toHaveAttribute('data-tone', 'danger');
    });
  });
});

