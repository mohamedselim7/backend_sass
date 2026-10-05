import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';
import path from 'node:path';

// Build config for the Inertia Admin bundle only. The user-facing React + Vite
// application keeps its own build in ../frontend.
export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/admin/app.tsx'],
            refresh: true,
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        alias: {
            '@admin': path.resolve(__dirname, 'resources/js/admin'),
        },
    },
});
