import React from 'react';
import ReactDOM from 'react-dom/client';
import AppShell from './app/AppShell';
import './index.css';

// Round 155: Global error handlers and E2E logging - must run before React renders
declare global {
  interface Window {
    __e2e_logs?: any[];
  }
}

if (typeof window !== 'undefined') {
  // Initialize E2E logs array if it doesn't exist
  if (!window.__e2e_logs) {
    window.__e2e_logs = [];
  }

  // Log entry point load
  window.__e2e_logs.push({
    scope: 'global',
    event: 'entry-loaded',
    url: window.location.href,
    ts: Date.now(),
  });

  // Global error handler - catches all unhandled errors
  window.addEventListener('error', (event) => {
    try {
      window.__e2e_logs?.push({
        scope: 'global',
        type: 'error',
        message: event.message,
        filename: event.filename,
        lineno: event.lineno,
        colno: event.colno,
        stack: (event.error && event.error.stack) || null,
        ts: Date.now(),
      });
    } catch {
      // Ignore errors in error handler
    }
  });

  // Unhandled promise rejection handler
  window.addEventListener('unhandledrejection', (event) => {
    try {
      window.__e2e_logs?.push({
        scope: 'global',
        type: 'unhandledrejection',
        reason: String((event as any).reason ?? 'unknown'),
        ts: Date.now(),
      });
    } catch {
      // Ignore errors in error handler
    }
  });
}

ReactDOM.createRoot(document.getElementById('root')!).render(
  <React.StrictMode>
    <AppShell />
  </React.StrictMode>,
);