<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ShoppingBag, Search, X, ShoppingCart, Plus, Minus, ArrowRight, CalendarDays } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import { useToast } from '@/composables/useToast';
import axios from 'axios';

const toast = useToast();

interface Category { id: number; name: string; }
interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number;
    stock: number;
    image_url: string | null;
    category: Category;
}

const props = defineProps<{
    products: Product[];
    categories: Category[];
    filters: { search?: string; category_id?: string };
    from_calendar?: boolean;
    cart_slots_count?: number;
}>();

// ── Filtros ──
const filterSearch = ref(props.filters.search ?? '');
const filterCategory = ref(props.filters.category_id ?? '');

function applyFilters() {
    router.get('/catalog', {
        search: filterSearch.value || undefined,
        category_id: filterCategory.value || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    filterSearch.value = '';
    filterCategory.value = '';
    router.get('/catalog', {}, { preserveState: true, replace: true });
}

const hasFilters = computed(() => !!filterSearch.value || !!filterCategory.value);

// ── Carrito local (cantidades por producto) ──
const cartQty = ref<Record<number, number>>({});
const addingId = ref<number | null>(null);

function getQty(productId: number) {
    return cartQty.value[productId] ?? 0;
}

async function addToCart(product: Product) {
    const qty = getQty(product.id) + 1;
    if (qty > product.stock) {
        toast.error('No hay suficiente stock.', 'Sin stock');
        return;
    }
    addingId.value = product.id;
    try {
        await axios.post('/cart/items', { product_id: product.id, quantity: qty });
        cartQty.value[product.id] = qty;
        toast.success(`${product.name} agregado al carrito.`, '¡Agregado!');
    } catch (e: any) {
        toast.error(e?.response?.data?.message ?? 'Error al agregar.', 'Error');
    } finally {
        addingId.value = null;
    }
}

async function decreaseQty(product: Product) {
    const qty = getQty(product.id) - 1;
    if (qty <= 0) {
        try {
            await axios.delete(`/cart/items/${product.id}`);
            delete cartQty.value[product.id];
        } catch { /* ignore */ }
        return;
    }
    try {
        await axios.post('/cart/items', { product_id: product.id, quantity: qty });
        cartQty.value[product.id] = qty;
    } catch { /* ignore */ }
}

const totalCartItems = computed(() => Object.values(cartQty.value).reduce((a, b) => a + b, 0));
</script>

<template>
    <Head title="Catálogo" />
    <AppClientLayout>
        <div class="p-4 lg:p-6">

            <!-- Banner "¿Algo más?" cuando viene del calendario -->
            <div v-if="from_calendar"
                class="mb-5 rounded-2xl border border-primary/30 bg-primary/5 p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-primary/15">
                            <CalendarDays class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">
                                ¡Horario{{ (cart_slots_count ?? 0) > 1 ? 's' : '' }} reservado{{ (cart_slots_count ?? 0) > 1 ? 's' : '' }}! 🎉
                            </p>
                            <p class="text-sm text-muted-foreground">
                                ¿Deseas agregar bebidas o snacks a tu reserva?
                            </p>
                        </div>
                    </div>
                    <button @click="router.visit('/checkout')"
                        class="flex shrink-0 items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90">
                        Ir al pago <ArrowRight class="h-4 w-4" />
                    </button>
                </div>
            </div>

            <!-- Header -->
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">Catálogo</h1>
                    <p class="text-sm text-muted-foreground">Bebidas y snacks para tu partido</p>
                </div>
                <a v-if="totalCartItems > 0" href="/cart"
                    class="relative flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90">
                    <ShoppingCart class="h-4 w-4" />
                    <span>Carrito</span>
                    <span class="absolute -top-1.5 -right-1.5 flex h-5 w-5 items-center justify-center rounded-full bg-white text-[10px] font-bold text-primary shadow">
                        {{ totalCartItems }}
                    </span>
                </a>
            </div>

            <!-- Filtros -->
            <div class="mb-5 flex flex-col gap-3 sm:flex-row">
                <div class="relative flex-1">
                    <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                    <input v-model="filterSearch" type="text" placeholder="Buscar producto..."
                        @keyup.enter="applyFilters"
                        class="w-full rounded-xl border border-border bg-background py-2.5 pl-9 pr-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>
                <select v-model="filterCategory" @change="applyFilters"
                    class="rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                    <option value="">Todas las categorías</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
                <button v-if="hasFilters" @click="clearFilters"
                    class="flex items-center gap-1.5 rounded-xl border border-border px-3 py-2.5 text-sm text-foreground hover:bg-muted">
                    <X class="h-4 w-4" /> Limpiar
                </button>
            </div>

            <!-- Vacío -->
            <div v-if="products.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <ShoppingBag class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">Sin productos disponibles</p>
                <p v-if="hasFilters" class="text-sm text-muted-foreground">Intenta con otra búsqueda</p>
            </div>

            <!-- Grid de productos -->
            <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                <div v-for="p in products" :key="p.id"
                    class="flex flex-col rounded-2xl border border-border bg-card shadow-sm overflow-hidden">

                    <!-- Imagen -->
                    <div class="h-32 w-full bg-muted flex items-center justify-center overflow-hidden">
                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="h-full w-full object-cover" />
                        <ShoppingBag v-else class="h-10 w-10 text-muted-foreground/30" />
                    </div>

                    <div class="flex flex-1 flex-col p-3">
                        <p class="text-sm font-semibold text-foreground leading-tight">{{ p.name }}</p>
                        <p class="text-xs text-muted-foreground">{{ p.category.name }}</p>
                        <p v-if="p.description" class="mt-1 text-xs text-muted-foreground line-clamp-2">{{ p.description }}</p>
                        <div class="mt-auto pt-3">
                            <p class="mb-2 text-base font-bold text-primary">${{ p.price.toLocaleString() }}</p>

                            <!-- Sin stock -->
                            <div v-if="p.stock === 0"
                                class="rounded-xl border border-border py-2 text-center text-xs font-medium text-muted-foreground">
                                Sin stock
                            </div>

                            <!-- Control cantidad -->
                            <div v-else-if="getQty(p.id) > 0" class="flex items-center justify-between rounded-xl border border-primary/30 bg-primary/5 px-2 py-1">
                                <button @click="decreaseQty(p)"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary/10 text-primary hover:bg-primary/20 transition">
                                    <Minus class="h-3.5 w-3.5" />
                                </button>
                                <span class="text-sm font-bold text-primary">{{ getQty(p.id) }}</span>
                                <button @click="addToCart(p)" :disabled="addingId === p.id || getQty(p.id) >= p.stock"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg bg-primary text-primary-foreground hover:bg-primary/90 transition disabled:opacity-50">
                                    <Plus class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <!-- Agregar -->
                            <button v-else @click="addToCart(p)" :disabled="addingId === p.id"
                                class="flex w-full items-center justify-center gap-1.5 rounded-xl bg-primary py-2 text-xs font-semibold text-primary-foreground shadow transition hover:bg-primary/90 disabled:opacity-60">
                                <Plus class="h-3.5 w-3.5" />
                                {{ addingId === p.id ? 'Agregando...' : 'Agregar' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Botón flotante: si viene del calendario → "Ir al pago", si no → "Ver carrito" -->
            <div v-if="totalCartItems > 0 || from_calendar"
                class="fixed bottom-24 left-1/2 z-40 -translate-x-1/2 lg:bottom-6">
                <button @click="router.visit('/checkout')"
                    class="flex items-center gap-3 rounded-2xl bg-primary px-6 py-3 font-semibold text-primary-foreground shadow-xl transition hover:bg-primary/90">
                    <ShoppingCart class="h-5 w-5" />
                    {{ from_calendar && totalCartItems === 0
                        ? 'Continuar sin consumibles'
                        : `Ir al pago · ${totalCartItems} producto(s)` }}
                </button>
            </div>
        </div>
    </AppClientLayout>
</template>
