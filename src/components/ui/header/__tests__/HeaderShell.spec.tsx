import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { vi } from 'vitest';
import { HeaderShell } from '../HeaderShell';

vi.mock('../../../hooks/useHeaderCondense', () => ({
  useHeaderCondense: vi.fn(),
}));

const logo = <span data-testid="logo">Logo</span>;
const primaryNav = <nav data-testid="primary-nav">Nav</nav>;
const secondaryActions = <button type="button">Create</button>;
const breadcrumbs = <div>Dashboard &gt; Overview</div>;

describe('HeaderShell', () => {
  it('renders logo, navigation, actions, and breadcrumbs', () => {
    render(
      <HeaderShell
        logo={logo}
        primaryNav={primaryNav}
        secondaryActions={secondaryActions}
        breadcrumbs={breadcrumbs}
        userMenu={<div>Menu</div>}
      />,
    );

    expect(screen.getByTestId('logo')).toBeVisible();
    expect(screen.getAllByTestId('primary-nav')[0]).toBeVisible();
    expect(screen.getByRole('banner')).toHaveAttribute('aria-label', 'Main navigation');
    expect(screen.getByText('Dashboard > Overview')).toBeVisible();
  });

  it('toggles the mobile menu and updates theme attribute', async () => {
    const user = userEvent.setup();
    render(<HeaderShell logo={logo} primaryNav={primaryNav} theme="dark" />);

    expect(document.documentElement).toHaveAttribute('data-theme', 'dark');

    const toggle = screen.getByRole('button', { name: /toggle mobile menu/i });
    await user.click(toggle);
    expect(screen.getByRole('dialog', { name: /mobile navigation menu/i })).toHaveClass('open');

    await user.click(toggle);
    expect(screen.getByRole('dialog', { name: /mobile navigation menu/i })).toHaveClass('closed');
  });
});
