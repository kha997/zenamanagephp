import { renderHook } from '@testing-library/react';
import { useTaskTransitionValidation } from '../useTaskTransitionValidation';
import type { Task } from '../../types';

describe('useTaskTransitionValidation', () => {
  const createMockTask = (status: string): Task => ({
    id: 'task-123',
    title: 'Test Task',
    status: status as any,
    created_at: new Date().toISOString(),
    updated_at: new Date().toISOString(),
  });

  it('should allow valid forward transition', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('backlog');
    const validation = result.current.canMoveToStatus(task, 'in_progress');
    expect(validation.allowed).toBe(true);
    expect(validation.reason).toBeUndefined();
  });

  it('should block invalid transition', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('backlog');
    const validation = result.current.canMoveToStatus(task, 'done');
    expect(validation.allowed).toBe(false);
    expect(validation.reason).toBeDefined();
    expect(validation.reason).toContain('Cannot move from');
  });

  it('should allow transition from in_progress to backlog', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('in_progress');
    const validation = result.current.canMoveToStatus(task, 'backlog');
    expect(validation.allowed).toBe(true);
  });

  it('should allow transition from blocked to in_progress', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('blocked');
    const validation = result.current.canMoveToStatus(task, 'in_progress');
    expect(validation.allowed).toBe(true);
  });

  it('should allow transition from done to in_progress (reopen)', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('done');
    const validation = result.current.canMoveToStatus(task, 'in_progress');
    expect(validation.allowed).toBe(true);
  });

  it('should allow transition from canceled to backlog (reactivate)', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('canceled');
    const validation = result.current.canMoveToStatus(task, 'backlog');
    expect(validation.allowed).toBe(true);
  });

  it('should return correct reason for blocked transition', () => {
    const { result } = renderHook(() => useTaskTransitionValidation());
    const task = createMockTask('backlog');
    const validation = result.current.canMoveToStatus(task, 'done');
    expect(validation.allowed).toBe(false);
    expect(validation.reason).toContain('backlog');
    expect(validation.reason).toContain('done');
    expect(validation.reason).toContain('Allowed');
  });
});
