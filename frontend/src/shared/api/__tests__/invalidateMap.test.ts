import { describe, it, expect, vi, beforeEach } from 'vitest';
import { QueryClient } from '@tanstack/react-query';
import { invalidateFor, createInvalidationContext, invalidateMap } from '../invalidateMap';

describe('invalidateMap', () => {
  let queryClient: QueryClient;
  let invalidateQueriesSpy: ReturnType<typeof vi.spyOn>;

  beforeEach(() => {
    queryClient = new QueryClient({
      defaultOptions: {
        queries: {
          retry: false,
        },
      },
    });
    invalidateQueriesSpy = vi.spyOn(queryClient, 'invalidateQueries');
  });

  describe('invalidateFor', () => {
    it('should invalidate queries for task.create action', () => {
      const context = createInvalidationContext(queryClient);
      invalidateFor('task.create', context);

      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['tasks'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['dashboard'] });
    });

    it('should invalidate queries for task.update with resourceId', () => {
      const context = createInvalidationContext(queryClient, {
        resourceId: '123',
      });
      invalidateFor('task.update', context);

      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['tasks'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['task'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['task', '123'] });
    });

    it('should invalidate queries for task.move with resourceId and projectId', () => {
      const context = createInvalidationContext(queryClient, {
        resourceId: '123',
        projectId: '456',
      });
      invalidateFor('task.move', context);

      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['tasks'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['task'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['dashboard'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['task', '123'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['tasks', 'project', '456'] });
    });

    it('should invalidate queries for project.update with resourceId', () => {
      const context = createInvalidationContext(queryClient, {
        resourceId: '789',
      });
      invalidateFor('project.update', context);

      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['project'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['projects'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['dashboard'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['project', '789'] });
    });

    it('should invalidate queries for document.delete', () => {
      const context = createInvalidationContext(queryClient, {
        resourceId: 'doc123',
      });
      invalidateFor('document.delete', context);

      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['documents'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['dashboard'] });
      expect(invalidateQueriesSpy).toHaveBeenCalledWith({ queryKey: ['documents', 'doc123'] });
    });

    it('should warn and return early for unknown action', () => {
      const consoleWarnSpy = vi.spyOn(console, 'warn').mockImplementation(() => {});
      const context = createInvalidationContext(queryClient);
      
      // @ts-expect-error - Testing invalid action
      invalidateFor('unknown.action', context);

      expect(consoleWarnSpy).toHaveBeenCalledWith(
        expect.stringContaining('No invalidation map for action: unknown.action')
      );
      expect(invalidateQueriesSpy).not.toHaveBeenCalled();
      
      consoleWarnSpy.mockRestore();
    });
  });

  describe('createInvalidationContext', () => {
    it('should create context with queryClient only', () => {
      const context = createInvalidationContext(queryClient);
      
      expect(context).toEqual({
        queryClient,
      });
    });

    it('should create context with all options', () => {
      const context = createInvalidationContext(queryClient, {
        tenantId: 'tenant1',
        resourceId: '123',
        projectId: '456',
      });
      
      expect(context).toEqual({
        queryClient,
        tenantId: 'tenant1',
        resourceId: '123',
        projectId: '456',
      });
    });
  });

  describe('invalidateMap coverage', () => {
    it('should have all task actions defined', () => {
      expect(invalidateMap['task.create']).toBeDefined();
      expect(invalidateMap['task.update']).toBeDefined();
      expect(invalidateMap['task.move']).toBeDefined();
      expect(invalidateMap['task.delete']).toBeDefined();
      expect(invalidateMap['task.bulkDelete']).toBeDefined();
      expect(invalidateMap['task.bulkUpdate']).toBeDefined();
      expect(invalidateMap['task.bulkAssign']).toBeDefined();
    });

    it('should have all project actions defined', () => {
      expect(invalidateMap['project.create']).toBeDefined();
      expect(invalidateMap['project.update']).toBeDefined();
      expect(invalidateMap['project.delete']).toBeDefined();
      expect(invalidateMap['project.archive']).toBeDefined();
      expect(invalidateMap['project.addTeamMember']).toBeDefined();
      expect(invalidateMap['project.removeTeamMember']).toBeDefined();
      expect(invalidateMap['project.uploadDocument']).toBeDefined();
    });

    it('should have all document actions defined', () => {
      expect(invalidateMap['document.create']).toBeDefined();
      expect(invalidateMap['document.update']).toBeDefined();
      expect(invalidateMap['document.delete']).toBeDefined();
      expect(invalidateMap['document.upload']).toBeDefined();
    });
  });
});

