import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { defineConfig } from 'vite';
import { VitePWA } from 'vite-plugin-pwa';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
        VitePWA({
            registerType: 'autoUpdate',
            injectRegister: null, // Lo registramos manualmente en app.ts con registerSW()
            strategies: 'injectManifest',
            srcDir: 'resources/js',
            filename: 'sw-push.ts',
            injectManifest: {
                globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
            },
            devOptions: {
                enabled: true,
                type: 'module',
            },
            manifest: {
                name: 'Cancha Sintética',
                short_name: 'Cancha',
                description: 'Reserva tu cancha sintética fácil y rápido',
                theme_color: '#10b981',
                background_color: '#ffffff',
                display: 'standalone',
                orientation: 'portrait-primary',
                scope: '/',
                start_url: '/dashboard',
                lang: 'es',
                categories: ['sports', 'lifestyle'],
                icons: [
                    {
                        src: '/icons/pwa-64.png',
                        sizes: '64x64',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/pwa-192.png',
                        sizes: '192x192',
                        type: 'image/png',
                    },
                    {
                        src: '/icons/pwa-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'any',
                    },
                    {
                        src: '/icons/pwa-maskable-512.png',
                        sizes: '512x512',
                        type: 'image/png',
                        purpose: 'maskable',
                    },
                ],
                screenshots: [
                    {
                        src: '/icons/screenshot-mobile.png',
                        sizes: '390x844',
                        type: 'image/png',
                        form_factor: 'narrow',
                        label: 'Pantalla principal',
                    },
                ],
                shortcuts: [
                    {
                        name: 'Ver disponibilidad',
                        short_name: 'Calendario',
                        description: 'Ver horarios disponibles',
                        url: '/calendar',
                        icons: [{ src: '/icons/pwa-96.png', sizes: '96x96' }],
                    },
                    {
                        name: 'Mis reservas',
                        short_name: 'Reservas',
                        description: 'Ver mis reservas activas',
                        url: '/reservations',
                        icons: [{ src: '/icons/pwa-96.png', sizes: '96x96' }],
                    },
                ],
            },
        }),
    ],
});
