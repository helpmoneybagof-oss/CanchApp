/**
 * useRealtime.ts
 *
 * Composable para suscribirse a canales de Reverb (WebSocket) y recargar
 * datos de Inertia automáticamente cuando ocurren eventos en tiempo real.
 *
 * Uso:
 *   // Canal público (slots del calendario)
 *   useRealtimeSlots(courtId, () => reloadSlots())
 *
 *   // Canal admin (reservas, pagos)
 *   useRealtimeAdmin(['reservations', 'stats'])
 *
 *   // Canal privado de usuario (estado de pago)
 *   useRealtimeUser(userId, ['reservations'])
 */

import { router } from '@inertiajs/vue3';
import { onMounted, onUnmounted } from 'vue';

/** Recarga solo las props especificadas de la página actual con Inertia */
function reloadOnly(only: string[]) {
    router.reload({ only });
}

/**
 * Suscribe al canal privado del admin para recibir eventos de reservas y pagos.
 * Llama a router.reload({ only }) cuando llega cualquier evento relevante.
 *
 * @param only  Props de Inertia a recargar (ej: ['reservations', 'stats'])
 * @param onEvent  Callback opcional para lógica adicional por evento
 */
export function useRealtimeAdmin(
    only: string[],
    onEvent?: (event: string, data: any) => void,
) {
    let channel: any = null;

    onMounted(() => {
        if (!window.Echo) return;

        channel = window.Echo.private('admin');

        channel
            .listen('.reservation.created', (data: any) => {
                reloadOnly(only);
                onEvent?.('reservation.created', data);
            })
            .listen('.reservation.cancelled', (data: any) => {
                reloadOnly(only);
                onEvent?.('reservation.cancelled', data);
            })
            .listen('.payment.proof_submitted', (data: any) => {
                reloadOnly(only);
                onEvent?.('payment.proof_submitted', data);
            })
            .listen('.payment.approved', (data: any) => {
                reloadOnly(only);
                onEvent?.('payment.approved', data);
            })
            .listen('.payment.rejected', (data: any) => {
                reloadOnly(only);
                onEvent?.('payment.rejected', data);
            });
    });

    onUnmounted(() => {
        if (channel) window.Echo?.leave('admin');
    });
}

/**
 * Suscribe al canal privado del usuario autenticado para recibir
 * notificaciones de aprobación/rechazo de pago.
 *
 * @param userId  ID del usuario autenticado
 * @param only    Props de Inertia a recargar
 * @param onEvent Callback opcional
 */
export function useRealtimeUser(
    userId: number,
    only: string[],
    onEvent?: (event: string, data: any) => void,
) {
    let channel: any = null;

    onMounted(() => {
        if (!window.Echo || !userId) return;

        channel = window.Echo.private(`user.${userId}`);

        channel
            .listen('.payment.approved', (data: any) => {
                reloadOnly(only);
                onEvent?.('payment.approved', data);
            })
            .listen('.payment.rejected', (data: any) => {
                reloadOnly(only);
                onEvent?.('payment.rejected', data);
            })
            .listen('.reservation.cancelled', (data: any) => {
                reloadOnly(only);
                onEvent?.('reservation.cancelled', data);
            });
    });

    onUnmounted(() => {
        if (channel) window.Echo?.leave(`user.${userId}`);
    });
}

/**
 * Suscribe al canal público de slots de una cancha para recibir
 * actualizaciones de disponibilidad en tiempo real.
 *
 * @param courtId   ID de la cancha
 * @param onChanged Callback llamado cuando cambia el estado de slots
 */
export function useRealtimeSlots(
    courtId: number,
    onChanged: (data: { court_id: number; date: string }) => void,
) {
    let channel: any = null;

    onMounted(() => {
        if (!window.Echo || !courtId) return;

        channel = window.Echo.channel(`slots.${courtId}`);
        channel.listen('.slot.changed', (data: any) => {
            onChanged(data);
        });
    });

    onUnmounted(() => {
        if (channel) window.Echo?.leave(`slots.${courtId}`);
    });
}
