<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { CalendarDays, ChevronLeft, ChevronRight, Clock, Goal, X } from 'lucide-vue-next';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import { useToast } from '@/composables/useToast';
import { useRealtimeSlots } from '@/composables/useRealtime';
import axios from 'axios';

const toast = useToast();

interface TimeSlot {
    id: number;
    court_id: number;
    date: string;
    start_time: string;
    end_time: string;
    start_formatted: string;
    end_formatted: string;
    status: 'available' | 'pre_reserved' | 'pending_payment' | 'reserved' | 'blocked';
    price: number;
    block_reason: string | null;
    pre_reserved_count: number;
}

interface Court {
    id: number;
    name: string;
    type: string | null;
    price_per_hour: number;
    surface: string | null;
    capacity: number | null;
    start_hour: number;
    end_hour: number;
}

const props = defineProps<{
    initialSlots: TimeSlot[];
    today: string;
    courts: Court[];
    selected_court_id: number | null;
}>();

const page = usePage();
const isAuth = computed(() => !!page.props.auth?.user);

// ── Cancha seleccionada ──
const selectedCourtId = ref<number | null>(props.selected_court_id ?? props.courts[0]?.id ?? null);
const selectedCourt   = computed(() => props.courts.find(c => c.id === selectedCourtId.value) ?? props.courts[0] ?? null);

// ── Estado del calendario ──
const slots        = ref<TimeSlot[]>(props.initialSlots);
const selectedDate = ref(props.today);
const selectedSlots = ref<TimeSlot[]>([]);
const loading       = ref(false);
const showModal     = ref(false);
const clickedSlot   = ref<TimeSlot | null>(null);
const calendarRef   = ref<any>(null);

// ── Función ÚNICA de carga ───────────────────────────────────────────────────
// Carga slots de un rango de fechas y actualiza tanto slots.value como FullCalendar
async function loadSlots(from: string, to: string) {
    if (!selectedCourtId.value) return;
    loading.value = true;
    try {
        const dates: string[] = [];
        const start = new Date(from + 'T00:00:00');
        const end   = new Date(to   + 'T00:00:00');
        for (let d = new Date(start); d <= end; d.setDate(d.getDate() + 1)) {
            dates.push(new Date(d).toISOString().split('T')[0]);
        }
        const results = await Promise.all(
            dates.map(date =>
                axios.get('/api/slots/day', {
                    params: { date, court_id: selectedCourtId.value },
                }).then(r => r.data).catch(() => [])
            )
        );
        const allSlots = results.flat();
        slots.value = allSlots;
        updateCalendarEvents(allSlots);
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

// Helper: lunes–domingo de la semana de una fecha
function weekRange(date: string): { from: string; to: string } {
    const d = new Date(date + 'T00:00:00');
    const dow = d.getDay();
    const mon = new Date(d);
    mon.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));
    const sun = new Date(mon);
    sun.setDate(mon.getDate() + 6);
    return { from: mon.toISOString().split('T')[0], to: sun.toISOString().split('T')[0] };
}

// Alias simple para cargar la semana de un día (usado en varios lugares)
async function loadSlotsForDay(date: string) {
    selectedDate.value = date;
    const { from, to } = weekRange(date);
    await loadSlots(from, to);
}

function updateCalendarEvents(newSlots: TimeSlot[]) {
    const api = calendarRef.value?.getApi();
    if (!api) return;
    api.removeAllEvents();
    api.addEventSource(newSlots.map((slot) => ({
        id: String(slot.id),
        title: slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0)
            ? `$${slot.price.toLocaleString()}`
            : slot.status === 'pending_payment'
                ? 'Pago en verificación'
                : slot.status === 'pre_reserved'
                    ? `${slot.pre_reserved_count} esperando pago`
                    : slot.status === 'reserved'
                        ? 'Ocupado'
                        : slot.block_reason
                            ? `Bloqueado: ${slot.block_reason}`
                            : 'Bloqueado',
        start: `${slot.date}T${slot.start_time}`,
        end:   `${slot.date}T${slot.end_time}`,
        backgroundColor:
            (slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0)) ? '#10b981'
            : slot.status === 'pending_payment' ? '#3b82f6'
            : slot.status === 'pre_reserved' ? '#f59e0b'
            : slot.status === 'reserved'     ? '#ef4444'
            : '#6b7280',
        borderColor:
            (slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0)) ? '#059669'
            : slot.status === 'pending_payment' ? '#2563eb'
            : slot.status === 'pre_reserved' ? '#d97706'
            : slot.status === 'reserved'     ? '#dc2626'
            : '#4b5563',
        textColor: '#ffffff',
        extendedProps: { slot },
    })));
}

