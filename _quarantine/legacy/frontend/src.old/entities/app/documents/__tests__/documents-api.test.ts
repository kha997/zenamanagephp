import { describe, it, expect, vi } from 'vitest';
import type { Document, DocumentVersion, DocumentActivity } from '../types';

// Mock the apiClient
vi.mock('../../../../shared/api/client', () => ({
  apiClient: {
    get: vi.fn(),
    post: vi.fn(),
    put: vi.fn(),
    delete: vi.fn(),
  }
}));

import { documentsApi } from '../api';
import { apiClient } from '../../../../shared/api/client';

describe('Documents API Adapters', () => {
  describe('toDocument adapter', () => {
    it('should normalize document with full data', async () => {
      const mockResponse = {
        data: {
          data: {
            id: 1,
            name: 'Test Document',
            title: 'Test Document',
            file_name: 'test.pdf',
            filename: 'test.pdf',
            original_name: 'original.pdf',
            mime_type: 'application/pdf',
            file_type: 'application/pdf',
            file_size: 1024000,
            size: 1024000,
            file_path: '/documents/test.pdf',
            path: '/documents/test.pdf',
            url: 'https://example.com/documents/test.pdf',
            project_id: 1,
            project: { id: 1, name: 'Project 1' },
            uploaded_by: 1,
            uploader: { id: 1, name: 'John Doe' },
            uploaded_at: '2024-01-01T00:00:00Z',
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
            tags: ['tag1', 'tag2'],
            description: 'Test description',
            version: 1,
            currentVersion: { version_number: 1 },
            is_public: true,
            download_count: 5,
            last_accessed_at: '2024-01-01T00:00:00Z'
          }
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocument(1);
      
      expect(result.data).toMatchObject({
        id: 1,
        name: 'Test Document',
        filename: 'test.pdf',
        original_filename: 'original.pdf',
        mime_type: 'application/pdf',
        size: 1024000,
        project_id: 1,
        project_name: 'Project 1',
        uploaded_by: 1,
        uploaded_by_name: 'John Doe',
        tags: ['tag1', 'tag2'],
        description: 'Test description',
        version: 1,
        is_public: true,
        download_count: 5
      });
    });

    it('should handle legacy field names', async () => {
      const mockResponse = {
        data: {
          data: {
            id: 1,
            title: 'Test Document',
            file_name: 'test.pdf',
            file_type: 'application/pdf',
            file_size: 1024000,
            file_path: '/documents/test.pdf',
            created_by: 1,
            uploaded_by_name: 'Jane Doe',
            created_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
            tags: [],
            version: 1
          }
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocument(1);
      
      expect(result.data.name).toBe('Test Document');
      expect(result.data.filename).toBe('test.pdf');
      expect(result.data.mime_type).toBe('application/pdf');
      expect(result.data.size).toBe(1024000);
    });
  });

  describe('toDocumentVersion adapter', () => {
    it('should normalize version with full data', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: 1,
              version: 1,
              version_number: 1,
              filename: 'test.pdf',
              file_name: 'test.pdf',
              size: 1024000,
              file_size: 1024000,
              mime_type: 'application/pdf',
              file_type: 'application/pdf',
              checksum: 'abc123',
              file_hash: 'abc123',
              uploaded_at: '2024-01-01T00:00:00Z',
              created_at: '2024-01-01T00:00:00Z',
              uploaded_by: 1,
              created_by: 1,
              uploaded_by_name: 'John Doe',
              uploader: { name: 'John Doe' },
              change_description: 'Initial version',
              comment: 'Initial version',
              reverted_from_version: null,
              reverted_from_version_number: null
            }
          ]
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocumentVersions(1);
      
      expect(result.data[0]).toMatchObject({
        id: 1,
        version: 1,
        filename: 'test.pdf',
        size: 1024000,
        mime_type: 'application/pdf',
        checksum: 'abc123',
        uploaded_by: 1,
        uploaded_by_name: 'John Doe',
        change_description: 'Initial version'
      });
    });

    it('should handle legacy version fields', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: '1',
              version_number: 2,
              file_name: 'test-v2.pdf',
              file_type: 'application/pdf',
              file_size: 2048000,
              file_hash: 'xyz789',
              created_by: 2,
              user_name: 'Jane Doe',
              comment: 'Updated version',
              created_at: '2024-01-02T00:00:00Z'
            }
          ]
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocumentVersions(1);
      
      expect(result.data[0]).toMatchObject({
        id: 1,
        version: 2,
        filename: 'test-v2.pdf',
        mime_type: 'application/pdf',
        size: 2048000,
        checksum: 'xyz789',
        uploaded_by: 2,
        uploaded_by_name: 'Jane Doe',
        change_description: 'Updated version'
      });
    });

    it('should handle missing uploader name', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: 1,
              version: 1,
              filename: 'test.pdf',
              size: 1024000,
              mime_type: 'application/pdf',
              uploaded_by: 1,
              created_at: '2024-01-01T00:00:00Z'
            }
          ]
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocumentVersions(1);
      
      expect(result.data[0].uploaded_by_name).toBe('');
    });
  });

  describe('toDocumentActivity adapter', () => {
    it('should normalize activity with full data', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: 1,
              event_id: 'evt-123',
              action: 'upload',
              event: 'upload',
              actor_id: 1,
              user_id: 1,
              actor_name: 'John Doe',
              user_name: 'John Doe',
              metadata: { ip: '127.0.0.1' },
              payload: { ip: '127.0.0.1' },
              created_at: '2024-01-01T00:00:00Z',
              timestamp: '2024-01-01T00:00:00Z'
            }
          ]
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocumentActivity(1);
      
      expect(result.data[0]).toMatchObject({
        id: '1',
        action: 'upload',
        actor_id: 1,
        actor_name: 'John Doe',
        created_at: '2024-01-01T00:00:00Z'
      });
    });

    it('should handle different action types', async () => {
      const mockResponse = {
        data: {
          data: [
            { id: 1, event: 'download', user_id: 1, user_name: 'John Doe', timestamp: '2024-01-01T00:00:00Z' },
            { id: 2, event: 'approve', user_id: 1, user_name: 'Jane Doe', timestamp: '2024-01-02T00:00:00Z' },
            { id: 3, event: 'revert', user_id: 1, user_name: 'Bob Smith', timestamp: '2024-01-03T00:00:00Z' }
          ]
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocumentActivity(1);
      
      expect(result.data).toHaveLength(3);
      expect(result.data[0].action).toBe('download');
      expect(result.data[1].action).toBe('approve');
      expect(result.data[2].action).toBe('revert');
    });

    it('should handle missing actor name', async () => {
      const mockResponse = {
        data: {
          data: [
            {
              id: 1,
              action: 'upload',
              actor_id: 1,
              created_at: '2024-01-01T00:00:00Z'
            }
          ]
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocumentActivity(1);
      
      expect(result.data[0].actor_name).toBe('');
    });
  });

  describe('getDocument with versions and activity', () => {
    it('should include versions and activity when available', async () => {
      const mockResponse = {
        data: {
          data: {
            id: 1,
            name: 'Test Document',
            filename: 'test.pdf',
            mime_type: 'application/pdf',
            size: 1024000,
            uploaded_by: 1,
            uploaded_by_name: 'John Doe',
            uploaded_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
            tags: [],
            version: 1,
            versions: [
              {
                id: 1,
                version: 1,
                filename: 'test.pdf',
                size: 1024000,
                mime_type: 'application/pdf',
                uploaded_at: '2024-01-01T00:00:00Z',
                uploaded_by: 1,
                uploaded_by_name: 'John Doe',
                change_description: 'Initial version'
              }
            ],
            activity: [
              {
                id: 1,
                action: 'upload',
                actor_id: 1,
                actor_name: 'John Doe',
                created_at: '2024-01-01T00:00:00Z'
              }
            ]
          }
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocument(1);
      
      expect(result.data.versions).toBeDefined();
      expect(result.data.versions).toHaveLength(1);
      expect(result.data.activity).toBeDefined();
      expect(result.data.activity).toHaveLength(1);
    });

    it('should handle missing versions and activity', async () => {
      const mockResponse = {
        data: {
          data: {
            id: 1,
            name: 'Test Document',
            filename: 'test.pdf',
            mime_type: 'application/pdf',
            size: 1024000,
            uploaded_by: 1,
            uploaded_by_name: 'John Doe',
            uploaded_at: '2024-01-01T00:00:00Z',
            updated_at: '2024-01-01T00:00:00Z',
            tags: [],
            version: 1
          }
        }
      };

      vi.mocked(apiClient.get as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.getDocument(1);
      
      expect(result.data.versions).toBeUndefined();
      expect(result.data.activity).toBeUndefined();
    });
  });

  describe('uploadNewVersion return type', () => {
    it('should return DocumentVersion', async () => {
      const mockResponse = {
        data: {
          data: {
            id: 2,
            version: 2,
            filename: 'test-v2.pdf',
            size: 2048000,
            mime_type: 'application/pdf',
            uploaded_at: '2024-01-02T00:00:00Z',
            uploaded_by: 1,
            uploaded_by_name: 'John Doe',
            change_description: 'Updated version'
          }
        }
      };

      const formData = new FormData();
      formData.append('file', new File([''], 'test.pdf'));

      vi.mocked(apiClient.post as any).mockResolvedValue(mockResponse);

      const result = await documentsApi.uploadNewVersion(1, new File([''], 'test.pdf'), 'Updated');
      
      expect(result.data).toMatchObject({
        id: 2,
        version: 2,
        filename: 'test-v2.pdf',
        size: 2048000,
        mime_type: 'application/pdf',
        change_description: 'Updated version'
      });
    });
  });
});

