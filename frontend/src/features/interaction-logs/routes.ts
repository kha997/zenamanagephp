import React, { lazy } from 'react';
import type { RouteObject } from 'react-router-dom';

// Lazy load các pages để tối ưu performance
const InteractionLogsList = lazy(() => import('./pages/InteractionLogsList').then(m => ({ default: m.InteractionLogsList })));
const InteractionLogDetail = lazy(() => import('./pages/InteractionLogDetail').then(m => ({ default: m.InteractionLogDetail })));
const CreateInteractionLog = lazy(() => import('./pages/CreateInteractionLog').then(m => ({ default: m.CreateInteractionLog })));

/**
 * Cấu hình routes cho module Interaction Logs
 * Bao gồm các route: danh sách, chi tiết, tạo mới
 */
export const interactionLogsRoutes: RouteObject[] = [
  {
    path: 'interaction-logs',
    children: [
      {
        index: true,
        element: React.createElement(InteractionLogsList),
      },
      {
        path: 'create',
        element: React.createElement(CreateInteractionLog),
      },
      {
        path: ':id',
        element: React.createElement(InteractionLogDetail),
      },
    ],
  },
];

/**
 * Route paths constants để sử dụng trong navigation
 */
export const INTERACTION_LOGS_ROUTES = {
  LIST: '/interaction-logs',
  CREATE: '/interaction-logs/create',
  DETAIL: (id: string | number) => `/interaction-logs/${id}`,
} as const;

/**
 * Breadcrumb configuration cho các trang interaction logs
 */
export const INTERACTION_LOGS_BREADCRUMBS = {
  LIST: [
    { label: 'Trang chủ', href: '/' },
    { label: 'Nhật ký tương tác', href: '/interaction-logs' },
  ],
  CREATE: [
    { label: 'Trang chủ', href: '/' },
    { label: 'Nhật ký tương tác', href: '/interaction-logs' },
    { label: 'Tạo mới', href: '/interaction-logs/create' },
  ],
  DETAIL: (id: string | number) => [
    { label: 'Trang chủ', href: '/' },
    { label: 'Nhật ký tương tác', href: '/interaction-logs' },
    { label: `Chi tiết #${id}`, href: `/interaction-logs/${id}` },
  ],
} as const;
