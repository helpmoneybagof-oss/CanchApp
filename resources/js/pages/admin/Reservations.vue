<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, Check, ChevronLeft, ChevronRight, ChevronRight as ChevronRightIcon, Clock, Eye, Plus, Search, X } from 'lucide-vue-next';
import { ref, computed, watch } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import { useToast } from '@/composables/useToast';
import { useRealtimeAdmin } from '@/composables/useRealtime';
import axios from 'axios';

const toast = useToast();

interface Reservation {
    id: number;
    user: { id: number; name: string; email: string };
    date: string;
    date_raw: string;
    start_time: string;
    end_time: string;
    duration_hours: number;
    status: string;
    payment_status: string;
    total_price: number;
    confirmation_code: string;
}

interface Client {
    id: number;
    name: string;
    email: string;
    phone: string | null;
}

interface Court {
    id: number;
    name: string;
    type: string | null;
    price_per_hour: number;
}

interface TimeSlot {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    start_formatted: string;
    end_formatted: string;
    status: 'available' | 'pre_reserved' | 'reserved' | 'blocked';
    price: number;
    pre_reserved_count: number;
}

const props = defineProps<{
    reservations: { data: Reservation[]; links: any[]; meta?: any };
    filters: { date?: string; status?: string; payment_status?: string };
    clients: Client[];
    courts: Court[];
}>();

// ───── FILTROS ─────
const filterDate = ref(props.filters.date ?? '');
const filterStatus = ref(props.filters.status ?? '');
const filterPayment = ref(props.filters.payment_status ?? '');

function applyFilters() {
    router.get(
        '/admin/reservations',
        {
            date: filterDate.value || undefined,
            status: filterStatus.value || undefined,
            payment_status: filterPayment.value || undefined,
        },
        { preserveState: true, replace: true },
    );
}

function clearFilters() {
    filterDate.value = '';
    filterStatus.value = '';
    filterPayment.value = '';
    router.get('/admin/reservations', {}, { preserveState: true, replace: true });
}

const hasActiveFilters = computed(
    () => !!filterDate.value || !!filterStatus.value || !!filterPayment.value,
);

// ───── ACCIONES LISTA ─────
const cancellingId = ref<number | null>(null);
const payingId = ref<number | null>(null);

function cancel(id: number) {
    if (!confirm('¿Cancelar esta reserva?')) return;
    cancellingId.value = id;
    router.patch(
        `/admin/reservations/${id}/cancel`,
        { reason: 'Cancelado por administrador' },
        {
            onSuccess: () => toast.success('Reserva cancelada exitosamente.', 'Cancelada'),
            onError: () => toast.error('No se pudo cancelar la reserva.', 'Error'),
            onFinish: () => {
                cancellingId.value = null;
            },
        },
    );
}

function markAsPaid(id: number) {
    payingId.value = id;
    router.patch(
        `/admin/reservations/${id}/pay`,
        {},
        {
            onSuccess: () => toast.success('Reserva marcada como pagada.', '¡Listo!'),
            onError: () => toast.error('No se pudo actualizar el pago.', 'Error'),
            onFinish: () => {
                payingId.value = null;
            },
        },
    );
}

// ───── MODAL CREAR RESERVA MANUAL ─────
const showCreateModal = ref(false);
type ClientMode = 'registered' | 'walkin';
const clientMode = ref<ClientMode>('registered');

const createForm = ref({
    user_id: '',
    client_name: '',
    client_phone: '',
    court_id: '' as string | number,
    date: '',
    slot_ids: [] as number[],
    notes: '',
});
const availableSlots = ref<TimeSlot[]>([]);
const loadingSlots = ref(false);
const creating = ref(false);
const createError = ref('');

async function loadSlots() {
    if (!createForm.value.date || !createForm.value.court_id) {
        availableSlots.value = [];
        return;
    }
    loadingSlots.value = true;
    createForm.value.slot_ids = [];
    try {
        const res = await axios.get('/api/slots/day', {
            params: { date: createForm.value.date, court_id: createForm.value.court_id },
        });
        // Disponibles: status 'available' o 'pre_reserved' sin pre-reservas activas
        availableSlots.value = (res.data as TimeSlot[]).filter(
            (s) => s.status === 'available' || (s.status === 'pre_reserved' && s.pre_reserved_count === 0),
        );
    } catch {
        availableSlots.value = [];
    } finally {
        loadingSlots.value = false;
    }
}

watch(() => [createForm.value.date, createForm.value.court_id], loadSlots);

function toggleSlot(slotId: number) {
    const idx = createForm.value.slot_ids.indexOf(slotId);
    if (idx === -1) {
        createForm.value.slot_ids.push(slotId);
    } else {
        createForm.value.slot_ids.splice(idx, 1);
    }
}

