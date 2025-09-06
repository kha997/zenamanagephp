import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App.tsx';
import './index.css';

// Khởi động MSW worker trong development
// Comment out phần này để tắt MSW
/*
if (import.meta.env.DEV) {
  import('./mocks/browser').then(({ worker }) => {
    worker.start({
      onUnhandledRequest: 'warn'
    }).then(() => {
      console.log('🔧 MSW Mock API đã được khởi động');
    });
  });
}
*/

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <App />
  </React.StrictMode>,
);