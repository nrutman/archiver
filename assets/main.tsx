import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { ArchiveApp } from './ArchiveApp';
import './styles/app.css';

const rootElement = document.getElementById('archiver-root');

if (!rootElement) {
    throw new Error('Could not find #archiver-root mount element.');
}

createRoot(rootElement).render(
    <StrictMode>
        <ArchiveApp />
    </StrictMode>,
);
