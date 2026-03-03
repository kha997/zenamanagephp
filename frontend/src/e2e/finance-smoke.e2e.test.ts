import {
  canAccessDashboard,
  getAccessibleDashboards,
  getDashboardUrl,
  getRoleDisplayName,
} from '../utils/dashboardUtils'

describe('finance smoke', () => {
  const financeUser = {
    id: 'user-finance-smoke',
    name: 'Finance Smoke',
    email: 'finance-smoke@example.test',
    roles: ['Finance'],
    permissions: [],
  }

  it('routes finance users to the finance dashboard', () => {
    expect(getDashboardUrl(financeUser)).toBe('/finance')
    expect(canAccessDashboard(financeUser, 'Finance')).toBe(true)
    expect(getRoleDisplayName('Finance')).toBe('Finance')

    expect(getAccessibleDashboards(financeUser)).toEqual(
      expect.arrayContaining([
        expect.objectContaining({
          role: 'Finance',
          url: '/finance',
          label: 'Finance Dashboard',
        }),
      ]),
    )
  })
})
