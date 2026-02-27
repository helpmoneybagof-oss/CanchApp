import { ref } from 'vue';

const VAPID_PUBLIC_KEY = import.meta.env.VITE_VAPID_PUBLIC_KEY as string;

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

/**
 * Registrar la suscripción push en el backend.
 */
async function saveSubscription(subscription: PushSubscription): Promise<void> {
    const key = subscription.getKey('p256dh');
    const auth = subscription.getKey('auth');

    await fetch('/api/push/subscribe', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '',
        },
        body: JSON.stringify({
            endpoint: subscription.endpoint,
            p256dh:   key   ? btoa(String.fromCharCode(...new Uint8Array(key)))   : '',
            auth:     auth  ? btoa(String.fromCharCode(...new Uint8Array(auth)))  : '',
        }),
    });
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

        const registration = await navigator.serviceWorker.ready;

        // Verificar si ya hay una suscripción activa
        let subscription = await registration.pushManager.getSubscription();

        if (!subscription) {
            subscription = await registration.pushManager.subscribe({
                userVisibleOnly:      true,
                applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC_KEY),
            });
        }

        await saveSubscription(subscription);
    } catch (error) {
        // El usuario rechazó o hay un error — no hacer nada
        console.warn('[Push] No se pudo suscribir:', error);
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
