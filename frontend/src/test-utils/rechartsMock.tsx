/**
 * Shared Recharts mock utilities for testing chart components
 * 
 * Round 60: Shared test utils for Reports module
 * 
 * Provides reusable mock setup for Recharts components to test:
 * - Bar chart click events
 * - Chart data rendering
 * - Drill-down navigation
 */

import { vi } from 'vitest';

export interface RechartsMockConfig {
  /**
   * Test ID for the bar element (e.g., 'project-bar', 'client-bar')
   */
  testId: string;
  /**
   * Optional: Reference to store chart data for testing
   */
  dataRef?: { current: any[] };
  /**
   * Optional: Flag to control whether to pass empty payload on click
   */
  passEmptyPayload?: boolean;
}

/**
 * Create a Recharts mock factory that can be used in tests
 * 
 * @param config - Configuration for the mock
 * @returns Object with mock setup functions and state
 */
export function createRechartsBarMock(config: RechartsMockConfig) {
  const { testId, dataRef, passEmptyPayload = false } = config;
  
  let mockChartData: any[] = [];
  let mockBarPassEmptyPayload = passEmptyPayload;

  const mock = {
    /**
     * Get current chart data
     */
    getChartData: () => mockChartData,
    
    /**
     * Set chart data
     */
    setChartData: (data: any[]) => {
      mockChartData = data || [];
      if (dataRef) {
        dataRef.current = mockChartData;
      }
    },
    
    /**
     * Set flag to pass empty payload on click
     */
    setPassEmptyPayload: (value: boolean) => {
      mockBarPassEmptyPayload = value;
    },
    
    /**
     * Reset all state
     */
    reset: () => {
      mockChartData = [];
      mockBarPassEmptyPayload = passEmptyPayload;
      if (dataRef) {
        dataRef.current = [];
      }
    },
    
    /**
     * Get the vi.mock() factory function
     */
    getMockFactory: () => {
      return async () => {
        const actual = await vi.importActual('recharts');
        return {
          ...actual,
          BarChart: ({ children, data, ...props }: any) => {
            // Store the data so Bar can access it
            mockChartData = data || [];
            if (dataRef) {
              dataRef.current = mockChartData;
            }
            return (
              <div data-testid="bar-chart" {...props}>
                {children}
              </div>
            );
          },
          Bar: ({ onClick, dataKey, ...props }: any) => {
            // Create a testable div that can trigger onClick
            // Filter out dataKey to avoid React warning
            return (
              <div
                data-testid={testId}
                onClick={() => {
                  // Simulate the click event structure that recharts passes
                  if (onClick) {
                    if (mockBarPassEmptyPayload) {
                      // Test case for missing payload
                      onClick({
                        payload: {},
                      });
                    } else if (mockChartData.length > 0) {
                      // Use the first item from chart data as the payload
                      onClick({
                        payload: mockChartData[0],
                      });
                    } else {
                      // No data case
                      onClick({
                        payload: {},
                      });
                    }
                  }
                }}
                {...props}
              />
            );
          },
          ResponsiveContainer: ({ children }: any) => <div>{children}</div>,
          XAxis: () => null,
          YAxis: () => null,
          Tooltip: () => null,
        };
      };
    },
  };

  return mock;
}

/**
 * Type for chart bar click event
 */
export type ChartBarClickEvent<T extends { [key: string]: any }> = {
  payload?: T;
};

