import { authHandlers } from './auth';
import { projectHandlers } from './projects';
import { taskHandlers } from './tasks';
import { userHandlers } from './users';
import { notificationHandlers } from './notifications';

// Tập trung tất cả các API handlers
export const handlers = [
  ...authHandlers,
  ...projectHandlers,
  ...taskHandlers,
  ...userHandlers,
  ...notificationHandlers,
];