// Al cambiar de cancha: limpiar selección y recargar slots
watch(selectedCourtId, () => {
    selectedSlots.value = [];
    slots.value = [];
    if (isMobile.value) {
        const { from, to } = weekRange(mobileSelectedDate.value);
        loadSlots(from, to);
    } else {
        // En desktop forzar recarga con el rango actual del calendario
        const api = calendarRef.value?.getApi();
        if (api) {
            const from = api.view.activeStart.toISOString().split('T')[0];
            const endDate = new Date(api.view.activeEnd);
            endDate.setDate(endDate.getDate() - 1);
            const to = endDate.toISOString().split('T')[0];
            loadSlots(from, to);
        } else {
            const { from, to } = weekRange(selectedDate.value);
            loadSlots(from, to);
        }
    }
});

// Realtime: suscripción reactiva al canal de slots — se re-suscribe cuando cambia la cancha
let realtimeChannel: any = null;
function subscribeToSlots(courtId: number) {
    if (!courtId) return;
    if (realtimeChannel) {
        window.Echo?.leave(`slots.${realtimeChannel._courtId}`);
    }
    const ch = window.Echo?.channel(`slots.${courtId}`);
    if (ch) {
        ch._courtId = courtId;
        ch.listen('.slot.changed', (data: { court_id: number; date: string }) => {
            if (data.date === selectedDate.value) {
                loadSlotsForDay(selectedDate.value);
            }
        });
        realtimeChannel = ch;
    }
}

watch(selectedCourtId, (newId) => {
    if (newId) subscribeToSlots(newId);
}, { immediate: true });

onUnmounted(() => {
    if (realtimeChannel) window.Echo?.leave(`slots.${realtimeChannel._courtId}`);
});

// ── FullCalendar ──
const calendarEvents = computed(() =>
    slots.value.map((slot) => ({
        id: String(slot.id),
        title: slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0)
            ? `$${slot.price.toLocaleString()}`
            : slot.status === 'pending_payment'
                ? 'Pago en verificación'
                : slot.status === 'pre_reserved'
                    ? `${slot.pre_reserved_count} esperando pago`
                    : slot.status === 'reserved'
                        ? 'Ocupado'
                        : slot.block_reason
                            ? `Bloqueado: ${slot.block_reason}`
                            : 'Bloqueado',
        start: `${slot.date}T${slot.start_time}`,
        end:   `${slot.date}T${slot.end_time}`,
        backgroundColor:
            (slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0)) ? '#10b981'
            : slot.status === 'pending_payment' ? '#3b82f6'
            : slot.status === 'pre_reserved' ? '#f59e0b'
            : slot.status === 'reserved'   ? '#ef4444'
            : '#6b7280',
        borderColor:
            (slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0)) ? '#059669'
            : slot.status === 'pending_payment' ? '#2563eb'
            : slot.status === 'pre_reserved' ? '#d97706'
            : slot.status === 'reserved'   ? '#dc2626'
            : '#4b5563',
        textColor: '#ffffff',
        extendedProps: { slot },
    }))
);

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: 'timeGridWeek',
    initialDate: selectedDate.value,
    locale: 'es',
    headerToolbar: false,
    slotMinTime: selectedCourt.value ? `${String(selectedCourt.value.start_hour).padStart(2,'0')}:00:00` : '17:00:00',
    slotMaxTime: selectedCourt.value ? `${String(selectedCourt.value.end_hour).padStart(2,'0')}:00:00` : '23:00:00',
    slotDuration: '01:00:00',
    allDaySlot: false,
    height: 'auto',
    events: [],
    eventClick: handleEventClick,
    datesSet: handleDatesSet,
    nowIndicator: true,
    expandRows: true,
    slotLabelFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
}));