const selectedSlotsTotal = computed(() => {
    return availableSlots.value
        .filter((s) => createForm.value.slot_ids.includes(s.id))
        .reduce((sum, s) => sum + s.price, 0);
});

function openCreateModal() {
    clientMode.value = 'registered';
    createForm.value = {
        user_id: '',
        client_name: '',
        client_phone: '',
        court_id: props.courts[0]?.id ?? '',
        date: '',
        slot_ids: [],
        notes: '',
    };
    availableSlots.value = [];
    createError.value = '';
    showCreateModal.value = true;
}

function closeCreateModal() {
    showCreateModal.value = false;
}

async function submitCreate() {
    createError.value = '';
    if (clientMode.value === 'registered' && !createForm.value.user_id) {
        createError.value = 'Selecciona un cliente.';
        return;
    }
    if (clientMode.value === 'walkin' && !createForm.value.client_name.trim()) {
        createError.value = 'Ingresa el nombre del cliente.';
        return;
    }
    if (!createForm.value.court_id) {
        createError.value = 'Selecciona una cancha.';
        return;
    }
    if (!createForm.value.slot_ids.length) {
        createError.value = 'Selecciona al menos un horario.';
        return;
    }
    creating.value = true;
    try {
        const payload: Record<string, any> = {
            slot_ids: createForm.value.slot_ids,
            notes: createForm.value.notes,
        };
        if (clientMode.value === 'registered') {
            payload.user_id = createForm.value.user_id;
        } else {
            payload.client_name  = createForm.value.client_name.trim();
            payload.client_phone = createForm.value.client_phone.trim() || null;
        }
        await axios.post('/admin/reservations', payload);
        toast.success('Reserva creada exitosamente.', '¡Listo!');
        showCreateModal.value = false;
        router.reload({ only: ['reservations'] });
    } catch (e: any) {
        createError.value =
            e?.response?.data?.message ?? 'Error al crear la reserva. Intenta nuevamente.';
    } finally {
        creating.value = false;
    }
}

// Tiempo real: recarga lista de reservas cuando hay cambios
useRealtimeAdmin(['reservations']);

// ───── BADGES ─────
const statusLabel: Record<string, string> = {
    pending: 'Pendiente',
    confirmed: 'Confirmada',
    cancelled: 'Cancelada',
    completed: 'Completada',
};

const statusColor: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    completed: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
};
</script>

