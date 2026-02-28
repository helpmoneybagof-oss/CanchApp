<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, CheckCircle, Clock, Goal, ShoppingBag, X } from 'lucide-vue-next';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import { useToast } from '@/composables/useToast';

const toast = useToast();

interface ReservationItem {
    id: number;
    name: string;
    quantity: number;
    unit_price: number;
    subtotal: number;
    image_url: string | null;
}

interface ReservationDetail {
    id: number;
    confirmation_code: string;
    date: string;
    start_time: string;
    end_time: string;
    duration_hours: number;
    status: string;
    payment_status: string;
    court_price: number;
    consumables_price: number;
    total_price: number;
    notes: string | null;
    court_name: string | null;
    court_type: string | null;
    court_surface: string | null;
    items: ReservationItem[];
    can_cancel: boolean;
    created_at: string;
}

const props = defineProps<{ reservation: ReservationDetail }>();

const statusLabel: Record<string, string> = {
    pending: 'Pendiente', confirmed: 'Confirmada', cancelled: 'Cancelada', completed: 'Completada',
};
const statusColor: Record<string, string> = {
    pending:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    completed: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
};

const paymentLabel: Record<string, string> = {
    unpaid:          'Sin pagar',
    pending_payment: 'Pago pendiente',
    payment_review:  'En revisión',
    paid:            'Pagado',
    rejected:        'Comprobante rechazado',
};
const paymentColor: Record<string, string> = {
    unpaid:          'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    pending_payment: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    payment_review:  'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
    paid:            'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    rejected:        'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
};

const cancelling = ref(false);

function cancel() {
    if (!confirm('¿Estás seguro de que deseas cancelar esta reserva?')) return;
    cancelling.value = true;
    router.patch(`/reservations/${props.reservation.id}/cancel`, {}, {
        onSuccess: () => toast.success('Reserva cancelada.', '¡Listo!'),
        onError: (e) => toast.error(Object.values(e)[0] as string, 'Error'),
        onFinish: () => { cancelling.value = false; },
    });
}

import { ref, onMounted, onUnmounted } from 'vue';

// Realtime: recargar página cuando el admin aprueba o rechaza el pago
const page    = usePage();
const userId  = (page.props as any).auth?.user?.id;
let realtimeChannel: any = null;
onMounted(() => {
    if (!userId || !window.Echo) return;
    realtimeChannel = window.Echo.private(`user.${userId}`)
        .listen('.payment.approved', () => router.reload())
        .listen('.payment.rejected', () => router.reload())
        .listen('.reservation.cancelled', () => router.reload());
});
onUnmounted(() => {
    if (realtimeChannel && userId) window.Echo?.leave(`user.${userId}`);
});
</script>

