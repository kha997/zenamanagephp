import { Page, expect } from '@playwright/test';

/**
 * Attachment Helper for E2E Tests
 * Provides methods for testing file attachment functionality
 */
export class AttachmentHelper {
  constructor(private page: Page) {}

  /**
   * Upload a file attachment
   */
  async uploadAttachment(filePath: string, name: string, category: string = 'general'): Promise<void> {
    await this.page.click('[data-testid="upload-button"]');
    await this.page.fill('[data-testid="attachment-name"]', name);
    await this.page.selectOption('[data-testid="attachment-category"]', category);
    await this.page.setInputFiles('[data-testid="file-input"]', filePath);
    await this.page.click('[data-testid="submit-upload"]');
  }

  /**
   * Download an attachment
   */
  async downloadAttachment(attachmentName: string): Promise<void> {
    const attachmentItem = this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName });
    await attachmentItem.locator('[data-testid="download-button"]').click();
  }

  /**
   * Delete an attachment
   */
  async deleteAttachment(attachmentName: string): Promise<void> {
    const attachmentItem = this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName });
    await attachmentItem.locator('[data-testid="delete-attachment-button"]').click();
    await this.page.click('[data-testid="confirm-delete-attachment"]');
  }

  /**
   * Preview an attachment
   */
  async previewAttachment(attachmentName: string): Promise<void> {
    const attachmentItem = this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName });
    await attachmentItem.locator('[data-testid="preview-button"]').click();
  }

  /**
   * Verify attachment exists
   */
  async verifyAttachmentExists(attachmentName: string): Promise<void> {
    await expect(this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName })).toBeVisible();
  }

  /**
   * Verify attachment does not exist
   */
  async verifyAttachmentNotExists(attachmentName: string): Promise<void> {
    await expect(this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName })).not.toBeVisible();
  }

  /**
   * Get attachment count
   */
  async getAttachmentCount(): Promise<number> {
    return await this.page.locator('[data-testid="attachment-item"]').count();
  }

  /**
   * Verify attachment manager is visible
   */
  async verifyAttachmentManagerVisible(): Promise<void> {
    await expect(this.page.locator('[data-testid="attachment-manager"]')).toBeVisible();
    await expect(this.page.locator('[data-testid="upload-button"]')).toBeVisible();
  }

  /**
   * Wait for attachment to appear
   */
  async waitForAttachment(attachmentName: string, timeout: number = 10000): Promise<void> {
    await expect(this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName })).toBeVisible({ timeout });
  }

  /**
   * Wait for attachment to be removed
   */
  async waitForAttachmentRemoved(attachmentName: string, timeout: number = 5000): Promise<void> {
    await expect(this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName })).not.toBeVisible({ timeout });
  }

  /**
   * Get attachment details
   */
  async getAttachmentDetails(attachmentName: string): Promise<{ name: string; size: string; category: string; uploadDate: string }> {
    const attachmentItem = this.page.locator('[data-testid="attachment-item"]').filter({ hasText: attachmentName });
    
    return {
      name: await attachmentItem.locator('[data-testid="attachment-name"]').textContent() || '',
      size: await attachmentItem.locator('[data-testid="attachment-size"]').textContent() || '',
      category: await attachmentItem.locator('[data-testid="attachment-category"]').textContent() || '',
      uploadDate: await attachmentItem.locator('[data-testid="attachment-date"]').textContent() || ''
    };
  }

  /**
   * Filter attachments by category
   */
  async filterByCategory(category: string): Promise<void> {
    await this.page.selectOption('[data-testid="category-filter"]', category);
    await this.page.click('[data-testid="apply-filters"]');
  }

  /**
   * Search attachments
   */
  async searchAttachments(searchTerm: string): Promise<void> {
    await this.page.fill('[data-testid="attachment-search"]', searchTerm);
    await this.page.click('[data-testid="search-attachments"]');
  }

  /**
   * Verify upload progress
   */
  async verifyUploadProgress(): Promise<void> {
    await expect(this.page.locator('[data-testid="upload-progress"]')).toBeVisible();
  }

  /**
   * Wait for upload to complete
   */
  async waitForUploadComplete(timeout: number = 30000): Promise<void> {
    await expect(this.page.locator('[data-testid="upload-progress"]')).not.toBeVisible({ timeout });
  }

  /**
   * Verify file size validation
   */
  async verifyFileSizeError(): Promise<void> {
    await expect(this.page.locator('[data-testid="file-size-error"]')).toBeVisible();
  }

  /**
   * Verify file type validation
   */
  async verifyFileTypeError(): Promise<void> {
    await expect(this.page.locator('[data-testid="file-type-error"]')).toBeVisible();
  }

  /**
   * Close upload modal
   */
  async closeUploadModal(): Promise<void> {
    await this.page.click('[data-testid="close-upload-modal"]');
  }

  /**
   * Cancel upload
   */
  async cancelUpload(): Promise<void> {
    await this.page.click('[data-testid="cancel-upload"]');
  }
}
