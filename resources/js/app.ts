import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { registerSW } from 'virtual:pwa-register';
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';
import '../css/app.css';
import { initializeTheme } from './composables/useAppearance';

// ─── PWA Install Prompt ───────────────────────────────────────────────────────
// Capturar el evento beforeinstallprompt para mostrarlo cuando queramos
let deferredInstallPrompt: any = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredInstallPrompt = e;
    // Notificar a la app que hay un prompt disponible
    window.dispatchEvent(new CustomEvent('pwa-installable'));
});

// Al instalar la app, pedir permisos de notificación automáticamente
window.addEventListener('appinstalled', async () => {
    deferredInstallPrompt = null;
    // Esperar un momento para que la app esté lista
    setTimeout(async () => {
        if ('Notification' in window && Notification.permission === 'default') {
            await Notification.requestPermission();
        }
    }, 2000);
});

// Exportar función global para mostrar el prompt de instalación
(window as any).showPWAInstallPrompt = async (): Promise<boolean> => {
    if (!deferredInstallPrompt) return false;
    deferredInstallPrompt.prompt();
    const { outcome } = await deferredInstallPrompt.userChoice;
    deferredInstallPrompt = null;
    return outcome === 'accepted';
};

// Verificar si ya está instalada como PWA
(window as any).isPWAInstalled = (): boolean => {
    return window.matchMedia('(display-mode: standalone)').matches ||
        (window.navigator as any).standalone === true;
};

// ─── Laravel Echo (Reverb WebSocket) ────────────────────────────────────────
// La configuración viene del servidor vía Inertia shared props (reverb)
// para evitar depender de variables VITE_* compiladas en build time.
window.Pusher = Pusher;

const reverbConfig = (window as any).__reverb__ ?? {};

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: reverbConfig.key ?? import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: reverbConfig.host ?? import.meta.env.VITE_REVERB_HOST ?? 'localhost',
    wsPort: reverbConfig.port ?? import.meta.env.VITE_REVERB_PORT ?? 8080,
    wssPort: reverbConfig.port ?? import.meta.env.VITE_REVERB_PORT ?? 8080,
    forceTLS: (reverbConfig.scheme ?? import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
    disableStats: true,
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#10b981',
    },
});

// Scroll al top en cada navegación de Inertia
router.on('navigate', () => {
    window.scrollTo({ top: 0, behavior: 'instant' });
});

// This will set light / dark mode on page load...
initializeTheme();

// Registrar el Service Worker (PWA + Push notifications)
if ('serviceWorker' in navigator) {
    registerSW({
        onRegistered(registration) {
            console.log('[PWA] Service Worker registrado:', registration?.scope);

            // Si ya está instalada como PWA standalone y no ha dado permiso, pedirlo
            const isStandalone = window.matchMedia('(display-mode: standalone)').matches ||
                (window.navigator as any).standalone === true;

            if (isStandalone && 'Notification' in window && Notification.permission === 'default') {
                setTimeout(() => Notification.requestPermission(), 1500);
            }
        },
        onRegisterError(error) {
            console.warn('[PWA] Error al registrar Service Worker:', error);
        },
    });
}
