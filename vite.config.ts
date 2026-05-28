import { fileURLToPath, URL } from 'node:url';

import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';
import symfonyPlugin from 'vite-plugin-symfony';

export default defineConfig({
    plugins: [react(), symfonyPlugin()],
    build: {
        rollupOptions: {
            input: {
                app: './assets/main.tsx',
            },
        },
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./assets', import.meta.url)),
        },
    },
    server: {
        cors: true,
        strictPort: true,
        port: 5173,
    },
});
