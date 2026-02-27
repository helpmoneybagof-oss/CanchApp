<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Check, CheckCircle, Clock, Goal, ShoppingBag, X } from 'lucide-vue-next';
import { ref } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
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
    created_at: string;
    user: { id: number; name: string; email: string; phone: string | null };
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

const cancelling = ref(false);
const paying = ref(false);

function cancel() {
    if (!confirm(`¿Cancelar la reserva de ${props.reservation.user.name}?`)) return;
    cancelling.value = true;
    router.patch(`/admin/reservations/${props.reservation.id}/cancel`,
        { reason: 'Cancelado por administrador' },
        {
            onSuccess: () => toast.success('Reserva cancelada.', '¡Listo!'),
            onError: () => toast.error('No se pudo cancelar.', 'Error'),
            onFinish: () => { cancelling.value = false; },
        }
    );
}

function markAsPaid() {
    paying.value = true;
    router.patch(`/admin/reservations/${props.reservation.id}/pay`, {},
        {
            onSuccess: () => toast.success('Reserva marcada como pagada.', '¡Listo!'),
            onError: () => toast.error('No se pudo actualizar.', 'Error'),
            onFinish: () => { paying.value = false; },
        }
    );
}
</script>

<template>
    <Head :title="`Reserva #${reservation.confirmation_code}`" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">

            <!-- Back -->
            <button @click="router.visit('/admin/reservations')"
                class="mb-5 flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition">
                <ArrowLeft class="h-4 w-4" /> Volver a reservas
            </button>

            <!-- Header -->
            <div class="mb-5 flex items-start justify-between gap-3">
                <div>
                    <p class="text-xs text-muted-foreground font-mono">Código de reserva</p>
                    <p class="text-2xl font-bold tracking-widest text-primary">{{ reservation.confirmation_code }}</p>
                    <p class="text-xs text-muted-foreground mt-0.5">Creada el {{ reservation.created_at }}</p>
                </div>
                <div class="flex flex-col items-end gap-1.5">
                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusColor[reservation.status]">
                        {{ statusLabel[reservation.status] }}
                    </span>
                    <span class="rounded-full px-3 py-1 text-xs font-semibold"
                        :class="reservation.payment_status === 'paid'
                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                            : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'">
                        {{ reservation.payment_status === 'paid' ? 'Pagado' : 'Sin pagar' }}
                    </span>
                </div>
            </div>

            <!-- Cliente -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">Cliente</h2>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10">
                        <span class="text-base font-bold text-primary">{{ reservation.user.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div>
                        <p class="font-semibold text-foreground">{{ reservation.user.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ reservation.user.email }}</p>
                        <p v-if="reservation.user.phone" class="text-xs text-muted-foreground">{{ reservation.user.phone }}</p>
                    </div>
                    <a :href="`/admin/users/${reservation.user.id}`"
                        class="ml-auto text-xs text-primary hover:underline">
                        Ver perfil →
                    </a>
                </div>
            </div>

            <!-- Cancha y horario -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <Goal class="h-4 w-4 text-primary" />
                    Cancha y horario
                </h2>
                <div class="space-y-2.5">
                    <div v-if="reservation.court_name" class="flex items-center justify-between">
                        <span class="text-sm text-muted-foreground">Cancha</span>
                        <div class="text-right">
                            <p class="text-sm font-semibold text-foreground">{{ reservation.court_name }}</p>
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

            <!-- Consumibles -->
            <div v-if="reservation.items.length > 0" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                    <ShoppingBag class="h-4 w-4 text-primary" />
                    Consumibles pedidos
                </h2>
                <div class="space-y-3">
                    <div v-for="item in reservation.items" :key="item.id" class="flex items-center gap-3">
                        <div class="h-10 w-10 shrink-0 overflow-hidden rounded-xl bg-muted flex items-center justify-center">
                            <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-full w-full object-cover" />
                            <ShoppingBag v-else class="h-5 w-5 text-muted-foreground/30" />
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-medium text-foreground truncate">{{ item.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ item.quantity }} × ${{ item.unit_price.toLocaleString() }}</p>
                        </div>
                        <p class="text-sm font-semibold text-foreground shrink-0">${{ item.subtotal.toLocaleString() }}</p>
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
                        <span class="font-bold text-foreground">Total</span>
                        <span class="text-xl font-bold text-primary">${{ reservation.total_price.toLocaleString() }}</span>
                    </div>
                </div>
            </div>

            <!-- Notas -->
            <div v-if="reservation.notes" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-2 text-sm font-semibold text-foreground">Notas</h2>
                <p class="text-sm text-muted-foreground">{{ reservation.notes }}</p>
            </div>

            <!-- Acciones admin -->
            <div v-if="reservation.status !== 'cancelled' && reservation.status !== 'completed'"
                class="flex gap-3">
                <button v-if="reservation.payment_status === 'unpaid'"
                    @click="markAsPaid" :disabled="paying"
                    class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 py-3.5 text-sm font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-60 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400">
                    <Check class="h-4 w-4" />
                    {{ paying ? 'Procesando...' : 'Marcar como pagado' }}
                </button>
                <button @click="cancel" :disabled="cancelling"
                    class="flex flex-1 items-center justify-center gap-2 rounded-2xl border border-red-200 bg-red-50 py-3.5 text-sm font-semibold text-red-600 transition hover:bg-red-100 disabled:opacity-60 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                    <X class="h-4 w-4" />
                    {{ cancelling ? 'Cancelando...' : 'Cancelar reserva' }}
                </button>
            </div>

            <div v-else-if="reservation.status === 'cancelled'"
                class="flex items-center justify-center gap-2 rounded-2xl bg-red-50 py-3.5 text-sm font-medium text-red-600 dark:bg-red-900/20 dark:text-red-400">
                <X class="h-4 w-4" /> Reserva cancelada
            </div>
            <div v-else-if="reservation.status === 'completed'"
                class="flex items-center justify-center gap-2 rounded-2xl bg-emerald-50 py-3.5 text-sm font-medium text-emerald-700 dark:bg-emerald-900/20 dark:text-emerald-400">
                <CheckCircle class="h-4 w-4" /> Reserva completada
            </div>
        </div>
    </AppAdminLayout>
</template>
