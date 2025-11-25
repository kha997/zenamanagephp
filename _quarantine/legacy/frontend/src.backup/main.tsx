import React from 'react';
import ReactDOM from 'react-dom/client';
import AppShell from './app/AppShell';
import './index.css';

// Mount React app to #app element (from Laravel Blade entry point)
const rootElement = document.getElementById('app') || document.getElementById('root');
if (!rootElement) {
  throw new Error('React root element (#app or #root) not found');
}

ReactDOM.createRoot(rootElement).render(
  <React.StrictMode>
    <AppShell />
  </React.StrictMode>,
);