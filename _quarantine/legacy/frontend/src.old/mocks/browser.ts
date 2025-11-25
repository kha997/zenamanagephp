import { setupWorker } from 'msw/browser';
import { handlers } from './handlers';

// Thiết lập MSW worker cho môi trường trình duyệt
export const worker = setupWorker(...handlers);

// Khởi động worker trong chế độ phát triển
if (import.meta.env.DEV) {
  worker.start({
    onUnhandledRequest: 'warn',
    serviceWorker: {
      url: '/mockServiceWorker.js'
    }
  });
}