function handleEventClick(info: any) {
    const slot: TimeSlot = info.event.extendedProps.slot;
    // Disponible y pre_reserved son reservables (pending_payment NO)
    if (slot.status !== 'available' && slot.status !== 'pre_reserved') return;
    clickedSlot.value = slot;
    showModal.value = true;
}

async function handleDatesSet(info: any) {
    // Solo cargar en desktop (en móvil lo maneja el selector de días)
    if (isMobile.value) return;
    const from = info.startStr.split('T')[0];
    const endDate = new Date(info.endStr.split('T')[0] + 'T00:00:00');
    endDate.setDate(endDate.getDate() - 1);
    const to = endDate.toISOString().split('T')[0];
    await loadSlots(from, to);
}

function addSlotToSelection() {
    if (!clickedSlot.value) return;
    const already = selectedSlots.value.find((s) => s.id === clickedSlot.value!.id);
    if (!already) selectedSlots.value.push(clickedSlot.value);
    showModal.value = false;
}

function removeSlot(slotId: number) {
    selectedSlots.value = selectedSlots.value.filter((s) => s.id !== slotId);
}

const totalPrice = computed(() => selectedSlots.value.reduce((sum, s) => sum + s.price, 0));

const savingSlots = ref(false);

async function goToCatalog() {
    if (!isAuth.value) { router.visit('/login'); return; }
    savingSlots.value = true;
    try {
        // Guardar slots en el carrito de sesión
        await axios.post('/cart/slots', {
            slot_ids: selectedSlots.value.map((s) => s.id),
        });
        // Ir al catálogo con banner "¿Algo más?"
        router.visit('/catalog?from_calendar=1');
    } catch (e: any) {
        toast.error(e?.response?.data?.message ?? 'Error al guardar horarios.', 'Error');
    } finally {
        savingSlots.value = false;
    }
}

// ── Detectar móvil ──
const isMobile = ref(false);
onMounted(() => {
    isMobile.value = window.innerWidth < 1024;
    window.addEventListener('resize', () => { isMobile.value = window.innerWidth < 1024; });

    // Carga inicial: en móvil cargamos la semana manualmente
    // En desktop, FullCalendar dispara handleDatesSet automáticamente
    if (isMobile.value) {
        mobileSelectedDate.value = props.today;
        selectedDate.value = props.today;
        const { from, to } = weekRange(props.today);
        loadSlots(from, to);
    }
});

// ── Navegación ──
function prevDay() {
    if (isMobile.value) {
        const d = new Date(mobileWeekStart.value + 'T00:00:00');
        d.setDate(d.getDate() - 7);
        const newDate = d.toISOString().split('T')[0];
        mobileWeekStart.value = newDate;
        mobileSelectedDate.value = newDate;
        selectedDate.value = newDate;
        const { from, to } = weekRange(newDate);
        loadSlots(from, to);
    } else {
        calendarRef.value?.getApi().prev();
    }
}
function nextDay() {
    if (isMobile.value) {
        const d = new Date(mobileWeekStart.value + 'T00:00:00');
        d.setDate(d.getDate() + 7);
        const newDate = d.toISOString().split('T')[0];
        mobileWeekStart.value = newDate;
        mobileSelectedDate.value = newDate;
        selectedDate.value = newDate;
        const { from, to } = weekRange(newDate);
        loadSlots(from, to);
    } else {
        calendarRef.value?.getApi().next();
    }
}
function goToday() {
    if (isMobile.value) {
        mobileWeekStart.value = props.today;
        selectMobileDay(props.today);
    } else {
        calendarRef.value?.getApi().today();
        loadSlotsForDay(props.today);
    }
}

