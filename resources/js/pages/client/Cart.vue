<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, Clock, Minus, Plus, ShoppingBag, ShoppingCart, Trash2, X } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

const toast = useToast();

interface CartItem {
    id: number;
    name: string;
    price: number;
    stock: number;
    image_url: string | null;
    quantity: number;
    subtotal: number;
}

interface Slot {
    id: number;
    date: string;
    start_formatted: string;
    end_formatted: string;
    price: number;
}

const props = defineProps<{
    items: CartItem[];
    slots: Slot[];
    court_price: number;
    consumables_price: number;
    total_price: number;
}>();

const localItems = ref<CartItem[]>(props.items.map(i => ({ ...i })));
const updatingId = ref<number | null>(null);
const submitting = ref(false);

async function updateQty(item: CartItem, delta: number) {
    const newQty = item.quantity + delta;
    if (newQty < 1) {
        await removeItem(item);
        return;
    }
    if (newQty > item.stock) {
        toast.error('Stock insuficiente.', 'Sin stock');
        return;
    }
    updatingId.value = item.id;
    try {
        await axios.post('/cart/items', { product_id: item.id, quantity: newQty });
        const found = localItems.value.find(i => i.id === item.id);
        if (found) {
            found.quantity = newQty;
            found.subtotal = newQty * found.price;
        }
    } catch (e: any) {
        toast.error(e?.response?.data?.message ?? 'Error al actualizar.', 'Error');
    } finally {
        updatingId.value = null;
    }
}

async function removeItem(item: CartItem) {
    updatingId.value = item.id;
    try {
        await axios.delete(`/cart/items/${item.id}`);
        localItems.value = localItems.value.filter(i => i.id !== item.id);
        toast.success(`${item.name} eliminado del carrito.`, 'Eliminado');
    } catch {
        toast.error('Error al eliminar.', 'Error');
    } finally {
        updatingId.value = null;
    }
}

const consumablesTotal = computed(() =>
    localItems.value.reduce((sum, i) => sum + i.subtotal, 0)
);

const grandTotal = computed(() => props.court_price + consumablesTotal.value);

const hasItems = computed(() => props.slots.length > 0 || localItems.value.length > 0);

function goToCheckout() {
    if (!props.slots.length) {
        toast.error('Selecciona al menos un horario en el calendario.', 'Sin horario');
        return;
    }
    // Los slots ya están en sesión, solo navegar al checkout
    router.visit('/checkout');
}

function goToCatalog() {
    router.visit('/catalog');
}

function goToCalendar() {
    router.visit('/calendar');
}
</script>

