<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Edit2, Package, Plus, Search, Trash2, X, AlertTriangle } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import { useToast } from '@/composables/useToast';

const toast = useToast();

interface Category { id: number; name: string; }
interface Product {
    id: number;
    name: string;
    description: string | null;
    price: number;
    stock: number;
    min_stock: number;
    active: boolean;
    low_stock: boolean;
    image_url: string | null;
    category: Category;
}

const props = defineProps<{
    products: Product[];
    categories: Category[];
    filters: { search?: string; category_id?: string; active?: string };
}>();

// ── Filtros ──
const filterSearch = ref(props.filters.search ?? '');
const filterCategory = ref(props.filters.category_id ?? '');

function applyFilters() {
    router.get('/admin/products', {
        search: filterSearch.value || undefined,
        category_id: filterCategory.value || undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    filterSearch.value = '';
    filterCategory.value = '';
    router.get('/admin/products', {}, { preserveState: true, replace: true });
}

const hasFilters = computed(() => !!filterSearch.value || !!filterCategory.value);

// ── Modal crear/editar ──
const showModal = ref(false);
const editing = ref<Product | null>(null);
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null);
const saving = ref(false);
const formError = ref('');

const form = ref({
    product_category_id: '',
    name: '',
    description: '',
    price: 0,
    stock: 0,
    min_stock: 5,
    active: true,
});

function openCreate() {
    editing.value = null;
    imageFile.value = null;
    imagePreview.value = null;
    form.value = { product_category_id: '', name: '', description: '', price: 0, stock: 0, min_stock: 5, active: true };
    formError.value = '';
    showModal.value = true;
}

function openEdit(p: Product) {
    editing.value = p;
    imageFile.value = null;
    imagePreview.value = p.image_url;
    form.value = {
        product_category_id: String(p.category.id),
        name: p.name,
        description: p.description ?? '',
        price: p.price,
        stock: p.stock,
        min_stock: p.min_stock,
        active: p.active,
    };
    formError.value = '';
    showModal.value = true;
}

function closeModal() { showModal.value = false; }

function onImageChange(e: Event) {
    const file = (e.target as HTMLInputElement).files?.[0];
    if (!file) return;
    imageFile.value = file;
    imagePreview.value = URL.createObjectURL(file);
}

function save() {
    formError.value = '';
    if (!form.value.product_category_id) { formError.value = 'Selecciona una categoría.'; return; }
    if (!form.value.name.trim()) { formError.value = 'El nombre es obligatorio.'; return; }

    saving.value = true;

    const data = new FormData();
    Object.entries(form.value).forEach(([k, v]) => data.append(k, String(v)));
    if (imageFile.value) data.append('image', imageFile.value);

    if (editing.value) {
        data.append('_method', 'PUT');
        router.post(`/admin/products/${editing.value.id}`, data, {
            onSuccess: () => { toast.success('Producto actualizado.', '¡Listo!'); showModal.value = false; },
            onError: (e) => { formError.value = Object.values(e)[0] as string; },
            onFinish: () => { saving.value = false; },
        });
    } else {
        router.post('/admin/products', data, {
            onSuccess: () => { toast.success('Producto creado.', '¡Listo!'); showModal.value = false; },
            onError: (e) => { formError.value = Object.values(e)[0] as string; },
            onFinish: () => { saving.value = false; },
        });
    }
}

function destroy(p: Product) {
    if (!confirm(`¿Eliminar el producto "${p.name}"?`)) return;
    router.delete(`/admin/products/${p.id}`, {
        onSuccess: () => toast.success('Producto eliminado.', '¡Listo!'),
        onError: () => toast.error('No se pudo eliminar el producto.', 'Error'),
    });
}

function toggleActive(p: Product) {
    router.patch(`/admin/products/${p.id}/toggle`, {}, {
        onSuccess: () => toast.success(p.active ? 'Producto desactivado.' : 'Producto activado.', '¡Listo!'),
    });
}
</script>

<template>
    <Head title="Productos" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Header -->
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">Productos</h1>
                    <p class="text-sm text-muted-foreground">Catálogo de consumibles</p>
                </div>
                <button @click="openCreate"
                    class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95">
                    <Plus class="h-4 w-4" /><span class="hidden sm:inline">Nuevo producto</span>
                </button>
            </div>

            <!-- Filtros -->
            <div class="mb-5 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <input v-model="filterSearch" type="text" placeholder="Buscar por nombre..."
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    <select v-model="filterCategory"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Todas las categorías</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>
                <div class="mt-3 flex gap-2">
                    <button @click="applyFilters"
                        class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90">
                        <Search class="h-3.5 w-3.5" /> Buscar
                    </button>
                    <button v-if="hasFilters" @click="clearFilters"
                        class="flex items-center gap-1.5 rounded-xl border border-border px-4 py-2 text-xs font-medium text-foreground hover:bg-muted">
                        <X class="h-3.5 w-3.5" /> Limpiar
                    </button>
                </div>
            </div>

            <!-- Vacío -->
            <div v-if="products.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <Package class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">Sin productos</p>
                <p class="text-sm text-muted-foreground">Agrega productos al catálogo.</p>
            </div>

            <!-- Grid de productos -->
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="p in products" :key="p.id"
                    class="relative rounded-2xl border border-border bg-card shadow-sm overflow-hidden"
                    :class="!p.active ? 'opacity-60' : ''">

                    <!-- Alerta stock bajo -->
                    <div v-if="p.low_stock && p.active"
                        class="absolute top-2 right-2 flex items-center gap-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs font-semibold text-orange-700 dark:bg-orange-900/30 dark:text-orange-400">
                        <AlertTriangle class="h-3 w-3" /> Stock bajo
                    </div>

                    <!-- Imagen -->
                    <div class="h-36 w-full bg-muted flex items-center justify-center overflow-hidden">
                        <img v-if="p.image_url" :src="p.image_url" :alt="p.name" class="h-full w-full object-cover" />
                        <Package v-else class="h-12 w-12 text-muted-foreground/30" />
                    </div>

                    <div class="p-4">
                        <div class="mb-1 flex items-start justify-between gap-2">
                            <div>
                                <p class="font-semibold text-foreground leading-tight">{{ p.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ p.category.name }}</p>
                            </div>
                            <p class="text-base font-bold text-primary shrink-0">${{ p.price.toLocaleString() }}</p>
                        </div>
                        <p v-if="p.description" class="mb-3 text-xs text-muted-foreground line-clamp-2">{{ p.description }}</p>

                        <div class="mb-3 flex items-center gap-2 text-xs">
                            <span class="rounded-full px-2 py-0.5 font-semibold"
                                :class="p.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'">
                                {{ p.active ? 'Activo' : 'Inactivo' }}
                            </span>
                            <span class="text-muted-foreground">Stock: {{ p.stock }}</span>
                        </div>

                        <div class="flex gap-2">
                            <button @click="toggleActive(p)"
                                class="flex-1 rounded-xl border border-border py-2 text-xs font-medium text-foreground transition hover:bg-muted">
                                {{ p.active ? 'Desactivar' : 'Activar' }}
                            </button>
                            <button @click="openEdit(p)"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-border text-foreground hover:bg-muted">
                                <Edit2 class="h-4 w-4" />
                            </button>
                            <button @click="destroy(p)"
                                class="flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                                <Trash2 class="h-4 w-4" />
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppAdminLayout>

    <!-- Modal crear/editar producto -->
    <Teleport to="body">
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm lg:items-center"
            @click.self="closeModal">
            <div class="w-full max-w-md rounded-t-3xl bg-background p-6 shadow-2xl lg:rounded-3xl max-h-[90vh] overflow-y-auto">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-foreground">
                        {{ editing ? 'Editar producto' : 'Nuevo producto' }}
                    </h3>
                    <button @click="closeModal"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground hover:bg-destructive hover:text-white">
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div v-if="formError"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                    {{ formError }}
                </div>

                <!-- Imagen preview -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Imagen</label>
                    <div class="flex items-center gap-4">
                        <div class="h-20 w-20 overflow-hidden rounded-xl border border-border bg-muted flex items-center justify-center shrink-0">
                            <img v-if="imagePreview" :src="imagePreview" class="h-full w-full object-cover" />
                            <Package v-else class="h-8 w-8 text-muted-foreground/30" />
                        </div>
                        <label class="flex-1 cursor-pointer rounded-xl border border-dashed border-border p-3 text-center text-sm text-muted-foreground hover:border-primary hover:text-primary transition">
                            <input type="file" accept="image/*" class="hidden" @change="onImageChange" />
                            Subir imagen (max 2MB)
                        </label>
                    </div>
                </div>

                <!-- Categoría -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Categoría *</label>
                    <select v-model="form.product_category_id"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Seleccionar...</option>
                        <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                    </select>
                </div>

                <!-- Nombre -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Nombre *</label>
                    <input v-model="form.name" type="text" placeholder="Nombre del producto"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>

                <!-- Descripción -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Descripción</label>
                    <textarea v-model="form.description" rows="2" placeholder="Descripción opcional..."
                        class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>

                <!-- Precio, Stock, Min Stock -->
                <div class="mb-4 grid grid-cols-3 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-foreground">Precio *</label>
                        <input v-model.number="form.price" type="number" min="0" step="100"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-foreground">Stock</label>
                        <input v-model.number="form.stock" type="number" min="0"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-foreground">Stock mín.</label>
                        <input v-model.number="form.min_stock" type="number" min="0"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                </div>

                <div class="mb-6 flex items-center gap-3">
                    <input v-model="form.active" type="checkbox" id="prod-active" class="h-4 w-4 rounded accent-primary" />
                    <label for="prod-active" class="text-sm font-medium text-foreground">Producto activo</label>
                </div>

                <div class="flex gap-3">
                    <button @click="closeModal"
                        class="flex-1 rounded-xl border border-border py-3 text-sm font-medium text-foreground hover:bg-muted">
                        Cancelar
                    </button>
                    <button @click="save" :disabled="saving"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-bold text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60">
                        {{ saving ? 'Guardando...' : (editing ? 'Guardar cambios' : 'Crear producto') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