// ── Móvil: semana scrollable ──
const mobileWeekStart    = ref(props.today);
const mobileSelectedDate = ref(props.today);

const mobileDays = computed(() => {
    const days = [];
    const start = new Date(mobileWeekStart.value + 'T00:00:00');
    const dow = start.getDay();
    const monday = new Date(start);
    monday.setDate(start.getDate() - (dow === 0 ? 6 : dow - 1));
    for (let i = 0; i < 7; i++) {
        const d = new Date(monday);
        d.setDate(monday.getDate() + i);
        const dateStr = d.toISOString().split('T')[0];
        days.push({
            date: dateStr,
            dow: d.toLocaleDateString('es-CO', { weekday: 'short' }).slice(0, 3),
            num: d.getDate(),
            isToday: dateStr === props.today,
        });
    }
    return days;
});

const mobileDaySlots = computed(() =>
    slots.value.filter(s => s.date === mobileSelectedDate.value)
        .sort((a, b) => a.start_time.localeCompare(b.start_time))
);

const mobileSelectedDateFormatted = computed(() => {
    const d = new Date(mobileSelectedDate.value + 'T00:00:00');
    return d.toLocaleDateString('es-CO', { weekday: 'long', day: 'numeric', month: 'long' });
});

async function selectMobileDay(date: string) {
    mobileSelectedDate.value = date;
    selectedDate.value = date;
    // Si ya tenemos slots de este día en memoria, no recargar
    if (slots.value.some(s => s.date === date)) return;
    // Si no, cargar la semana completa
    const { from, to } = weekRange(date);
    await loadSlots(from, to);
}


function handleMobileSlotClick(slot: TimeSlot) {
    if (slot.status !== 'available' && slot.status !== 'pre_reserved') return;
    clickedSlot.value = slot;
    showModal.value = true;
}


