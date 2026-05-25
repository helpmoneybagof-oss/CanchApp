<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock, CreditCard, Goal, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import { useRealtimeUser } from '@/composables/useRealtime';
import { useToast } from '@/composables/useToast';
import AppClientLayout from '@/layouts/AppClientLayout.vue';

interface Reservation {
    id: number;
    date: string;
    date_raw: string;
    start_time: string;
    end_time: string;
    duration_hours: number;
    status: string;
    payment_status: string;
    total_price: number;
    confirmation_code: string;
    can_cancel: boolean;
    court_name: string | null;
}

defineProps<{ reservations: Reservation[] }>();

const page = usePage();
const authUser = (page.props as any).auth?.user;

// Tiempo real: recarga reservas cuando el admin aprueba/rechaza el pago
useRealtimeUser(authUser?.id, ['reservations']);
const toast = useToast();
const cancellingId = ref<number | null>(null);
const showCancelModal = ref(false);
const selectedReservation = ref<Reservation | null>(null);

// Mostrar toast de confirmación de reserva si viene flash
onMounted(() => {
    const flash = (page.props as any).flash;
    if (flash?.type === 'success' && flash?.message) {
        toast.success(flash.message, '¡Listo!');
    }
});

function openCancelModal(reservation: Reservation) {
    selectedReservation.value = reservation;
    showCancelModal.value = true;
}

function confirmCancel() {
    if (!selectedReservation.value) return;
    cancellingId.value = selectedReservation.value.id;
    router.patch(`/reservations/${selectedReservation.value.id}/cancel`, {}, {
        onSuccess: () => {
            toast.success('Tu reserva fue cancelada exitosamente.', 'Cancelación exitosa');
        },
        onError: (errors) => {
            const msg = Object.values(errors)[0] as string;
            toast.error(msg, 'No se pudo cancelar');
        },
        onFinish: () => {
            cancellingId.value = null;
            showCancelModal.value = false;
        },
    });
}

const statusLabel: Record<string, string> = {
    pending: 'Pendiente',
    confirmed: 'Confirmada',
    cancelled: 'Cancelada',
    completed: 'Completada',
};

const statusColor: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    completed: 'bg-gray-100 text-gray-600',
};

const paymentLabel: Record<string, string> = {
    unpaid: 'Sin pagar',
    pending_payment: 'Pago pendiente',
    payment_review: 'En revisión',
    paid: 'Pagado',
    rejected: 'Comprobante rechazado',
};

const paymentColor: Record<string, string> = {
    unpaid: 'bg-orange-100 text-orange-700',
    pending_payment: 'bg-amber-100 text-amber-700',
    payment_review: 'bg-blue-100 text-blue-700',
    paid: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
};

function canPay(r: Reservation): boolean {
    return ['unpaid', 'rejected'].includes(r.payment_status) && r.status !== 'cancelled';
}
</script>

<template>
    <Head title="Mis Reservas" />

    <AppClientLayout>
        <div class="p-4 lg:p-6">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-foreground">Mis Reservas</h1>
                <p class="text-sm text-muted-foreground">Reservas activas y próximas</p>
            </div>

            <!-- Sin reservas -->
            <div
                v-if="reservations.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center"
            >
                <CalendarDays class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">No tienes reservas activas</p>
                <p class="mb-4 text-sm text-muted-foreground">¿Qué esperas para reservar tu cancha?</p>
                <a
                    href="/calendar"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                >
                    Ver disponibilidad
                </a>
            </div>

            <!-- Lista de reservas -->
            <div v-else class="space-y-3">
                <div
                    v-for="r in reservations"
                    :key="r.id"
                    @click="router.visit(`/reservations/${r.id}`)"
                    class="cursor-pointer rounded-2xl border border-border bg-card p-4 shadow-sm transition hover:border-primary/40 hover:shadow-md"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <div class="flex items-center gap-2 mb-1 flex-wrap">
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="statusColor[r.status]"
                                >
                                    {{ statusLabel[r.status] }}
                                </span>
                                <span
                                    class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                    :class="paymentColor[r.payment_status] ?? 'bg-gray-100 text-gray-600'"
                                >
                                    {{ paymentLabel[r.payment_status] ?? r.payment_status }}
                                </span>
                            </div>
                            <p class="text-xs text-muted-foreground font-mono">
                                #{{ r.confirmation_code }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-lg font-bold text-foreground">
                                ${{ r.total_price.toLocaleString() }}
                            </p>
                            <ChevronRight class="h-4 w-4 text-muted-foreground" />
                        </div>
                    </div>

                    <div class="space-y-1.5 mb-3">
                        <div v-if="r.court_name" class="flex items-center gap-2 text-sm text-foreground">
                            <Goal class="h-4 w-4 text-primary shrink-0" />
                            <span class="font-medium">{{ r.court_name }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-foreground">
                            <CalendarDays class="h-4 w-4 text-primary shrink-0" />
                            <span>{{ r.date }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-foreground">
                            <Clock class="h-4 w-4 text-primary shrink-0" />
                            <span>{{ r.start_time }} – {{ r.end_time }} ({{ r.duration_hours }}h)</span>
                        </div>
                    </div>

                    <div class="flex gap-2">
                        <!-- Botón pagar con Nequi -->
                        <a
                            v-if="canPay(r)"
                            :href="`/reservations/${r.id}/payment`"
                            @click.stop
                            class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-2 text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
                        >
                            <CreditCard class="h-4 w-4" />
                            Pagar con Nequi
                        </a>

                        <!-- Botón cancelar -->
                        <button
                            v-if="r.can_cancel"
                            @click.stop="openCancelModal(r)"
                            :disabled="cancellingId === r.id"
                            class="flex items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-600 transition hover:bg-red-100 disabled:opacity-50"
                            :class="canPay(r) ? 'w-auto' : 'w-full'"
                        >
                            <X class="h-4 w-4" />
                            {{ cancellingId === r.id ? 'Cancelando...' : 'Cancelar' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal cancelar -->
        <Teleport to="body">
            <div
                v-if="showCancelModal"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm lg:items-center"
                @click.self="showCancelModal = false"
            >
                <div class="w-full max-w-sm rounded-t-3xl bg-background p-6 shadow-xl lg:rounded-3xl">
                    <h3 class="mb-2 text-lg font-bold text-foreground">¿Cancelar reserva?</h3>
                    <p class="mb-6 text-sm text-muted-foreground">
                        Esta acción no se puede deshacer. Se liberará el horario reservado.
                    </p>
                    <div class="flex gap-3">
                        <button
                            @click="showCancelModal = false"
                            class="flex-1 rounded-xl border border-border py-2.5 text-sm font-medium text-foreground transition hover:bg-muted"
                        >
                            Volver
                        </button>
                        <button
                            @click="confirmCancel"
                            class="flex-1 rounded-xl bg-red-500 py-2.5 text-sm font-bold text-white transition hover:bg-red-600"
                        >
                            Sí, cancelar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppClientLayout>
</template>
