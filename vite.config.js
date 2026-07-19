import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/css/operator.css', 'resources/js/app.js', 'resources/js/money-format.js', 'resources/js/ai-lead-suggest.js', 'resources/js/ai-design-item-suggest.js', 'resources/js/ai-opportunity-summary.js'],
            refresh: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        port: 3000,
        hmr: {
            host: 'localhost',
        },
    },
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
    },
});
