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
    // server: {
    //     host: "0.0.0.0", // Allow Vite to listen on all network interfaces
    //     port: 5174, // Or any available port
    //     hmr: {
    //         host: "192.168.254.116", // Replace with your local machine's IP address
    //     },
    //     headers: {
    //         "Access-Control-Allow-Origin": "*",
    //     },
    // },
});
