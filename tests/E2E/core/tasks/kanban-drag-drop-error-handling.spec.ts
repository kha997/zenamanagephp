import { test, expect, Page } from '@playwright/test';
import { login, seed, TestData } from '../../../test-setup';

const getTaskCard = (page: Page, title: string) => {
    return page.locator('[data-testid^="task-card-"]').filter({ hasText: title });
};

const getKanbanColumn = (page: Page, status: string) => {
    return page.locator(`[data-testid="kanban-column-${status}"]`);
};

test.describe('Kanban Drag and Drop Error Handling', () => {
    let testData: TestData;

    test.beforeEach(async ({ page }) => {
        testData = await seed(45678);
        await login(page, testData.user.email, 'password');
        await page.goto(`/projects/${testData.project.id}`);
        await expect(page.locator('h1')).toContainText(testData.project.name);
    });

    test('shows error modal for invalid transition', async ({ page }) => {
        await page.route('**/api/tasks/*/move', route => {
            route.fulfill({
                status: 422,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Invalid transition from backlog to in_review.' }),
            });
        });

        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const targetColumn = await getKanbanColumn(page, 'in_review');

        await taskToMove.dragTo(targetColumn);

        await expect(page.locator('[data-testid="error-modal-title"]')).toHaveText('Invalid Move');
        await expect(page.locator('[data-testid="error-modal-description"]')).toContainText("A task can't be moved from Backlog to In Review.");
    });

    test('shows error modal when blocked by dependencies', async ({ page }) => {
        await page.route('**/api/tasks/*/move', route => {
            route.fulfill({
                status: 422,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Task has incomplete dependencies.' }),
            });
        });
        
        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const targetColumn = await getKanbanColumn(page, 'in_progress');

        await taskToMove.dragTo(targetColumn);

        await expect(page.locator('[data-testid="error-modal-title"]')).toHaveText('Blocked by Dependencies');
        await expect(page.locator('[data-testid="error-modal-description"]')).toContainText('This task cannot be started until its dependencies are completed.');
    });
    
    test('shows reason-required modal for regressive moves', async ({ page }) => {
        const taskToMove = await getTaskCard(page, testData.tasks.in_review[0].title);
        const targetColumn = await getKanbanColumn(page, 'in_progress');

        await taskToMove.dragTo(targetColumn);

        await expect(page.locator('[data-testid="reason-modal-title"]')).toHaveText('Reason Required');
        await expect(page.locator('[data-testid="reason-modal-description"]')).toContainText('Please provide a reason for moving this task backwards.');
        
        await page.route('**/api/tasks/*/move', route => {
            route.fulfill({
                status: 200,
                contentType: 'application/json',
                body: JSON.stringify({ data: { ...testData.tasks.in_review[0], status: 'in_progress' } }),
            });
        });
        
        await page.locator('[data-testid="reason-input"]').fill('Needs rework');
        await page.locator('[data-testid="reason-submit-button"]').click();

        await expect(targetColumn.locator(`[data-testid="task-card-${testData.tasks.in_review[0].id}"]`)).toBeVisible();
    });

    test('task card gets red border and animates back on failed drag', async ({ page }) => {
        await page.route('**/api/tasks/*/move', route => {
            route.fulfill({ status: 422, body: JSON.stringify({ message: 'Generic error' }) });
        });

        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const originalColumn = await getKanbanColumn(page, 'backlog');
        const targetColumn = await getKanbanColumn(page, 'in_review');
        const initialBox = await taskToMove.boundingBox();
        
        await taskToMove.dragTo(targetColumn);

        await expect(taskToMove).toHaveClass(/border-red-500/);
        await page.waitForTimeout(1000);
        await expect(taskToMove).not.toHaveClass(/border-red-500/);
        
        const finalBox = await taskToMove.boundingBox();
        expect(finalBox?.x).toBeCloseTo(initialBox?.x ?? 0, 1);
        expect(finalBox?.y).toBeCloseTo(initialBox?.y ?? 0, 1);
        await expect(originalColumn.locator(`[data-testid="task-card-${testData.tasks.backlog[0].id}"`)).toBeVisible();
    });

    test('invalid drop targets are not highlighted', async ({ page }) => {
        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const validTarget = await getKanbanColumn(page, 'in_progress');
        const invalidTarget = await getKanbanColumn(page, 'in_review');

        await taskToMove.hover();
        await page.mouse.down();
        
        await validTarget.hover();
        await expect(validTarget).toHaveClass(/bg-secondary-light/);

        await invalidTarget.hover();
        await expect(invalidTarget).not.toHaveClass(/bg-secondary-light/);
        
        await page.mouse.up();
    });
    
    test('shows tooltip on invalid column hover during drag', async ({ page }) => {
        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const invalidTarget = await getKanbanColumn(page, 'in_review');
        
        await taskToMove.hover();
        await page.mouse.down();
        await invalidTarget.hover();
        
        await expect(page.locator('[role="tooltip"]')).toBeVisible();
        await expect(page.locator('[role="tooltip"]')).toContainText("Invalid move: Backlog to In Review");
        
        await page.mouse.up();
    });
    
    test('shows tooltip on blocked task icon', async ({ page }) => {
        const blockedTask = await getTaskCard(page, testData.tasks.blocked[0].title);
        
        await blockedTask.locator('[data-testid="dependency-icon"]').hover();
        
        await expect(page.locator('[role="tooltip"]')).toBeVisible();
        await expect(page.locator('[role="tooltip"]')).toContainText('Blocked by:');
        await expect(page.locator('[role="tooltip"]')).toContainText(testData.tasks.blocker[0].title);
    });

    test('no tooltip on valid drop targets during drag', async ({ page }) => {
        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const validTarget = await getKanbanColumn(page, 'in_progress');
        
        await taskToMove.hover();
        await page.mouse.down();
        await validTarget.hover();
        
        await expect(page.locator('[role="tooltip"]')).not.toBeVisible();
        
        await page.mouse.up();
    });
    
    test('optimistic lock failure shows error and rolls back', async ({ page }) => {
        await page.route('**/api/tasks/*/move', route => {
            route.fulfill({
                status: 409,
                contentType: 'application/json',
                body: JSON.stringify({ message: 'Task has been updated by another process.' }),
            });
        });

        const taskToMove = await getTaskCard(page, testData.tasks.backlog[0].title);
        const targetColumn = await getKanbanColumn(page, 'in_progress');

        await taskToMove.dragTo(targetColumn);

        await expect(page.locator('[data-testid="error-modal-title"')).toHaveText('Task Out of Date');
        await expect(getKanbanColumn(page, 'backlog').locator(`[data-testid="task-card-${testData.tasks.backlog[0].id}"`)).toBeVisible();
    });
});
