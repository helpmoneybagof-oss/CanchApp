<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Edit2, Plus, Tag, Trash2, X } from 'lucide-vue-next';
import { ref } from 'vue';
import { useToast } from '@/composables/useToast';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

const toast = useToast();

interface Category {
    id: number;
    name: string;
    description: string | null;
    active: boolean;
    products_count: number;
}

defineProps<{ categories: Category[] }>();

// ── Modal crear/editar ──
const showModal = ref(false);
const editing = ref<Category | null>(null);
const form = ref({ name: '', description: '', active: true });
const saving = ref(false);
const formError = ref('');

function openCreate() {
    editing.value = null;
    form.value = { name: '', description: '', active: true };
    formError.value = '';
    showModal.value = true;
}

function openEdit(cat: Category) {
    editing.value = cat;
    form.value = { name: cat.name, description: cat.description ?? '', active: cat.active };
    formError.value = '';
    showModal.value = true;
}

function closeModal() {
    showModal.value = false;
}

function save() {
    formError.value = '';
    if (!form.value.name.trim()) { formError.value = 'El nombre es obligatorio.'; return; }
    saving.value = true;

    if (editing.value) {
        router.put(`/admin/categories/${editing.value.id}`, form.value, {
            onSuccess: () => { toast.success('Categoría actualizada.', '¡Listo!'); showModal.value = false; },
            onError: (e) => { formError.value = Object.values(e)[0] as string; },
            onFinish: () => { saving.value = false; },
        });
    } else {
        router.post('/admin/categories', form.value, {
            onSuccess: () => { toast.success('Categoría creada.', '¡Listo!'); showModal.value = false; },
            onError: (e) => { formError.value = Object.values(e)[0] as string; },
            onFinish: () => { saving.value = false; },
        });
    }
}

function destroy(cat: Category) {
    if (!confirm(`¿Eliminar la categoría "${cat.name}"?`)) return;
    router.delete(`/admin/categories/${cat.id}`, {
        onSuccess: () => toast.success('Categoría eliminada.', '¡Listo!'),
        onError: (e) => toast.error(Object.values(e)[0] as string, 'Error'),
    });
}
</script>

<template>
    <Head title="Categorías" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">Categorías</h1>
                    <p class="text-sm text-muted-foreground">Categorías de productos</p>
                </div>
                <button @click="openCreate"
                    class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95">
                    <Plus class="h-4 w-4" /> Nueva categoría
                </button>
            </div>

            <!-- Vacío -->
            <div v-if="categories.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <Tag class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">Sin categorías</p>
                <p class="text-sm text-muted-foreground">Crea tu primera categoría para organizar los productos.</p>
            </div>

            <!-- Lista -->
            <div v-else class="space-y-3">
                <div v-for="cat in categories" :key="cat.id"
                    class="flex items-center justify-between rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10">
                            <Tag class="h-5 w-5 text-primary" />
                        </div>
                        <div>
                            <p class="font-semibold text-foreground">{{ cat.name }}</p>
                            <p v-if="cat.description" class="text-xs text-muted-foreground">{{ cat.description }}</p>
                            <div class="mt-0.5 flex items-center gap-2">
                                <span class="text-xs text-muted-foreground">{{ cat.products_count }} producto(s)</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="cat.active ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400' : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'">
                                    {{ cat.active ? 'Activa' : 'Inactiva' }}
                                </span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <button @click="openEdit(cat)"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-border bg-card text-foreground transition hover:bg-muted">
                            <Edit2 class="h-4 w-4" />
                        </button>
                        <button @click="destroy(cat)" :disabled="cat.products_count > 0"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 transition hover:bg-red-100 disabled:opacity-30 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppAdminLayout>

    <!-- Modal crear/editar -->
    <Teleport to="body">
        <div v-if="showModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm lg:items-center"
            @click.self="closeModal">
            <div class="w-full max-w-sm rounded-t-3xl bg-background p-6 shadow-2xl lg:rounded-3xl">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-foreground">
                        {{ editing ? 'Editar categoría' : 'Nueva categoría' }}
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

                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Nombre *</label>
                    <input v-model="form.name" type="text" placeholder="Ej: Bebidas"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Descripción</label>
                    <input v-model="form.description" type="text" placeholder="Opcional..."
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>
                <div class="mb-6 flex items-center gap-3">
                    <input v-model="form.active" type="checkbox" id="cat-active" class="h-4 w-4 rounded accent-primary" />
                    <label for="cat-active" class="text-sm font-medium text-foreground">Categoría activa</label>
                </div>

                <div class="flex gap-3">
                    <button @click="closeModal"
                        class="flex-1 rounded-xl border border-border py-3 text-sm font-medium text-foreground hover:bg-muted">
                        Cancelar
                    </button>
                    <button @click="save" :disabled="saving"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-bold text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60">
                        {{ saving ? 'Guardando...' : (editing ? 'Guardar cambios' : 'Crear') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
