import { ref } from 'vue';

// Leer VAPID key desde PHP runtime (inyectada en app.blade.php) con fallback a Vite
const VAPID_PUBLIC_KEY: string =
    (window as any).__vapid_public_key__ || (import.meta.env.VITE_VAPID_PUBLIC_KEY as string) || '';

const isSupported = ref(
    typeof window !== 'undefined' &&
    'serviceWorker' in navigator &&
    'PushManager' in window &&
    'Notification' in window
);

const permission = ref<NotificationPermission>(
    typeof window !== 'undefined' && 'Notification' in window
        ? Notification.permission
        : 'denied'
);

/**
 * Convierte una clave VAPID base64url a Uint8Array para la API de Web Push.
 */
function urlBase64ToUint8Array(base64String: string): Uint8Array {
    const padding = '='.repeat((4 - (base64String.length % 4)) % 4);
    const base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
    const rawData = atob(base64);
    return Uint8Array.from([...rawData].map((c) => c.charCodeAt(0)));
}

function applicationServerKeyMatches(subscription: PushSubscription, expected: Uint8Array): boolean {
    const current = subscription.options.applicationServerKey;
    if (!current) return false;
    const a = new Uint8Array(current);
    if (a.length !== expected.length) return false;
    for (let i = 0; i < a.length; i++) {
        if (a[i] !== expected[i]) return false;
    }
    return true;
}

/**
 * Lee la cookie XSRF-TOKEN. Laravel la rota junto con la sesión, así que siempre devuelve
 * un token válido — a diferencia del <meta name="csrf-token"> que queda viejo tras login
 * cuando Inertia no recarga la página completa.
 */
function getXsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match ? decodeURIComponent(match[1]) : '';
}

/**
 * Registrar la suscripción push en el backend.
 */
async function saveSubscription(subscription: PushSubscription): Promise<void> {
    const key = subscription.getKey('p256dh');
    const auth = subscription.getKey('auth');

    const res = await fetch('/api/push/subscribe', {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-XSRF-TOKEN': getXsrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
            endpoint: subscription.endpoint,
            p256dh:   key   ? btoa(String.fromCharCode(...new Uint8Array(key)))   : '',
            auth:     auth  ? btoa(String.fromCharCode(...new Uint8Array(auth)))  : '',
        }),
    });

    if (!res.ok) {
        const text = await res.text().catch(() => '');
        console.warn('[Push] Error guardando suscripción:', res.status, text);
    }
}

/**
 * Solicitar permiso y suscribir al usuario a push notifications.
 * Se llama automáticamente desde el layout cuando el usuario está autenticado.
 */
export async function subscribeToPush(): Promise<void> {
    if (!isSupported.value) return;
    if (!VAPID_PUBLIC_KEY) return;

    try {
        // Solicitar permiso si aún no está concedido
        if (Notification.permission === 'default') {
            const result = await Notification.requestPermission();
            permission.value = result;
        }

        if (Notification.permission !== 'granted') return;

        // Obtener un service worker registration listo.
        // Nota: para poder usar PushManager de forma fiable, el SW debe estar *activo* y controlando la página.
        // `ready` garantiza eso.
        const registration = await navigator.serviceWorker.ready;

        console.log('[Push] SW state:', registration.active?.state, 'scope:', registration.scope);

        const newKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);

        let subscription = await registration.pushManager.getSubscription();

        // Si la suscripción local apunta a una VAPID distinta, hay que desuscribir
        // antes de re-suscribir — si no, el navegador lanza AbortError "push service error".
        if (subscription && !applicationServerKeyMatches(subscription, newKey)) {
            await subscription.unsubscribe().catch(() => {});
            subscription = null;
        }

        if (!subscription) {
            try {
                subscription = await registration.pushManager.subscribe({
                    userVisibleOnly:      true,
                    applicationServerKey: newKey as BufferSource,
                });
            } catch (subscribeErr: any) {
                // AbortError "push service error" suele indicar que Chrome tiene un endpoint colgado
                // (eg. una suscripción vieja a un FCM que devolvió 410 Gone). Retry tras unsubscribe forzado.
                if (subscribeErr?.name === 'AbortError') {
                    console.warn('[Push] AbortError en subscribe, intentando reset…');
                    const existing = await registration.pushManager.getSubscription();
                    if (existing) await existing.unsubscribe().catch(() => {});
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly:      true,
                        applicationServerKey: newKey as BufferSource,
                    });
                } else {
                    throw subscribeErr;
                }
            }
        }

        await saveSubscription(subscription);
    } catch (error) {
        console.warn('[Push] No se pudo suscribir:', error);
        throw error;
    }
}

/**
 * Muestra el diálogo de permiso de notificaciones explícitamente.
 * Útil para botones en la UI.
 */
export async function requestPushPermission(): Promise<NotificationPermission> {
    if (!isSupported.value) return 'denied';
    const result = await Notification.requestPermission();
    permission.value = result;
    if (result === 'granted') {
        await subscribeToPush();
    }
    return result;
}

export function usePush() {
    return { isSupported, permission, subscribeToPush, requestPushPermission };
}
