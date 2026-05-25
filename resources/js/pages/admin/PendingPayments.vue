<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CheckCircle, Eye, X } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import { useToast } from '@/composables/useToast';
import { useRealtimeAdmin } from '@/composables/useRealtime';

interface PaymentReservation {
    id: number;
    confirmation_code: string;
    date: string;
    start_time: string;
    end_time: string;
    total_price: number;
    payment_method: string;
    payment_reference: string | null;
    payment_proof_url: string | null;
    submitted_at: string;
    is_cancelled: boolean;
    user: { name: string; email: string; phone: string | null };
}

const props = defineProps<{
    reservations: PaymentReservation[];
}>();

const page = usePage();
const toast = useToast();
const previewUrl = ref<string | null>(null);
const processing = ref<number | null>(null);

onMounted(() => {
    const flash = (page.props as any).flash;
    if (flash?.type === 'success') toast.success(flash.message, '¡Listo!');
    if (flash?.type === 'error') toast.error(flash.message, 'Rechazado');
});

// Tiempo real: recarga lista de pagos pendientes al recibir cualquier evento de pago
useRealtimeAdmin(['reservations']);

function approve(id: number) {
    processing.value = id;
    router.patch(
        `/admin/reservations/${id}/approve-payment`,
        {},
        {
            onFinish: () => { processing.value = null; },
        },
    );
}

function reject(id: number) {
    if (!confirm('¿Seguro que quieres rechazar este pago?')) return;
    processing.value = id;
    router.patch(
        `/admin/reservations/${id}/reject-payment`,
        {},
        {
            onFinish: () => { processing.value = null; },
        },
    );
}

function dismiss(id: number) {
    processing.value = id;
    router.delete(
        `/admin/reservations/${id}/dismiss-payment`,
        {
            onFinish: () => { processing.value = null; },
        },
    );
}
</script>

<template>
    <Head title="Pagos pendientes" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-foreground">Pagos pendientes</h1>
                <p class="text-sm text-muted-foreground">Comprobantes de Nequi enviados por los clientes</p>
            </div>

            <!-- Sin pagos pendientes -->
            <div v-if="!reservations.length"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <CheckCircle class="mb-3 h-12 w-12 text-emerald-400" />
                <p class="font-semibold text-foreground">No hay pagos pendientes</p>
                <p class="text-sm text-muted-foreground">Todos los comprobantes han sido revisados.</p>
            </div>

            <!-- Lista de pagos -->
            <div v-else class="space-y-4">
                <div v-for="r in reservations" :key="r.id"
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm">

                    <!-- Badge cancelada -->
                    <div v-if="r.is_cancelled" class="mb-3 flex items-center gap-2 rounded-xl bg-destructive/10 px-3 py-2 text-sm font-semibold text-destructive">
                        <X class="h-4 w-4 shrink-0" />
                        Reserva cancelada — el cliente canceló antes de que se revisara el comprobante.
                    </div>

                    <!-- Encabezado -->
                    <div class="flex items-start justify-between gap-3 mb-3">
                        <div>
                            <p class="font-mono text-sm font-bold text-primary">{{ r.confirmation_code }}</p>
                            <p class="text-sm font-medium text-foreground">{{ r.user.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ r.user.email }}{{ r.user.phone ? ' · ' + r.user.phone : '' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-foreground">${{ r.total_price.toLocaleString() }}</p>
                            <p class="text-xs text-muted-foreground">{{ r.submitted_at }}</p>
                        </div>
                    </div>

                    <!-- Detalles -->
                    <div class="mb-3 rounded-xl bg-muted/40 px-3 py-2 text-xs text-muted-foreground space-y-1">
                        <div class="flex justify-between">
                            <span>Fecha reserva</span>
                            <span class="font-medium text-foreground">{{ r.date }} · {{ r.start_time }} – {{ r.end_time }}</span>
                        </div>
                        <div v-if="r.payment_reference" class="flex justify-between">
                            <span>Referencia</span>
                            <span class="font-medium text-foreground">{{ r.payment_reference }}</span>
                        </div>
                    </div>

                    <!-- Comprobante -->
                    <div class="mb-4">
                        <button v-if="r.payment_proof_url" @click="previewUrl = r.payment_proof_url"
                            class="flex items-center gap-2 rounded-xl border border-border bg-muted/30 px-3 py-2 text-sm font-medium text-foreground transition hover:bg-muted">
                            <Eye class="h-4 w-4 text-primary" />
                            Ver comprobante
                        </button>
                        <p v-else class="text-xs text-muted-foreground">Sin comprobante adjunto.</p>
                    </div>

                    <!-- Acciones -->
                    <div class="flex gap-3">
                        <!-- Reserva cancelada: solo botón descartar -->
                        <template v-if="r.is_cancelled">
                            <button @click="dismiss(r.id)" :disabled="processing === r.id"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-muted py-2.5 text-sm font-semibold text-muted-foreground transition hover:bg-muted/70 disabled:opacity-50">
                                <CheckCircle class="h-4 w-4" />
                                Ok, descartar
                            </button>
                        </template>
                        <!-- Reserva activa: aprobar o rechazar -->
                        <template v-else>
                            <button @click="reject(r.id)" :disabled="processing === r.id"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-destructive/50 py-2.5 text-sm font-semibold text-destructive transition hover:bg-destructive/10 disabled:opacity-50">
                                <X class="h-4 w-4" />
                                Rechazar
                            </button>
                            <button @click="approve(r.id)" :disabled="processing === r.id"
                                class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-emerald-600 py-2.5 text-sm font-bold text-white shadow transition hover:bg-emerald-700 disabled:opacity-50">
                                <CheckCircle class="h-4 w-4" />
                                Aprobar pago
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal preview comprobante -->
        <Teleport to="body">
            <div v-if="previewUrl" class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 p-4"
                @click.self="previewUrl = null">
                <div class="relative max-w-lg w-full rounded-2xl bg-card overflow-hidden shadow-2xl">
                    <button @click="previewUrl = null"
                        class="absolute right-3 top-3 flex h-8 w-8 items-center justify-center rounded-full bg-muted text-foreground">
                        <X class="h-4 w-4" />
                    </button>
                    <img :src="previewUrl" alt="Comprobante" class="w-full max-h-[80vh] object-contain p-4" />
                </div>
            </div>
        </Teleport>

    </AppAdminLayout>
</template>
