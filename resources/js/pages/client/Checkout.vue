<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle, Clock, CreditCard, ShoppingBag } from 'lucide-vue-next';
import { onMounted, ref } from 'vue';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import { useToast } from '@/composables/useToast';

interface Slot {
    id: number;
    date: string;
    start_formatted: string;
    end_formatted: string;
    price: number;
}

interface CartItem {
    id: number;
    name: string;
    price: number;
    quantity: number;
    subtotal: number;
    image_url: string | null;
}

const props = defineProps<{
    slots: Slot[];
    items: CartItem[];
    court_name: string;
    court_price: number;
    consumables_price: number;
    total_price: number;
}>();

const page = usePage();
const toast = useToast();
const notes = ref('');
const submitting = ref(false);
const confirmed = ref(false);
const confirmationCode = ref('');
const reservationId = ref<number | null>(null);

onMounted(() => {
    const flash = (page.props as any).flash;
    if (flash?.type === 'success' && flash?.message) {
        confirmed.value = true;
        confirmationCode.value = flash.confirmation_code ?? '';
        reservationId.value = flash.reservation_id ?? null;
        toast.success(flash.message, '¡Reserva confirmada!');
    }
});

function confirm() {
    if (!props.slots.length) return;
    submitting.value = true;

    router.post(
        '/reservations',
        {
            slot_ids: props.slots.map((s) => s.id),
            notes: notes.value,
        },
        {
            onError: (errors) => {
                const msg = Object.values(errors)[0] as string;
                toast.error(msg, 'Error al reservar');
                submitting.value = false;
            },
            onFinish: () => {
                submitting.value = false;
            },
        },
    );
}

function goBack() {
    router.visit('/cart');
}
</script>

<template>
    <Head title="Confirmar Reserva" />

    <AppClientLayout>
        <div class="p-4 lg:p-6">

            <!-- Estado: Confirmada -->
            <div v-if="confirmed" class="flex flex-col items-center justify-center py-12 text-center">
                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-emerald-100 dark:bg-emerald-950">
                    <CheckCircle class="h-10 w-10 text-emerald-500" />
                </div>
                <h1 class="mb-1 text-2xl font-bold text-foreground">¡Reserva confirmada!</h1>
                <p class="mb-4 text-muted-foreground">Recibirás un correo con los detalles de tu reserva.</p>
                <div class="mb-6 rounded-2xl border border-border bg-card px-6 py-4">
                    <p class="text-xs text-muted-foreground">Código de confirmación</p>
                    <p class="font-mono text-2xl font-bold tracking-widest text-primary">{{ confirmationCode }}</p>
                </div>
                <p class="mb-4 text-sm text-muted-foreground">¿Quieres pagar ahora con Nequi?</p>
                <div class="flex flex-col gap-3 w-full max-w-xs">
                    <a v-if="reservationId" :href="`/reservations/${reservationId}/payment`"
                        class="flex items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-3 font-bold text-primary-foreground transition hover:bg-primary/90">
                        <CreditCard class="h-4 w-4" />
                        Pagar con Nequi
                    </a>
                    <a href="/reservations"
                        class="rounded-2xl border border-border px-6 py-3 text-center font-semibold text-foreground transition hover:bg-muted">
                        Ver mis reservas
                    </a>
                </div>
            </div>

            <!-- Estado: Formulario -->
            <template v-else>
                <div class="mb-6">
                    <h1 class="text-xl font-bold text-foreground">Confirmar reserva</h1>
                    <p class="text-sm text-muted-foreground">Revisa los detalles antes de confirmar</p>
                </div>

                <!-- Sin slots -->
                <div v-if="!slots.length"
                    class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                    <CalendarDays class="mb-3 h-12 w-12 text-muted-foreground/40" />
                    <p class="font-semibold text-foreground">No hay horarios seleccionados</p>
                    <p class="mb-4 text-sm text-muted-foreground">Selecciona horarios en el calendario primero.</p>
                    <button @click="goBack"
                        class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground">
                        Ir al carrito
                    </button>
                </div>

                <template v-else>
                    <!-- Horarios -->
                    <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                            <CalendarDays class="h-4 w-4 text-primary" />
                            {{ court_name || 'Cancha' }} — Horarios seleccionados
                        </h2>
                        <div class="space-y-2">
                            <div v-for="slot in slots" :key="slot.id"
                                class="flex items-center justify-between rounded-xl bg-muted/50 px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <Clock class="h-4 w-4 text-primary shrink-0" />
                                    <div>
                                        <p class="text-sm font-medium text-foreground">
                                            {{ slot.start_formatted }} – {{ slot.end_formatted }}
                                        </p>
                                        <p class="text-xs text-muted-foreground">{{ slot.date }}</p>
                                    </div>
                                </div>
                                <p class="text-sm font-semibold text-foreground">${{ slot.price.toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Consumibles (si hay) -->
                    <div v-if="items.length > 0" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                            <ShoppingBag class="h-4 w-4 text-primary" />
                            Consumibles
                        </h2>
                        <div class="space-y-2">
                            <div v-for="item in items" :key="item.id"
                                class="flex items-center justify-between rounded-xl bg-muted/50 px-3 py-2.5">
                                <div class="flex items-center gap-2">
                                    <div class="h-8 w-8 overflow-hidden rounded-lg bg-muted shrink-0 flex items-center justify-center">
                                        <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-full w-full object-cover" />
                                        <ShoppingBag v-else class="h-4 w-4 text-muted-foreground/40" />
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium text-foreground">{{ item.name }}</p>
                                        <p class="text-xs text-muted-foreground">x{{ item.quantity }} · ${{ item.price.toLocaleString() }} c/u</p>
                                    </div>
                                </div>
                                <p class="text-sm font-semibold text-foreground">${{ item.subtotal.toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Resumen de pago -->
                    <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                            Resumen de pago
                        </h2>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-muted-foreground">Cancha ({{ slots.length }}h)</span>
                                <span class="font-medium">${{ court_price.toLocaleString() }}</span>
                            </div>
                            <div v-if="items.length > 0" class="flex justify-between">
                                <span class="text-muted-foreground">Consumibles</span>
                                <span class="font-medium">${{ consumables_price.toLocaleString() }}</span>
                            </div>
                            <div class="flex items-center justify-between border-t border-border pt-2">
                                <span class="font-semibold text-foreground">Total</span>
                                <span class="text-lg font-bold text-primary">${{ total_price.toLocaleString() }}</span>
                            </div>
                        </div>
                        <p class="mt-2 text-xs text-muted-foreground">* El pago se realiza en el lugar al momento de usar la cancha.</p>
                    </div>

                    <!-- Notas -->
                    <div class="mb-6 rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <label class="mb-2 block text-sm font-semibold text-foreground">
                            Notas adicionales
                            <span class="font-normal text-muted-foreground">(opcional)</span>
                        </label>
                        <textarea v-model="notes" rows="3"
                            placeholder="Ej: somos 10 personas, necesitamos petos..."
                            class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>

                    <!-- Botones -->
                    <div class="flex gap-3">
                        <button @click="goBack"
                            class="flex-1 rounded-2xl border border-border py-3 text-sm font-medium text-foreground transition hover:bg-muted">
                            Volver al carrito
                        </button>
                        <button @click="confirm" :disabled="submitting"
                            class="flex flex-1 items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95 disabled:opacity-60">
                            <CheckCircle class="h-4 w-4" />
                            {{ submitting ? 'Confirmando...' : 'Confirmar reserva' }}
                        </button>
                    </div>
                </template>
            </template>
        </div>
    </AppClientLayout>
</template>
