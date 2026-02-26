import ReactDOM from 'react-dom/client';
import { StrictMode } from 'react';
import { AppRoot } from './AppRoot';

// Render
ReactDOM.createRoot(document.getElementById('root')!).render(
  <StrictMode>
    <AppRoot />
  </StrictMode>
);
