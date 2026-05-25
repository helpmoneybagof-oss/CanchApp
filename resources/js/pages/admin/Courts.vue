<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Edit2, Plus, ShieldCheck, Trash2, X, Goal, ImageIcon, Upload } from 'lucide-vue-next';
import { computed, ref } from 'vue';
import { useToast } from '@/composables/useToast';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

const toast = useToast();

interface Court {
    id: number;
    name: string;
    type: string | null;
    price_per_hour: number;
    description: string | null;
    image_url: string | null;
    capacity: number | null;
    surface: string | null;
    start_hour: number;
    end_hour: number;
    active: boolean;
    schedule_label: string;
    reservations_count: number;
}

defineProps<{ courts: Court[] }>();

// ── Modal ──
const showModal = ref(false);
const editing = ref<Court | null>(null);
const saving = ref(false);
const formError = ref('');

const form = ref({
    name: '',
    type: '',
    price_per_hour: 50000,
    description: '',
    capacity: null as number | null,
    surface: 'Sintética',
    start_hour: 17,
    end_hour: 23,
    active: true,
});

// Manejo de imagen (separado del form para no contaminar la serialización JSON)
const imageFile = ref<File | null>(null);
const imagePreview = ref<string | null>(null); // URL temporal del File seleccionado
const removeImage = ref(false);                // marca para borrar la imagen actual al guardar

const currentImageUrl = computed(() => {
    if (imagePreview.value) return imagePreview.value;
    if (removeImage.value) return null;
    return editing.value?.image_url ?? null;
});

function onImageSelected(event: Event) {
    const file = (event.target as HTMLInputElement).files?.[0] ?? null;
    if (!file) return;
    if (file.size > 2 * 1024 * 1024) {
        formError.value = 'La imagen no puede pesar más de 2 MB.';
        return;
    }
    imageFile.value = file;
    removeImage.value = false;
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imagePreview.value = URL.createObjectURL(file);
}

function clearImage() {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imageFile.value = null;
    imagePreview.value = null;
    removeImage.value = !!editing.value?.image_url;
}

const surfaces   = ['Sintética', 'Natural', 'Cemento', 'Parquet', 'Otra'];
const courtTypes = ['Fútbol 5', 'Fútbol 7', 'Fútbol 9', 'Fútbol 11', 'Baloncesto', 'Voleibol', 'Tenis', 'Pádel', 'Otra'];

function hourLabel(h: number): string {
    const d = new Date(); d.setHours(h, 0, 0);
    return d.toLocaleTimeString('es-CO', { hour: 'numeric', minute: '2-digit', hour12: true });
}

function resetImageState() {
    if (imagePreview.value) URL.revokeObjectURL(imagePreview.value);
    imageFile.value = null;
    imagePreview.value = null;
    removeImage.value = false;
}

function openCreate() {
    editing.value = null;
    form.value = { name: '', type: '', price_per_hour: 50000, description: '', capacity: null, surface: 'Sintética', start_hour: 17, end_hour: 23, active: true };
    resetImageState();
    formError.value = '';
    showModal.value = true;
}

function openEdit(c: Court) {
    editing.value = c;
    form.value = {
        name: c.name, type: c.type ?? '', price_per_hour: c.price_per_hour,
        description: c.description ?? '', capacity: c.capacity,
        surface: c.surface ?? 'Sintética', start_hour: c.start_hour,
        end_hour: c.end_hour, active: c.active,
    };
    resetImageState();
    formError.value = '';
    showModal.value = true;
}

function closeModal() { showModal.value = false; }

function save() {
    formError.value = '';
    if (!form.value.name.trim()) { formError.value = 'El nombre es obligatorio.'; return; }
    if (form.value.end_hour <= form.value.start_hour) { formError.value = 'La hora de cierre debe ser mayor a la de apertura.'; return; }
    saving.value = true;

    // Inertia/Laravel necesita multipart cuando hay archivos. Se serializa via FormData
    // y para PUT toca enviarlo como POST con _method=put (limitación de PHP/multipart).
    const payload: Record<string, any> = {
        name:           form.value.name,
        type:           form.value.type ?? '',
        price_per_hour: form.value.price_per_hour,
        description:    form.value.description ?? '',
        capacity:       form.value.capacity ?? '',
        surface:        form.value.surface ?? '',
        start_hour:     form.value.start_hour,
        end_hour:       form.value.end_hour,
        active:         form.value.active ? 1 : 0,
    };
    if (imageFile.value) payload.image = imageFile.value;
    if (editing.value && removeImage.value && !imageFile.value) payload.remove_image = 1;

    const opts = {
        forceFormData: true,
        onSuccess: () => {
            toast.success(editing.value ? 'Cancha actualizada.' : 'Cancha creada.', '¡Listo!');
            showModal.value = false;
        },
        onError: (e: Record<string, string>) => { formError.value = Object.values(e)[0]; },
        onFinish: () => { saving.value = false; },
    };

    if (editing.value) {
        payload._method = 'put';
        router.post(`/admin/courts/${editing.value.id}`, payload, opts);
    } else {
        router.post('/admin/courts', payload, opts);
    }
}

