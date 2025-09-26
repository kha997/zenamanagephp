import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [],
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