<template>
    <Head :title="`Reserva #${reservation.confirmation_code}`" />
    <AppClientLayout>
        <div class="p-4 lg:p-6">

            <!-- Back -->
            <button @click="router.visit('/reservations')"
                class="mb-5 flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition">
                <ArrowLeft class="h-4 w-4" /> Mis reservas
            </button>

            <!-- Header con código y estado -->
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-muted-foreground font-mono">Código de reserva</p>
                    <p class="text-2xl font-bold tracking-widest text-primary">{{ reservation.confirmation_code }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Reservada el {{ reservation.created_at }}</p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusColor[reservation.status]">
                        {{ statusLabel[reservation.status] }}
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="paymentColor[reservation.payment_status] ?? 'bg-gray-100 text-gray-600'">
                        {{ paymentLabel[reservation.payment_status] ?? reservation.payment_status }}
                    </span>
                </div>
            </div>

            <!-- Información de la cancha y horario -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Goal class="h-4 w-4 text-primary" />
                    Cancha y horario
                </h2>
                <div class="space-y-2.5">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Cancha</span>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-foreground">{{ reservation.court_name ?? 'Sin asignar' }}</p>
                            <p v-if="reservation.court_type || reservation.court_surface"
                                class="text-xs text-muted-foreground">
                                {{ [reservation.court_type, reservation.court_surface].filter(Boolean).join(' · ') }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <CalendarDays class="h-3.5 w-3.5" /> Fecha
                        </span>
                        <span class="text-sm font-medium text-foreground">{{ reservation.date }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Clock class="h-3.5 w-3.5" /> Horario
                        </span>
                        <span class="text-sm font-medium text-foreground">
                            {{ reservation.start_time }} – {{ reservation.end_time }} ({{ reservation.duration_hours }}h)
                        </span>
                    </div>
                    <div class="flex items-center justify-between border-t border-border pt-2">
                        <span class="text-sm text-muted-foreground">Subtotal cancha</span>
                        <span class="text-sm font-semibold text-foreground">${{ reservation.court_price.toLocaleString() }}</span>
                    </div>
                </div>
            </div>

            <!-- Consumibles (si los hay) -->
            <div v-if="reservation.items.length > 0" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <ShoppingBag class="h-4 w-4 text-primary" />
                    Consumibles pedidos
                </h2>
                <div class="space-y-3">
                    <div v-for="item in reservation.items" :key="item.id"
                        class="flex items-center gap-3">
                        <!-- Imagen -->
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-muted flex items-center justify-center">
                            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-full w-full object-cover" />
                            <ShoppingBag v-else class="h-5 w-5 text-muted-foreground/30" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-foreground truncate">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">
                                {{ item.quantity }} × ${{ item.unit_price.toLocaleString() }}
                            </p>
                        </div>
                        <p class="text-sm font-semibold text-foreground shrink-0">
                            ${{ item.subtotal.toLocaleString() }}
                        </p>
                    </div>
                </div>
                <div class="mt-3 flex items-center justify-between border-t border-border pt-2">
                    <span class="text-sm text-muted-foreground">Subtotal consumibles</span>
                    <span class="text-sm font-semibold text-foreground">${{ reservation.consumables_price.toLocaleString() }}</span>
                </div>
            </div>

            <!-- Sin consumibles -->
            <div v-else class="mb-4 rounded-2xl border border-dashed border-border bg-card/50 p-4 text-center">
                <p class="text-sm text-muted-foreground">Sin consumibles en esta reserva</p>
            </div>

            <!-- Total -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span class="text-muted-foreground">Cancha ({{ reservation.duration_hours }}h)</span>
                        <span class="font-medium">${{ reservation.court_price.toLocaleString() }}</span>
                    </div>
                    <div v-if="reservation.consumables_price > 0" class="flex justify-between">
                        <span class="text-muted-foreground">Consumibles</span>
                        <span class="font-medium">${{ reservation.consumables_price.toLocaleString() }}</span>
                    </div>
                    <div class="flex items-center justify-between border-t border-border pt-2">
                        <span class="font-bold text-foreground">Total a pagar</span>
                        <span class="text-xl font-bold text-primary">${{ reservation.total_price.toLocaleString() }}</span>
                    </div>
                </div>
                <p class="mt-2 text-xs text-muted-foreground">* El pago se realiza en el lugar al usar la cancha.</p>
            </div>

            <!-- Notas -->
            <div v-if="reservation.notes" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-foreground">Notas</h2>
                <p class="text-sm text-muted-foreground">{{ reservation.notes }}</p>
            </div>

            <!-- Botón cancelar -->
            <button v-if="reservation.can_cancel"
                @click="cancel" :disabled="cancelling"
                class="flex w-full items-center justify-center gap-2 rounded-2xl border border-red-200 bg-red-50 py-3.5 text-sm font-semibold text-red-600 transition hover:bg-red-100 disabled:opacity-60 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                <X class="h-4 w-4" />
                {{ cancelling ? 'Cancelando...' : 'Cancelar reserva' }}
            </button>

            <!-- Estado cancelada/completada -->
            <div v-else-if="reservation.status === 'cancelled'"
                class="flex items-center justify-center gap-2 rounded-2xl bg-red-50 py-3.5 text-sm font-medium text-red-600 dark:bg-red-900/20 dark:text-red-400">
                <X class="h-4 w-4" /> Reserva cancelada
            </div>
            <div v-else-if="reservation.status === 'completed'"
                class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-50 py-3.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                <CheckCircle class="h-4 w-4" /> Reserva completada
            </div>
        </div>
    </AppClientLayout>
</template>