function destroy(c: Court) {
    if (!confirm(`¿Eliminar la cancha "${c.name}"?`)) return;
    router.delete(`/admin/courts/${c.id}`, {
        onSuccess: () => toast.success('Cancha eliminada.', '¡Listo!'),
        onError: (e) => toast.error(Object.values(e)[0] as string, 'Error'),
    });
}

function toggleActive(c: Court) {
    router.patch(`/admin/courts/${c.id}/toggle`, {}, {
        onSuccess: () => toast.success(c.active ? 'Cancha desactivada.' : 'Cancha activada.', '¡Listo!'),
    });
}
</script>

<template>
    <Head title="Canchas" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Header -->
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">Canchas</h1>
                    <p class="text-sm text-muted-foreground">Gestiona las canchas del establecimiento</p>
                </div>
                <button @click="openCreate"
                    class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95">
                    <Plus class="h-4 w-4" /> Nueva cancha
                </button>
            </div>

            <!-- Sin canchas -->
            <div v-if="courts.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <Goal class="mb-3 h-14 w-14 text-muted-foreground/30" />
                <p class="font-semibold text-foreground">Sin canchas registradas</p>
                <p class="mb-4 text-sm text-muted-foreground">Crea tu primera cancha para empezar a recibir reservas.</p>
                <button @click="openCreate"
                    class="rounded-xl bg-primary px-5 py-2.5 text-sm font-semibold text-primary-foreground shadow hover:bg-primary/90">
                    Crear cancha
                </button>
            </div>

            <!-- Grid de canchas -->
            <div v-else class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div v-for="c in courts" :key="c.id"
                    class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm"
                    :class="!c.active ? 'opacity-60' : ''">

                    <!-- Foto de la cancha -->
                    <div v-if="c.image_url" class="aspect-[16/9] w-full overflow-hidden bg-muted">
                        <img :src="c.image_url" :alt="c.name"
                            class="h-full w-full object-cover" loading="lazy" />
                    </div>
                    <div v-else class="flex aspect-[16/9] w-full items-center justify-center bg-muted/40">
                        <ImageIcon class="h-10 w-10 text-muted-foreground/30" />
                    </div>

                    <div class="p-5">
                    <!-- Header tarjeta -->
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-primary/10">
                                <Goal class="h-6 w-6 text-primary" />
                            </div>
                            <div>
                                <p class="font-bold text-foreground leading-tight">{{ c.name }}</p>
                                <p v-if="c.type" class="text-xs text-muted-foreground">{{ c.type }}</p>
                            </div>
                        </div>
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold shrink-0"
                            :class="c.active
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : 'bg-gray-100 text-gray-500 dark:bg-gray-800 dark:text-gray-400'">
                            {{ c.active ? 'Activa' : 'Inactiva' }}
                        </span>
                    </div>

                    <!-- Info -->
                    <div class="mb-4 space-y-2 text-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Precio / hora</span>
                            <span class="font-bold text-primary text-base">${{ c.price_per_hour.toLocaleString() }}</span>
                        </div>
                        <div v-if="c.surface" class="flex items-center justify-between">
                            <span class="text-muted-foreground">Superficie</span>
                            <span class="font-medium text-foreground">{{ c.surface }}</span>
                        </div>
                        <div v-if="c.capacity" class="flex items-center justify-between">
                            <span class="text-muted-foreground">Capacidad</span>
                            <span class="font-medium text-foreground">{{ c.capacity }} jugadores/equipo</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Horario</span>
                            <span class="font-medium text-foreground">{{ hourLabel(c.start_hour) }} – {{ hourLabel(c.end_hour) }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="text-muted-foreground">Reservas</span>
                            <span class="font-medium text-foreground">{{ c.reservations_count }}</span>
                        </div>
                    </div>

                    <p v-if="c.description" class="mb-4 text-xs text-muted-foreground line-clamp-2">{{ c.description }}</p>

                    <!-- Acciones -->
                    <div class="flex gap-2">
                        <button @click="toggleActive(c)"
                            class="flex-1 rounded-xl border border-border py-2 text-xs font-medium text-foreground hover:bg-muted transition">
                            {{ c.active ? 'Desactivar' : 'Activar' }}
                        </button>
                        <button @click="openEdit(c)"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-border text-foreground hover:bg-muted transition">
                            <Edit2 class="h-4 w-4" />
                        </button>
                        <button @click="destroy(c)" :disabled="c.reservations_count > 0"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-red-200 bg-red-50 text-red-600 hover:bg-red-100 disabled:opacity-30 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400 transition">
                            <Trash2 class="h-4 w-4" />
                        </button>
                    </div>
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
            <div class="w-full max-w-md rounded-t-3xl bg-background p-6 shadow-2xl lg:rounded-3xl max-h-[90vh] overflow-y-auto">
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-foreground">
                        {{ editing ? 'Editar cancha' : 'Nueva cancha' }}
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

                <!-- Nombre -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Nombre *</label>
                    <input v-model="form.name" type="text" placeholder="Ej: Cancha Fútbol 5"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>

                <!-- Tipo -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Tipo de cancha</label>
                    <select v-model="form.type"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Seleccionar...</option>
                        <option v-for="t in courtTypes" :key="t" :value="t">{{ t }}</option>
                    </select>
                </div>

                <!-- Precio por hora -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Precio por hora (COP) *</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-muted-foreground">$</span>
                        <input v-model.number="form.price_per_hour" type="number" min="0" step="1000"
                            class="w-full rounded-xl border border-border bg-background py-2.5 pl-7 pr-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <p class="mt-1 text-xs text-muted-foreground">= ${{ form.price_per_hour.toLocaleString() }} / hora</p>
                </div>

                <!-- Superficie + Capacidad -->
                <div class="mb-4 grid grid-cols-2 gap-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-foreground">Superficie</label>
                        <select v-model="form.surface"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                            <option v-for="s in surfaces" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-sm font-semibold text-foreground">Jugadores/equipo</label>
                        <input v-model.number="form.capacity" type="number" min="1" placeholder="Ej: 5"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                </div>

                <!-- Horario disponibilidad -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Horario de disponibilidad</label>
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Hora inicio</label>
                            <select v-model.number="form.start_hour"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option v-for="h in Array.from({length: 24}, (_, i) => i)" :key="h" :value="h">
                                    {{ hourLabel(h) }}
                                </option>
                            </select>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs text-muted-foreground">Hora cierre</label>
                            <select v-model.number="form.end_hour"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                <option v-for="h in Array.from({length: 24}, (_, i) => i + 1)" :key="h" :value="h">
                                    {{ hourLabel(h) }}
                                </option>
                            </select>
                        </div>
                    </div>
                    <p class="mt-1.5 text-xs text-muted-foreground">
                        Se generarán slots de 1 hora entre {{ hourLabel(form.start_hour) }} y {{ hourLabel(form.end_hour) }}
                    </p>
                </div>

                <!-- Foto -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Foto</label>
                    <div class="flex items-start gap-3">
                        <div class="h-24 w-24 shrink-0 overflow-hidden rounded-xl border border-border bg-muted/40">
                            <img v-if="currentImageUrl" :src="currentImageUrl" alt="Preview"
                                class="h-full w-full object-cover" />
                            <div v-else class="flex h-full w-full items-center justify-center">
                                <ImageIcon class="h-7 w-7 text-muted-foreground/40" />
                            </div>
                        </div>
                        <div class="flex flex-1 flex-col gap-2">
                            <label
                                class="flex cursor-pointer items-center justify-center gap-2 rounded-xl border border-dashed border-border bg-background px-3 py-2 text-xs font-semibold text-foreground hover:bg-muted">
                                <Upload class="h-4 w-4" />
                                {{ currentImageUrl ? 'Cambiar foto' : 'Seleccionar foto' }}
                                <input type="file" accept="image/*" class="hidden" @change="onImageSelected" />
                            </label>
                            <button v-if="currentImageUrl" @click="clearImage" type="button"
                                class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400">
                                Quitar foto
                            </button>
                            <p class="text-[11px] text-muted-foreground">PNG/JPG/WebP — máx. 2 MB.</p>
                        </div>
                    </div>
                </div>

                <!-- Descripción -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">Descripción</label>
                    <textarea v-model="form.description" rows="2" placeholder="Detalles adicionales de la cancha..."
                        class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>

                <!-- Activa -->
                <div class="mb-6 flex items-center gap-3">
                    <input v-model="form.active" type="checkbox" id="court-active" class="h-4 w-4 rounded accent-primary" />
                    <label for="court-active" class="text-sm font-medium text-foreground">Cancha activa (visible para reservas)</label>
                </div>

                <!-- Botones -->
                <div class="flex gap-3">
                    <button @click="closeModal"
                        class="flex-1 rounded-xl border border-border py-3 text-sm font-medium text-foreground hover:bg-muted transition">
                        Cancelar
                    </button>
                    <button @click="save" :disabled="saving"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-bold text-primary-foreground shadow hover:bg-primary/90 disabled:opacity-60 transition">
                        {{ saving ? 'Guardando...' : (editing ? 'Guardar cambios' : 'Crear cancha') }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
