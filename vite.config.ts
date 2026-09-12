import vue from '@vitejs/plugin-vue';
import autoprefixer from 'autoprefixer';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import tailwindcss from 'tailwindcss';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    resolve: {
        alias: {
            '@': path.resolve(__dirname, './resources/js'),
        },
    },
    css: {
        postcss: {
            plugins: [tailwindcss, autoprefixer],
        },
    },
    // DEPLOYMENT BACKUP (commented for local dev - original HMR host for shared VPS/network):
    // server: { host: "0.0.0.0", port: 5174, hmr: { host: "192.168.254.116" }, headers: { "Access-Control-Allow-Origin": "*" } }
    server: {
        host: "0.0.0.0", // Local dev on 5174 - SOMS (Hulagway stopped on this port per user request)
        port: 5174,
        hmr: {
            host: "localhost",
        },
        headers: {
            "Access-Control-Allow-Origin": "*",
        },
    },
});
