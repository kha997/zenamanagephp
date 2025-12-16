/**
 * PR #4: Type helpers for extracting types from OpenAPI-generated paths
 * 
 * These helpers extract request/response types from the generated OpenAPI types
 * to use in API clients and React Query hooks.
 */

import type { paths, components } from '../types/api';

// Extract response type for a specific endpoint
export type ApiResponse<
  Path extends keyof paths,
  Method extends keyof paths[Path]
> = paths[Path][Method] extends { responses: infer R }
  ? R extends { 200: { content: { 'application/json': infer D } } }
    ? D
    : R extends { 201: { content: { 'application/json': infer D } } }
    ? D
    : never
  : never;

// Extract request body type for a specific endpoint
export type ApiRequest<
  Path extends keyof paths,
  Method extends keyof paths[Path]
> = paths[Path][Method] extends { requestBody: { content: { 'application/json': infer D } } }
  ? D
  : paths[Path][Method] extends { requestBody?: never }
  ? never
  : never;

// Extract query parameters type
export type ApiQuery<
  Path extends keyof paths,
  Method extends keyof paths[Path]
> = paths[Path][Method] extends { parameters: infer P }
  ? P extends { query: infer Q }
    ? Q extends never
      ? never
      : Q
    : never
  : never;

// Extract path parameters type
export type ApiPathParams<
  Path extends keyof paths,
  Method extends keyof paths[Path]
> = paths[Path][Method] extends { parameters: { path: infer P } }
  ? P
  : never;

// Helper to extract component types
export type Task = components['schemas']['Task'];
export type TaskCreate = components['schemas']['TaskCreate'];
export type TaskUpdate = components['schemas']['TaskUpdate'];
export type Project = components['schemas']['Project'];
export type ProjectCreate = components['schemas']['ProjectCreate'];
export type User = components['schemas']['User'];
export type NavItem = components['schemas']['NavItem'];
export type PaginationMeta = components['schemas']['PaginationMeta'];

// Task API types
export type TasksListResponse = ApiResponse<'/app/tasks', 'get'>;
export type TaskGetResponse = ApiResponse<'/app/tasks/{id}', 'get'>;
export type TaskCreateResponse = ApiResponse<'/app/tasks', 'post'>;
export type TaskUpdateResponse = ApiResponse<'/app/tasks/{id}', 'put'>;
export type TaskMoveResponse = ApiResponse<'/app/tasks/{id}/move', 'patch'>;
export type TaskBulkDeleteResponse = ApiResponse<'/app/tasks/bulk-delete', 'post'>;
export type TaskBulkStatusResponse = ApiResponse<'/app/tasks/bulk-status', 'post'>;
export type TaskBulkAssignResponse = ApiResponse<'/app/tasks/bulk-assign', 'post'>;

export type TaskCreateRequest = ApiRequest<'/app/tasks', 'post'>;
export type TaskUpdateRequest = ApiRequest<'/app/tasks/{id}', 'put'>;
export type TaskMoveRequest = ApiRequest<'/app/tasks/{id}/move', 'patch'>;
export type TaskBulkDeleteRequest = ApiRequest<'/app/tasks/bulk-delete', 'post'>;
export type TaskBulkStatusRequest = ApiRequest<'/app/tasks/bulk-status', 'post'>;
export type TaskBulkAssignRequest = ApiRequest<'/app/tasks/bulk-assign', 'post'>;

// Extract query type directly from paths
export type TasksListQuery = paths['/app/tasks']['get']['parameters']['query'];
export type TaskPathParams = ApiPathParams<'/app/tasks/{id}', 'get'>;

// Project API types
export type ProjectsListResponse = ApiResponse<'/app/projects', 'get'>;
export type ProjectGetResponse = ApiResponse<'/app/projects/{id}', 'get'>;
export type ProjectCreateResponse = ApiResponse<'/app/projects', 'post'>;
export type ProjectUpdateResponse = ApiResponse<'/app/projects/{id}', 'put'>;

export type ProjectCreateRequest = ApiRequest<'/app/projects', 'post'>;
export type ProjectUpdateRequest = ApiRequest<'/app/projects/{id}', 'put'>;

// Navigation API types
export type NavigationResponse = ApiResponse<'/me/nav', 'get'>;
export type UserInfoResponse = ApiResponse<'/me', 'get'>;

