import {defineConfig, loadEnv} from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    // Derive base asset URL from APP_URL (no extra env key)
    const appUrl = (env.APP_URL || '').replace(/\/+$/, '');
    const base = appUrl ? `${appUrl}/build/` : '/build/';
    return {
        base,
        plugins: [
            laravel({
                input: ['resources/css/main.scss', 'resources/js/main.js'],
                refresh: true,
            }),
        ],
        resolve: {
            alias: {
                '@': '/resources/js',
            },
        },
        server: {
            hmr: {  host: 'localhost' },
            cors: {
                origin: '*',
                methods: ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
                allowedHeaders: ['Content-Type'],
                credentials: true,
            }
        },
        css: {
            // Hide Sass deprecation noise coming from dependencies (Bootstrap/AdminLTE)
            preprocessorOptions: {
                scss: {
                    quietDeps: true,
                    silenceDeprecations: ['import'],
                },
            },
        },
    };
});