const formattedSelectedDate = computed(() => {
    const d = new Date(selectedDate.value + 'T00:00:00');
    return d.toLocaleDateString('es-CO', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
});
</script>

<template>
    <Head title="Calendario de disponibilidad" />
    <AppClientLayout>
        <div class="p-3 lg:p-6 overflow-x-hidden">

            <!-- Selector de cancha (desplegable) -->
            <div v-if="courts.length >= 1" class="mb-3">
                <div class="relative">
                    <Goal class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
                    <select
                        :value="selectedCourtId"
                        @change="selectedCourtId = Number(($event.target as HTMLSelectElement).value)"
                        class="w-full appearance-none rounded-2xl border border-border bg-card py-2.5 pl-9 pr-10 text-sm font-semibold text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        <option v-for="c in courts" :key="c.id" :value="c.id">
                            {{ c.name }} — ${{ c.price_per_hour.toLocaleString() }}/h
                        </option>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Sin canchas -->
            <div v-if="courts.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <Goal class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">No hay canchas disponibles</p>
                <p class="text-sm text-muted-foreground">Contacta al establecimiento para más información.</p>
            </div>

            <template v-else>
                <!-- Navegación -->
                <div class="mb-3 flex items-center gap-2">
                    <button @click="prevDay" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground active:scale-95 transition">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button @click="goToday" class="flex-1 rounded-xl border border-border bg-card py-2 text-sm font-medium text-foreground active:scale-95 transition">
                        Hoy
                        <span v-if="loading" class="ml-1 animate-pulse text-xs text-muted-foreground">·</span>
                    </button>
                    <button @click="nextDay" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground active:scale-95 transition">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <!-- ══ MÓVIL: Lista nativa iOS ══ -->
                <div class="lg:hidden">
                    <!-- Selector de días (sin scroll, distribuido en toda la fila) -->
                    <div class="mb-3 flex gap-1">
                        <button
                            v-for="day in mobileDays" :key="day.date"
                            @click="selectMobileDay(day.date)"
                            class="flex flex-1 flex-col items-center rounded-2xl py-2 transition active:scale-95"
                            :class="day.date === mobileSelectedDate
                                ? 'bg-primary text-primary-foreground shadow-md'
                                : 'bg-card border border-border text-foreground'"
                        >
                            <span class="text-[10px] font-medium uppercase opacity-70">{{ day.dow }}</span>
                            <span class="text-base font-bold leading-tight">{{ day.num }}</span>
                            <span
                                class="mt-0.5 h-1.5 w-1.5 rounded-full transition"
                                :class="day.isToday
                                    ? (day.date === mobileSelectedDate ? 'bg-primary-foreground' : 'bg-primary')
                                    : 'bg-transparent'"
                            ></span>
                        </button>
                    </div>

                    <!-- Fecha seleccionada -->
                    <p class="mb-3 text-sm font-semibold capitalize text-foreground">{{ mobileSelectedDateFormatted }}</p>


                    <!-- Lista de slots -->
                    <div class="space-y-2">
                        <div v-if="mobileDaySlots.length === 0" class="rounded-2xl border border-dashed border-border bg-card py-10 text-center text-sm text-muted-foreground">
                            Sin horarios para este día
                        </div>
                        <button
                            v-for="slot in mobileDaySlots" :key="slot.id"
                            @click="handleMobileSlotClick(slot)"
                            :disabled="slot.status === 'reserved' || slot.status === 'blocked' || slot.status === 'pending_payment'"
                            class="flex w-full items-center gap-2.5 rounded-2xl border-2 px-3 py-3 text-left transition active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-60"
                            :class="[
                                selectedSlots.find(s => s.id === slot.id) ? 'border-primary' :
                                    slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0) ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' :
                                    slot.status === 'pre_reserved' ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40' :
                                    slot.status === 'pending_payment' ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40' :
                                    slot.status === 'reserved' ? 'border-red-200 bg-red-50 dark:border-red-900 dark:bg-red-950/40' :
                                    'border-border bg-card'
                            ]"
                        >
                            <span class="h-3 w-3 shrink-0 rounded-full"
                                :class="slot.status === 'reserved' ? 'bg-red-500' :
                                    slot.status === 'pending_payment' ? 'bg-blue-500' :
                                    slot.status === 'pre_reserved' && slot.pre_reserved_count > 0 ? 'bg-amber-500' :
                                    slot.status === 'blocked' ? 'bg-gray-400' : 'bg-emerald-500'"
                            ></span>
                            <div class="min-w-0 flex-1">
                                <p class="text-sm font-bold text-foreground">{{ slot.start_formatted }} – {{ slot.end_formatted }}</p>
                                <p class="text-xs text-muted-foreground">
                                    {{ slot.status === 'reserved' ? '🔒 Ocupado' :
                                       slot.status === 'pending_payment' ? '💳 Pago en verificación' :
                                       slot.status === 'pre_reserved' && slot.pre_reserved_count > 0 ? `⏳ ${slot.pre_reserved_count} esperando pago` :
                                       slot.status === 'blocked' ? `⛔ ${slot.block_reason || 'Bloqueado'}` :
                                       `✅ $${slot.price.toLocaleString()}` }}
                                </p>
                            </div>
                            <!-- Indicador seleccionado -->
                            <div v-if="slot.status === 'available' || slot.status === 'pre_reserved'"
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition"
                                :class="selectedSlots.find(s => s.id === slot.id) ? 'border-primary bg-primary' : 'border-border bg-background'">
                                <svg v-if="selectedSlots.find(s => s.id === slot.id)" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                            <ChevronRight v-else class="h-4 w-4 shrink-0 text-muted-foreground/40" />
                        </button>
                    </div>
                </div>

                <!-- ══ DESKTOP: FullCalendar ══ -->
                <div class="hidden lg:block">
                    <!-- Leyenda -->
                    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs">
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500"></span><span class="text-muted-foreground">Disponible</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-amber-500"></span><span class="text-muted-foreground">Esperando pago</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-blue-500"></span><span class="text-muted-foreground">Pago en verificación</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-red-500"></span><span class="text-muted-foreground">Ocupado</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-gray-500"></span><span class="text-muted-foreground">Bloqueado</span></div>
                        <div v-if="loading" class="ml-auto animate-pulse text-xs text-muted-foreground">Cargando...</div>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm">
                        <FullCalendar ref="calendarRef" :options="calendarOptions" class="fc-theme-custom" />
                    </div>
                </div>

                <!-- Selección actual (ambas vistas) -->
                <div v-if="selectedSlots.length > 0" class="mt-4 rounded-2xl border border-primary/30 bg-primary/5 p-4">
                    <h3 class="mb-3 text-sm font-semibold text-foreground">
                        Horarios seleccionados ({{ selectedSlots.length }}) · {{ selectedCourt?.name }}
                    </h3>
                    <div class="mb-3 space-y-2">
                        <div v-for="slot in selectedSlots" :key="slot.id"
                            class="flex items-center justify-between rounded-xl bg-background p-3 shadow-sm">
                            <div class="flex items-center gap-2">
                                <Clock class="h-4 w-4 text-primary" />
                                <span class="text-sm font-medium text-foreground">{{ slot.start_formatted }} – {{ slot.end_formatted }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-sm font-semibold text-primary">${{ slot.price.toLocaleString() }}</span>
                                <button @click="removeSlot(slot.id)"
                                    class="flex h-6 w-6 items-center justify-center rounded-full bg-muted text-muted-foreground transition hover:bg-destructive hover:text-white">
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center justify-between border-t border-border pt-3">
                        <div>
                            <p class="text-xs text-muted-foreground">Total</p>
                            <p class="text-lg font-bold text-foreground">${{ totalPrice.toLocaleString() }}</p>
                        </div>
                        <button @click="goToCatalog" :disabled="savingSlots"
                            class="flex items-center gap-2 rounded-xl bg-primary px-5 py-2.5 text-sm font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95 disabled:opacity-60">
                            <CalendarDays class="h-4 w-4" />
                            {{ !isAuth ? 'Iniciar sesión para reservar' : savingSlots ? 'Guardando...' : 'Continuar' }}
                        </button>
                    </div>
                </div>
            </template>
        </div>

        <!-- Modal de confirmación de slot -->
        <Teleport to="body">
            <div v-if="showModal && clickedSlot"
                class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm lg:items-center"
                @click.self="showModal = false">
                <div class="w-full max-w-sm rounded-t-3xl bg-background p-6 shadow-xl lg:rounded-3xl">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-lg font-bold text-foreground">Reservar horario</h3>
                        <button @click="showModal = false"
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-muted text-muted-foreground">
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                    <div class="mb-4 rounded-2xl bg-muted/50 p-4 space-y-3">
                        <div class="flex items-center gap-3">
                            <Goal class="h-5 w-5 text-primary shrink-0" />
                            <div>
                                <p class="text-xs text-muted-foreground">Cancha</p>
                                <p class="font-semibold text-foreground">{{ selectedCourt?.name }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <Clock class="h-5 w-5 text-primary shrink-0" />
                            <div>
                                <p class="text-xs text-muted-foreground">Horario</p>
                                <p class="font-semibold text-foreground">{{ clickedSlot.start_formatted }} – {{ clickedSlot.end_formatted }}</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <CalendarDays class="h-5 w-5 text-primary shrink-0" />
                            <div>
                                <p class="text-xs text-muted-foreground">Precio</p>
                                <p class="font-semibold text-foreground">${{ clickedSlot.price.toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>
                    <button @click="addSlotToSelection"
                        class="w-full rounded-2xl bg-primary py-3 text-base font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-95">
                        Agregar al carrito
                    </button>
                </div>
            </div>
        </Teleport>
    </AppClientLayout>
</template>

<style>
.fc-theme-custom .fc-timegrid-slot { height: 60px !important; }
.fc-theme-custom .fc-scrollgrid { border: none !important; }
</style>