<template>
    <Head title="Gestión de Reservas" />

    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Header -->
            <div class="mb-5 flex items-center justify-between">
                <div>
                    <h1 class="text-xl font-bold text-foreground">Reservas</h1>
                    <p class="text-sm text-muted-foreground">Gestión completa de reservas</p>
                </div>
                <button
                    @click="openCreateModal"
                    class="flex items-center gap-2 rounded-xl bg-primary px-4 py-2.5 text-sm font-semibold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95"
                >
                    <Plus class="h-4 w-4" />
                    <span class="hidden sm:inline">Nueva reserva</span>
                </button>
            </div>

            <!-- Filtros -->
            <div class="mb-5 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                    <!-- Fecha -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Fecha</label>
                        <input
                            v-model="filterDate"
                            type="date"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                    <!-- Estado -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Estado</label>
                        <select
                            v-model="filterStatus"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="">Todos</option>
                            <option value="pending">Pendiente</option>
                            <option value="confirmed">Confirmada</option>
                            <option value="cancelled">Cancelada</option>
                            <option value="completed">Completada</option>
                        </select>
                    </div>
                    <!-- Pago -->
                    <div>
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Pago</label>
                        <select
                            v-model="filterPayment"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        >
                            <option value="">Todos</option>
                            <option value="unpaid">Sin pagar</option>
                            <option value="paid">Pagado</option>
                        </select>
                    </div>
                </div>
                <div class="mt-3 flex items-center gap-2">
                    <button
                        @click="applyFilters"
                        class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground transition hover:bg-primary/90"
                    >
                        <Search class="h-3.5 w-3.5" />
                        Buscar
                    </button>
                    <button
                        v-if="hasActiveFilters"
                        @click="clearFilters"
                        class="flex items-center gap-1.5 rounded-xl border border-border px-4 py-2 text-xs font-medium text-foreground transition hover:bg-muted"
                    >
                        <X class="h-3.5 w-3.5" />
                        Limpiar filtros
                    </button>
                    <span v-if="hasActiveFilters" class="ml-auto text-xs text-muted-foreground">
                        Filtros activos
                    </span>
                </div>
            </div>

            <!-- Sin reservas -->
            <div
                v-if="reservations.data.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center"
            >
                <CalendarDays class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">No hay reservas</p>
                <p v-if="hasActiveFilters" class="mt-1 text-sm text-muted-foreground">
                    Intenta con otros filtros
                </p>
            </div>

            <!-- Lista -->
            <div v-else class="space-y-3">
                <div
                    v-for="r in reservations.data"
                    :key="r.id"
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <!-- Botón ver detalle -->
                    <button @click="router.visit(`/admin/reservations/${r.id}`)"
                        class="mb-3 flex w-full items-center justify-between rounded-xl bg-muted/40 px-3 py-2 text-xs font-medium text-muted-foreground hover:bg-muted hover:text-foreground transition">
                        <span class="font-mono">#{{ r.confirmation_code }}</span>
                        <span class="flex items-center gap-1">Ver detalle <Eye class="h-3.5 w-3.5" /></span>
                    </button>
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <p class="font-semibold text-foreground">{{ r.user.name }}</p>
                            <p class="text-xs text-muted-foreground">{{ r.user.email }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-foreground">
                                ${{ r.total_price.toLocaleString() }}
                            </p>
                            <div class="mt-1 flex items-center justify-end gap-1">
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="statusColor[r.status]"
                                >
                                    {{ statusLabel[r.status] }}
                                </span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                    :class="
                                        r.payment_status === 'paid'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'
                                    "
                                >
                                    {{ r.payment_status === 'paid' ? 'Pagado' : 'Sin pagar' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3 space-y-1">
                        <div class="flex items-center gap-2 text-sm text-foreground">
                            <CalendarDays class="h-4 w-4 shrink-0 text-primary" />
                            <span>{{ r.date }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-foreground">
                            <Clock class="h-4 w-4 shrink-0 text-primary" />
                            <span>{{ r.start_time }} – {{ r.end_time }} ({{ r.duration_hours }}h)</span>
                        </div>
                    </div>

                    <div class="flex gap-2" v-if="r.status !== 'cancelled' && r.status !== 'completed'">
                        <button
                            v-if="r.payment_status === 'unpaid'"
                            @click="markAsPaid(r.id)"
                            :disabled="payingId === r.id"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 py-2 text-xs font-semibold text-emerald-700 transition hover:bg-emerald-100 disabled:opacity-50 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400"
                        >
                            <Check class="h-3.5 w-3.5" />
                            {{ payingId === r.id ? 'Procesando...' : 'Marcar pagado' }}
                        </button>
                        <button
                            @click="cancel(r.id)"
                            :disabled="cancellingId === r.id"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-red-200 bg-red-50 py-2 text-xs font-semibold text-red-600 transition hover:bg-red-100 disabled:opacity-50 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                        >
                            <X class="h-3.5 w-3.5" />
                            {{ cancellingId === r.id ? 'Cancelando...' : 'Cancelar' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div
                v-if="reservations.links && reservations.links.length > 3"
                class="mt-5 flex items-center justify-center gap-1"
            >
                <template v-for="link in reservations.links" :key="link.label">
                    <button
                        v-if="link.url"
                        @click="router.visit(link.url, { preserveScroll: true })"
                        class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border border-border px-3 text-sm font-medium transition hover:bg-muted"
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-foreground'"
                        v-html="link.label"
                    />
                    <span
                        v-else
                        class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl px-3 text-sm text-muted-foreground opacity-50"
                        v-html="link.label"
                    />
                </template>
            </div>
        </div>
    </AppAdminLayout>

    <!-- ───── MODAL: CREAR RESERVA MANUAL ───── -->
    <Teleport to="body">
        <div
            v-if="showCreateModal"
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/60 backdrop-blur-sm lg:items-center"
            @click.self="closeCreateModal"
        >
            <div
                class="w-full max-w-md rounded-t-3xl bg-background p-6 shadow-2xl lg:rounded-3xl max-h-[90vh] overflow-y-auto"
            >
                <!-- Header modal -->
                <div class="mb-5 flex items-center justify-between">
                    <h3 class="text-lg font-bold text-foreground">Nueva reserva manual</h3>
                    <button
                        @click="closeCreateModal"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground transition hover:bg-destructive hover:text-white"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Error global -->
                <div
                    v-if="createError"
                    class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400"
                >
                    {{ createError }}
                </div>

                <!-- Cliente: toggle registrado / walk-in -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">
                        Cliente <span class="text-destructive">*</span>
                    </label>
                    <div class="mb-2 inline-flex w-full rounded-xl border border-border bg-muted/40 p-0.5">
                        <button
                            type="button"
                            @click="clientMode = 'registered'"
                            :class="[
                                'flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                clientMode === 'registered'
                                    ? 'bg-card text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            Cliente registrado
                        </button>
                        <button
                            type="button"
                            @click="clientMode = 'walkin'"
                            :class="[
                                'flex-1 rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                clientMode === 'walkin'
                                    ? 'bg-card text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            ]"
                        >
                            Nombre libre
                        </button>
                    </div>

                    <!-- Registrado: select -->
                    <select
                        v-if="clientMode === 'registered'"
                        v-model="createForm.user_id"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Seleccionar cliente...</option>
                        <option v-for="c in clients" :key="c.id" :value="c.id">
                            {{ c.name }} — {{ c.email }}
                        </option>
                    </select>

                    <!-- Walk-in: nombre + teléfono -->
                    <div v-else class="space-y-2">
                        <input
                            v-model="createForm.client_name"
                            type="text"
                            placeholder="Nombre del cliente"
                            maxlength="120"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        />
                        <input
                            v-model="createForm.client_phone"
                            type="tel"
                            placeholder="Teléfono (opcional)"
                            maxlength="30"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                        />
                    </div>
                </div>

                <!-- Cancha -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">
                        Cancha <span class="text-destructive">*</span>
                    </label>
                    <select
                        v-model="createForm.court_id"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    >
                        <option value="">Seleccionar cancha...</option>
                        <option v-for="court in courts" :key="court.id" :value="court.id">
                            {{ court.name }}<template v-if="court.type"> — {{ court.type }}</template>
                            (${{ court.price_per_hour.toLocaleString() }}/h)
                        </option>
                    </select>
                </div>

                <!-- Fecha -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">
                        Fecha <span class="text-destructive">*</span>
                    </label>
                    <input
                        v-model="createForm.date"
                        type="date"
                        :min="new Date().toISOString().split('T')[0]"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>

                <!-- Horarios disponibles -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">
                        Horarios disponibles <span class="text-destructive">*</span>
                    </label>

                    <div v-if="!createForm.court_id || !createForm.date" class="rounded-xl bg-muted/50 py-6 text-center text-sm text-muted-foreground">
                        Selecciona cancha y fecha para ver los horarios
                    </div>

                    <div v-else-if="loadingSlots" class="rounded-xl bg-muted/50 py-6 text-center text-sm text-muted-foreground">
                        Cargando horarios...
                    </div>

                    <div v-else-if="availableSlots.length === 0" class="rounded-xl bg-muted/50 py-6 text-center text-sm text-muted-foreground">
                        No hay horarios disponibles para esta fecha
                    </div>

                    <div v-else class="space-y-2">
                        <button
                            v-for="slot in availableSlots"
                            :key="slot.id"
                            type="button"
                            @click="toggleSlot(slot.id)"
                            class="flex w-full items-center justify-between rounded-xl border px-4 py-3 text-sm font-medium transition"
                            :class="
                                createForm.slot_ids.includes(slot.id)
                                    ? 'border-primary bg-primary/10 text-primary'
                                    : 'border-border bg-card text-foreground hover:border-primary/50 hover:bg-muted'
                            "
                        >
                            <span class="flex items-center gap-2">
                                <Clock class="h-4 w-4 shrink-0" />
                                {{ slot.start_formatted }} – {{ slot.end_formatted }}
                            </span>
                            <span class="font-semibold">${{ slot.price.toLocaleString() }}</span>
                        </button>
                    </div>

                    <!-- Total seleccionado -->
                    <div
                        v-if="createForm.slot_ids.length > 0"
                        class="mt-2 flex items-center justify-between rounded-xl bg-primary/5 px-4 py-2.5"
                    >
                        <span class="text-xs text-muted-foreground">
                            {{ createForm.slot_ids.length }} horario(s) — Total
                        </span>
                        <span class="text-sm font-bold text-primary">
                            ${{ selectedSlotsTotal.toLocaleString() }}
                        </span>
                    </div>
                </div>

                <!-- Notas -->
                <div class="mb-6">
                    <label class="mb-1.5 block text-sm font-semibold text-foreground">
                        Notas <span class="font-normal text-muted-foreground">(opcional)</span>
                    </label>
                    <textarea
                        v-model="createForm.notes"
                        rows="2"
                        placeholder="Observaciones adicionales..."
                        class="w-full resize-none rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                    />
                </div>

                <!-- Botones -->
                <div class="flex gap-3">
                    <button
                        @click="closeCreateModal"
                        class="flex-1 rounded-xl border border-border py-3 text-sm font-medium text-foreground transition hover:bg-muted"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submitCreate"
                        :disabled="creating"
                        class="flex flex-1 items-center justify-center gap-2 rounded-xl bg-primary py-3 text-sm font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95 disabled:opacity-60"
                    >
                        <Plus class="h-4 w-4" />
                        {{ creating ? 'Creando...' : 'Crear reserva' }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>
