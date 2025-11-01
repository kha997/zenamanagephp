import { Page, expect } from '@playwright/test';

/**
 * Task Helper for E2E Tests
 * Provides methods for testing task functionality
 */
export class TaskHelper {
  constructor(private page: Page) {}

  /**
   * Navigate to tasks page
   */
  async navigateToTasks(): Promise<void> {
    await this.page.goto('/app/tasks');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to task detail page
   */
  async navigateToTaskDetail(taskId: string): Promise<void> {
    await this.page.goto(`/app/tasks/${taskId}`);
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Navigate to Kanban React page
   */
  async navigateToKanbanReact(): Promise<void> {
    await this.page.goto('/app/tasks/kanban-react');
    await this.page.waitForLoadState('networkidle');
  }

  /**
   * Get first task item
   */
  async getFirstTask() {
    return this.page.locator('[data-testid="task-item"]').first();
  }

  /**
   * Click on first task
   */
  async clickFirstTask(): Promise<void> {
    const firstTask = await this.getFirstTask();
    await firstTask.click();
  }

  /**
   * Wait for task to load
   */
  async waitForTaskLoad(): Promise<void> {
    await this.page.waitForSelector('[data-testid="task-detail"]', { timeout: 10000 });
  }
}
