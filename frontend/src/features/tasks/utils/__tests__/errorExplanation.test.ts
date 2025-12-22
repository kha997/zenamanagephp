import { getErrorExplanation } from '../errorExplanation';
import type { Task } from '../../types';

describe('getErrorExplanation', () => {
  const mockTask: Task = {
    id: 'task-123',
    title: 'Test Task',
    status: 'backlog',
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  };

  it('should return correct explanation for dependencies_incomplete', () => {
    const error = {
      code: 'dependencies_incomplete',
      message: 'Cannot start task: one or more dependencies are not completed',
      details: {
        dependencies: ['task-456', 'task-789']
      }
    };
    const result = getErrorExplanation(error, mockTask, 'in_progress');
    expect(result.title).toBe('Cannot Start Task');
    expect(result.description).toBe('This task has dependencies that must be completed first.');
    expect(result.relatedTasks).toEqual(['task-456', 'task-789']);
    expect(result.actionButton?.label).toBe('View Dependencies');
  });

  it('should return correct explanation for project_status_restricted', () => {
    const error = {
      code: 'project_status_restricted',
      message: 'Cannot perform this operation when project is in archived status',
      details: {
        project_id: 'project-123',
        project_name: 'Test Project',
        project_status: 'archived',
        required_statuses: ['planning', 'active']
      }
    };
    const result = getErrorExplanation(error, mockTask, 'in_progress');
    expect(result.title).toBe('Project Status Restriction');
    expect(result.description).toContain('Test Project');
    expect(result.description).toContain('archived');
    expect(result.actionButton?.label).toBe('View Project');
  });

  it('should return correct explanation for invalid_transition', () => {
    const error = {
      code: 'invalid_transition',
      message: 'Cannot transition from backlog to done',
      details: {
        from_status: 'backlog',
        to_status: 'done',
        allowed_transitions: ['in_progress', 'canceled']
      }
    };
    const result = getErrorExplanation(error, mockTask, 'done');
    expect(result.title).toBe('Invalid Status Transition');
    expect(result.description).toBe('Cannot transition from backlog to done');
    expect(result.solutions).toBeDefined();
  });

  it('should return correct explanation for optimistic_lock_conflict', () => {
    const error = {
      code: 'CONFLICT',
      message: 'Task has been modified by another user. Please refresh and try again.',
      details: {}
    };
    const result = getErrorExplanation(error, mockTask, 'in_progress');
    expect(result.title).toBe('Task Was Modified');
    expect(result.description).toBe('Another user has modified this task. Please refresh and try again.');
    expect(result.actionButton?.label).toBe('Refresh');
  });

  it('should return correct explanation for dependents_active', () => {
    const error = {
      code: 'dependents_active',
      message: 'Task has 2 active dependent task(s)',
      details: {
        dependents: ['task-456', 'task-789'],
        count: 2
      }
    };
    const result = getErrorExplanation(error, mockTask, 'canceled');
    expect(result.title).toBe('Active Dependents Warning');
    expect(result.relatedTasks).toEqual(['task-456', 'task-789']);
  });

  it('should return default explanation for unknown error', () => {
    const error = {
      code: 'UNKNOWN_ERROR',
      message: 'Something went wrong',
      details: {}
    };
    const result = getErrorExplanation(error, mockTask, 'in_progress');
    expect(result.title).toBe('Cannot Move Task');
    expect(result.description).toBe('Something went wrong');
    expect(result.solutions).toBeDefined();
  });
});