<template>
    <Head title="Mi Carrito" />
    <AppClientLayout>
        <div class="p-4 lg:p-6">
            <div class="mb-5">
                <h1 class="text-xl font-bold text-foreground">Mi Carrito</h1>
                <p class="text-sm text-muted-foreground">Revisa tu pedido antes de confirmar</p>
            </div>

            <!-- Carrito vacío -->
            <div v-if="!hasItems"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <ShoppingCart class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">Tu carrito está vacío</p>
                <p class="mb-4 text-sm text-muted-foreground">Selecciona un horario y/o agrega productos.</p>
                <div class="flex gap-3">
                    <button @click="goToCalendar"
                        class="rounded-xl border border-border px-4 py-2.5 text-sm font-medium text-foreground hover:bg-muted transition">
                        Ver calendario
                    </button>
                    <button @click="goToCatalog"
                        class="rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow hover:bg-primary/90 transition">
                        Ver catálogo
                    </button>
                </div>
            </div>

            <template v-else>
                <!-- Slots / Cancha -->
                <div v-if="slots.length > 0" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                        <CalendarDays class="h-4 w-4 text-primary" />
                        Horarios reservados
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
                    <div class="mt-2 flex items-center justify-between border-t border-border pt-2">
                        <span class="text-xs text-muted-foreground">Subtotal cancha</span>
                        <span class="text-sm font-bold text-foreground">${{ court_price.toLocaleString() }}</span>
                    </div>
                </div>

                <!-- Sin horario seleccionado -->
                <div v-else class="mb-4 rounded-2xl border border-dashed border-amber-300 bg-amber-50 p-4 dark:border-amber-700 dark:bg-amber-900/20">
                    <p class="text-sm font-medium text-amber-700 dark:text-amber-400">
                        No tienes horarios seleccionados.
                        <button @click="goToCalendar" class="underline hover:no-underline">Ir al calendario</button>
                    </p>
                </div>

                <!-- Consumibles -->
                <div v-if="localItems.length > 0" class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <h2 class="mb-3 flex items-center gap-2 text-sm font-semibold text-foreground">
                        <ShoppingBag class="h-4 w-4 text-primary" />
                        Consumibles
                    </h2>
                    <div class="space-y-3">
                        <div v-for="item in localItems" :key="item.id"
                            class="flex items-center gap-3">
                            <!-- Imagen -->
                            <div class="h-12 w-12 overflow-hidden rounded-xl bg-muted shrink-0 flex items-center justify-center">
                                <img v-if="item.image_url" :src="item.image_url" :alt="item.name" class="h-full w-full object-cover" />
                                <ShoppingBag v-else class="h-5 w-5 text-muted-foreground/30" />
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-foreground truncate">{{ item.name }}</p>
                                <p class="text-xs text-muted-foreground">${{ item.price.toLocaleString() }} c/u</p>
                            </div>
                            <!-- Control qty -->
                            <div class="flex items-center gap-1.5">
                                <button @click="updateQty(item, -1)" :disabled="updatingId === item.id"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-50 transition">
                                    <Minus class="h-3 w-3" />
                                </button>
                                <span class="w-6 text-center text-sm font-semibold text-foreground">{{ item.quantity }}</span>
                                <button @click="updateQty(item, 1)" :disabled="updatingId === item.id || item.quantity >= item.stock"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg border border-border bg-card text-foreground hover:bg-muted disabled:opacity-50 transition">
                                    <Plus class="h-3 w-3" />
                                </button>
                            </div>
                            <p class="w-20 text-right text-sm font-bold text-foreground shrink-0">
                                ${{ item.subtotal.toLocaleString() }}
                            </p>
                            <button @click="removeItem(item)" :disabled="updatingId === item.id"
                                class="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground hover:text-destructive disabled:opacity-50 transition">
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                    <div class="mt-3 flex items-center justify-between border-t border-border pt-2">
                        <span class="text-xs text-muted-foreground">Subtotal consumibles</span>
                        <span class="text-sm font-bold text-foreground">${{ consumablesTotal.toLocaleString() }}</span>
                    </div>
                </div>

                <!-- Agregar consumibles -->
                <button @click="goToCatalog"
                    class="mb-4 flex w-full items-center justify-center gap-2 rounded-2xl border border-dashed border-border py-3 text-sm font-medium text-muted-foreground transition hover:border-primary hover:text-primary">
                    <Plus class="h-4 w-4" />
                    Agregar consumibles del catálogo
                </button>

                <!-- Total general -->
                <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="space-y-2 text-sm">
                        <div v-if="slots.length > 0" class="flex justify-between">
                            <span class="text-muted-foreground">Cancha ({{ slots.length }}h)</span>
                            <span class="font-medium">${{ court_price.toLocaleString() }}</span>
                        </div>
                        <div v-if="localItems.length > 0" class="flex justify-between">
                            <span class="text-muted-foreground">Consumibles</span>
                            <span class="font-medium">${{ consumablesTotal.toLocaleString() }}</span>
                        </div>
                        <div class="flex items-center justify-between border-t border-border pt-2">
                            <span class="font-semibold text-foreground">Total</span>
                            <span class="text-lg font-bold text-primary">${{ grandTotal.toLocaleString() }}</span>
                        </div>
                    </div>
                    <p class="mt-2 text-xs text-muted-foreground">* El pago se realiza en el lugar.</p>
                </div>

                <!-- Botón confirmar -->
                <button @click="goToCheckout" :disabled="!slots.length || submitting"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-4 text-base font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-[0.98] disabled:opacity-60">
                    <CalendarDays class="h-5 w-5" />
                    {{ slots.length ? 'Confirmar reserva' : 'Selecciona un horario primero' }}
                </button>
            </template>
        </div>
    </AppClientLayout>
</template>
