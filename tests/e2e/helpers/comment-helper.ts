import { Page, expect } from '@playwright/test';

/**
 * Comment Helper for E2E Tests
 * Provides methods for testing comment functionality
 */
export class CommentHelper {
  constructor(private page: Page) {}

  /**
   * Create a new comment
   */
  async createComment(content: string, type: string = 'general', isInternal: boolean = false): Promise<void> {
    await this.page.fill('[data-testid="comment-content"]', content);
    await this.page.selectOption('[data-testid="comment-type"]', type);
    
    if (isInternal) {
      await this.page.check('[data-testid="is-internal"]');
    }
    
    await this.page.click('[data-testid="submit-comment"]');
  }

  /**
   * Reply to a comment
   */
  async replyToComment(commentText: string, replyContent: string): Promise<void> {
    const commentItem = this.page.locator('[data-testid="comment-item"]').filter({ hasText: commentText });
    await commentItem.locator('[data-testid="reply-button"]').click();
    
    await this.page.fill('[data-testid="reply-content"]', replyContent);
    await this.page.click('[data-testid="submit-reply"]');
  }

  /**
   * Edit a comment
   */
  async editComment(originalText: string, newText: string): Promise<void> {
    const commentItem = this.page.locator('[data-testid="comment-item"]').filter({ hasText: originalText });
    await commentItem.locator('[data-testid="edit-button"]').click();
    
    await this.page.fill('[data-testid="edit-comment-content"]', newText);
    await this.page.click('[data-testid="save-edit"]');
  }

  /**
   * Delete a comment
   */
  async deleteComment(commentText: string): Promise<void> {
    const commentItem = this.page.locator('[data-testid="comment-item"]').filter({ hasText: commentText });
    await commentItem.locator('[data-testid="delete-button"]').click();
    await this.page.click('[data-testid="confirm-delete"]');
  }

  /**
   * Verify comment exists
   */
  async verifyCommentExists(commentText: string): Promise<void> {
    await expect(this.page.locator('[data-testid="comment-item"]').filter({ hasText: commentText })).toBeVisible();
  }

  /**
   * Verify comment does not exist
   */
  async verifyCommentNotExists(commentText: string): Promise<void> {
    await expect(this.page.locator('[data-testid="comment-item"]').filter({ hasText: commentText })).not.toBeVisible();
  }

  /**
   * Get comment count
   */
  async getCommentCount(): Promise<number> {
    return await this.page.locator('[data-testid="comment-item"]').count();
  }

  /**
   * Navigate to next page of comments
   */
  async goToNextPage(): Promise<void> {
    await this.page.click('[data-testid="next-page"]');
  }

  /**
   * Navigate to previous page of comments
   */
  async goToPreviousPage(): Promise<void> {
    await this.page.click('[data-testid="previous-page"]');
  }

  /**
   * Get current page number
   */
  async getCurrentPage(): Promise<string> {
    return await this.page.locator('[data-testid="current-page"]').textContent() || '1';
  }

  /**
   * Verify pagination is visible
   */
  async verifyPaginationVisible(): Promise<void> {
    await expect(this.page.locator('[data-testid="pagination"]')).toBeVisible();
  }

  /**
   * Verify comments section is visible
   */
  async verifyCommentsSectionVisible(): Promise<void> {
    await expect(this.page.locator('[data-testid="comments-section"]')).toBeVisible();
    await expect(this.page.locator('[data-testid="comments-container"]')).toBeVisible();
  }

  /**
   * Wait for comment to appear
   */
  async waitForComment(commentText: string, timeout: number = 5000): Promise<void> {
    await expect(this.page.locator('[data-testid="comment-item"]').filter({ hasText: commentText })).toBeVisible({ timeout });
  }

  /**
   * Wait for comment to be removed
   */
  async waitForCommentRemoved(commentText: string, timeout: number = 5000): Promise<void> {
    await expect(this.page.locator('[data-testid="comment-item"]').filter({ hasText: commentText })).not.toBeVisible({ timeout });
  }
}
