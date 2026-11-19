import { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';

import { ArchiveApp } from './ArchiveApp';
import './styles/app.css';

const rootElement = document.getElementById('archiver-root');

if (!rootElement) {
    throw new Error('Could not find #archiver-root mount element.');
}

const maxFiles = Number(rootElement.dataset.maxFiles);
if (!Number.isSafeInteger(maxFiles) || maxFiles < 0) {
    throw new Error('Missing or invalid upload file limit. Refresh the page.');
}

createRoot(rootElement).render(
    <StrictMode>
        <ArchiveApp maxFiles={maxFiles} />
    </StrictMode>,
);
