import { test as base } from '@playwright/test';
import { AuthHelper } from './helpers/auth-helper';
import { TaskHelper } from './helpers/task-helper';
import { CommentHelper } from './helpers/comment-helper';
import { AttachmentHelper } from './helpers/attachment-helper';

/**
 * Test fixtures for Phase 3 E2E tests
 * Provides helper classes to all tests
 */
export const test = base.extend<{
  authHelper: AuthHelper;
  taskHelper: TaskHelper;
  commentHelper: CommentHelper;
  attachmentHelper: AttachmentHelper;
}>({
  authHelper: async ({ page }, use) => {
    const authHelper = new AuthHelper(page);
    await use(authHelper);
  },

  taskHelper: async ({ page }, use) => {
    const taskHelper = new TaskHelper(page);
    await use(taskHelper);
  },

  commentHelper: async ({ page }, use) => {
    const commentHelper = new CommentHelper(page);
    await use(commentHelper);
  },

  attachmentHelper: async ({ page }, use) => {
    const attachmentHelper = new AttachmentHelper(page);
    await use(attachmentHelper);
  },
});

export { expect } from '@playwright/test';
