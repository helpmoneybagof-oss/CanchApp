<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import interactionPlugin from '@fullcalendar/interaction';
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import { ChevronLeft, ChevronRight, Goal, Lock, Unlock } from 'lucide-vue-next';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';
import axios from 'axios';

interface TimeSlot {
    id: number;
    court_id: number;
    date: string;
    start_time: string;
    end_time: string;
    start_formatted: string;
    end_formatted: string;
    status: 'available' | 'pre_reserved' | 'reserved' | 'blocked';
    price: number;
    pre_reserved_count: number;
    block_reason: string | null;
    reservation_id: number | null;
    is_finished: boolean;
}

interface Court {
    id: number;
    name: string;
    type: string | null;
    price_per_hour: number;
}

const props = defineProps<{
    today: string;
    courts: Court[];
    selected_court_id: number | null;
}>();

// ── Cancha seleccionada ──
const selectedCourtId = ref<number | null>(props.selected_court_id ?? props.courts[0]?.id ?? null);
const selectedCourt   = computed(() => props.courts.find(c => c.id === selectedCourtId.value) ?? props.courts[0] ?? null);

const slots         = ref<TimeSlot[]>([]);
const selectedSlots = ref<TimeSlot[]>([]);
const loading       = ref(false);
const calendarRef   = ref<any>(null);
const currentDate   = ref(props.today);
let currentRange    = { from: '', to: '' };

// Detectar si es móvil para cambiar vista
const isMobile = ref(false);
onMounted(() => {
    isMobile.value = window.innerWidth < 1024;
    window.addEventListener('resize', () => { isMobile.value = window.innerWidth < 1024; });
});
// Nota: carga inicial móvil se hace en el segundo onMounted al final del script

async function loadSlots(from: string, to: string) {
    if (!selectedCourtId.value) return;
    currentRange = { from, to };
    loading.value = true;
    try {
        const res = await axios.get('/admin/calendar/slots', {
            params: { from, to, court_id: selectedCourtId.value },
        });
        slots.value = res.data;
        // Actualizar FullCalendar directamente tras recibir los datos
        updateCalendarEvents(res.data);
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

function updateCalendarEvents(newSlots: TimeSlot[]) {
    const api = calendarRef.value?.getApi();
    if (!api) return;
    api.removeAllEvents();
    api.addEventSource(newSlots.map(slotToEvent));
}

// Al cambiar de cancha: limpiar selección y recargar
watch(selectedCourtId, () => {
    selectedSlots.value = [];
    const api = calendarRef.value?.getApi();
    if (api) {
        loadSlots(
            api.view.activeStart.toISOString().split('T')[0],
            api.view.activeEnd.toISOString().split('T')[0],
        );
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
            if (data.date >= currentRange.from && data.date <= currentRange.to) {
                refreshCalendar();
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

function slotToEvent(slot: TimeSlot) {
    const finished = slot.is_finished;
    return {
        id: String(slot.id),
        title: finished                                                               ? '🏁 Finalizado'
             : slot.status === 'reserved'                                            ? '🔒 Reservado'
             : slot.status === 'pre_reserved' && slot.pre_reserved_count > 0        ? `⏳ ${slot.pre_reserved_count} esperando pago`
             : slot.status === 'blocked'                                             ? `⛔ ${slot.block_reason ? slot.block_reason : 'Bloqueado'}`
             : `✅ $${slot.price.toLocaleString()}`,
        start: `${slot.date}T${slot.start_time}`,
        end:   `${slot.date}T${slot.end_time}`,
        backgroundColor:
            finished                                                              ? '#7c3aed'
            : slot.status === 'reserved'                                         ? '#3b82f6'
            : slot.status === 'pre_reserved' && slot.pre_reserved_count > 0      ? '#f59e0b'
            : slot.status === 'blocked'                                          ? '#6b7280'
            : '#10b981',
        borderColor:
            finished                                                              ? '#6d28d9'
            : slot.status === 'reserved'                                         ? '#2563eb'
            : slot.status === 'pre_reserved' && slot.pre_reserved_count > 0      ? '#d97706'
            : slot.status === 'blocked'                                          ? '#4b5563'
            : '#059669',
        textColor: '#ffffff',
        extendedProps: { slot, finished },
    };
}

const calendarOptions = computed(() => ({
    plugins: [dayGridPlugin, timeGridPlugin, interactionPlugin],
    initialView: isMobile.value ? 'timeGridDay' : 'timeGridWeek',
    initialDate: currentDate.value,
    locale: 'es',
    headerToolbar: false,
    slotMinTime: '17:00:00',
    slotMaxTime: '23:00:00',
    slotDuration: '01:00:00',
    allDaySlot: false,
    height: 'auto',
    events: [],
    eventClick: handleEventClick,
    datesSet: handleDatesSet,
    nowIndicator: true,
    expandRows: true,
    selectable: true,
    slotLabelFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short', hour12: true },
    // En móvil ocultar columna de hora para ganar espacio
    slotLabelInterval: '01:00',
}));


function handleEventClick(info: any) {
    const slot: TimeSlot = info.event.extendedProps.slot;
    const finished: boolean = info.event.extendedProps.finished;

    // Slots reservados (incluye finalizados) o pre-reservados → navegar al detalle de la reserva
    if ((slot.status === 'reserved' || slot.status === 'pre_reserved') && slot.reservation_id) {
        router.visit(`/admin/reservations/${slot.reservation_id}`);
        return;
    }

    // Disponibles y bloqueados → seleccionar para bloquear/desbloquear
    if (slot.status === 'reserved' || slot.status === 'pre_reserved') return;
    const idx = selectedSlots.value.findIndex((s) => s.id === slot.id);
    if (idx === -1) selectedSlots.value.push(slot);
    else selectedSlots.value.splice(idx, 1);
}

async function handleDatesSet(info: any) {
    const from = info.startStr.split('T')[0];
    const to   = info.endStr.split('T')[0];
    await loadSlots(from, to);
}

async function blockSelected() {
    const ids = selectedSlots.value.filter(s => s.status === 'available').map(s => s.id);
    if (!ids.length) return;
    const reason = prompt('Razón del bloqueo (opcional):') ?? '';
    await axios.post('/admin/slots/block', { slot_ids: ids, reason });
    selectedSlots.value = [];
    refreshCalendar();
}

async function unblockSelected() {
    const ids = selectedSlots.value.filter(s => s.status === 'blocked').map(s => s.id);
    if (!ids.length) return;
    await axios.post('/admin/slots/unblock', { slot_ids: ids });
    selectedSlots.value = [];
    refreshCalendar();
}

function refreshCalendar() {
    const api = calendarRef.value?.getApi();
    if (api) loadSlots(api.view.activeStart.toISOString().split('T')[0], api.view.activeEnd.toISOString().split('T')[0]);
}

function prev() {
    if (isMobile.value) {
        // Retroceder 7 días en móvil
        const d = new Date(mobileSelectedDate.value + 'T00:00:00');
        d.setDate(d.getDate() - 7);
        const newDate = d.toISOString().split('T')[0];
        mobileWeekStart.value = newDate;
        selectMobileDay(newDate);
    } else {
        calendarRef.value?.getApi().prev();
    }
}
function next() {
    if (isMobile.value) {
        const d = new Date(mobileSelectedDate.value + 'T00:00:00');
        d.setDate(d.getDate() + 7);
        const newDate = d.toISOString().split('T')[0];
        mobileWeekStart.value = newDate;
        selectMobileDay(newDate);
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
    }
}

// ── Móvil: semana de 7 días scrollable ──
const mobileWeekStart  = ref(props.today);
const mobileSelectedDate = ref(props.today);

const mobileDays = computed(() => {
    const days = [];
    const start = new Date(mobileWeekStart.value + 'T00:00:00');
    // Ir al lunes de la semana
    const dow = start.getDay(); // 0=dom
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
    // Cargar slots de toda la semana del día seleccionado
    const d = new Date(date + 'T00:00:00');
    const dow = d.getDay();
    const monday = new Date(d);
    monday.setDate(d.getDate() - (dow === 0 ? 6 : dow - 1));
    const sunday = new Date(monday);
    sunday.setDate(monday.getDate() + 6);
    const from = monday.toISOString().split('T')[0];
    const to   = sunday.toISOString().split('T')[0];
    await loadSlots(from, to);
}

function handleMobileSlotClick(slot: TimeSlot) {
    // Reservados/pre-reservados → navegar al detalle
    if ((slot.status === 'reserved' || slot.status === 'pre_reserved') && slot.reservation_id) {
        router.visit(`/admin/reservations/${slot.reservation_id}`);
        return;
    }
    // Disponibles/bloqueados → seleccionar para bloquear/desbloquear
    if (slot.status === 'available' || slot.status === 'blocked') {
        const idx = selectedSlots.value.findIndex(s => s.id === slot.id);
        if (idx === -1) selectedSlots.value.push(slot);
        else selectedSlots.value.splice(idx, 1);
    }
}

// Cargar semana actual al montar en móvil
onMounted(() => {
    if (isMobile.value) selectMobileDay(props.today);
});
</script>

<template>
    <Head title="Calendario Admin" />
    <AppAdminLayout>
        <div class="p-3 lg:p-6 overflow-x-hidden">

            <!-- Selector de cancha (desplegable) -->
            <div v-if="courts.length > 1" class="mb-3">
                <div class="relative">
                    <Goal class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-primary" />
                    <select
                        :value="selectedCourtId"
                        @change="selectedCourtId = Number(($event.target as HTMLSelectElement).value)"
                        class="w-full appearance-none rounded-2xl border border-border bg-card py-2.5 pl-9 pr-10 text-sm font-semibold text-foreground shadow-sm focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                        <option v-for="c in courts" :key="c.id" :value="c.id">
                            {{ c.name }} — ${{ Number(c.price_per_hour).toLocaleString('es-CO') }}/h
                        </option>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7" />
                    </svg>
                </div>
            </div>

            <!-- Sin canchas -->
            <div v-if="courts.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-12 text-center">
                <Goal class="mb-3 h-10 w-10 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">Sin canchas registradas</p>
                <a href="/admin/courts" class="mt-2 text-sm text-primary underline">Crear canchas</a>
            </div>

            <template v-else>
                <!-- Navegación de fecha -->
                <div class="mb-3 flex items-center gap-2">
                    <button @click="prev" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground active:scale-95 transition">
                        <ChevronLeft class="h-4 w-4" />
                    </button>
                    <button @click="goToday" class="flex-1 rounded-xl border border-border bg-card py-2 text-sm font-medium text-foreground active:scale-95 transition">
                        {{ isMobile ? 'Hoy' : 'Esta semana' }}
                        <span v-if="loading" class="ml-1 animate-pulse text-xs text-muted-foreground">·</span>
                    </button>
                    <button @click="next" class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl border border-border bg-card text-foreground active:scale-95 transition">
                        <ChevronRight class="h-4 w-4" />
                    </button>
                </div>

                <!-- ══ MÓVIL: Lista de slots tipo nativa iOS ══ -->
                <div class="lg:hidden min-w-0 w-full">
                    <!-- Selector de día (sin scroll, distribuido en toda la fila) -->
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
                        </button>
                    </div>

                    <!-- Fecha seleccionada -->
                    <p class="mb-3 text-sm font-semibold capitalize text-foreground">{{ mobileSelectedDateFormatted }}</p>

                    <!-- Leyenda compacta -->
                    <div class="mb-3 text-xs">
                        <div class="mb-1.5 flex gap-3">
                            <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-500"></span><span class="text-muted-foreground">Disponible</span></div>
                            <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-amber-500"></span><span class="text-muted-foreground">Esperando</span></div>
                            <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-blue-500"></span><span class="text-muted-foreground">Reservado</span></div>
                            <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-violet-600"></span><span class="text-muted-foreground">Finalizado</span></div>
                        </div>
                        <div class="flex gap-3">
                            <div class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 shrink-0 rounded-full bg-gray-500"></span><span class="text-muted-foreground">Bloqueado</span></div>
                        </div>
                    </div>

                    <!-- Acciones bloqueo -->
                    <div v-if="selectedSlots.length > 0" class="mb-3 flex items-center gap-2">
                        <span class="flex-1 text-xs font-medium text-primary">{{ selectedSlots.length }} seleccionado(s)</span>
                        <button v-if="selectedSlots.some(s => s.status === 'available')" @click="blockSelected"
                            class="flex items-center gap-1.5 rounded-xl bg-gray-600 px-3 py-2 text-xs font-bold text-white active:scale-95">
                            <Lock class="h-3.5 w-3.5" /> Bloquear
                        </button>
                        <button v-if="selectedSlots.some(s => s.status === 'blocked')" @click="unblockSelected"
                            class="flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground active:scale-95">
                            <Unlock class="h-3.5 w-3.5" /> Desbloquear
                        </button>
                    </div>

                    <!-- Lista de slots del día seleccionado -->
                    <div class="space-y-2 w-full overflow-hidden">
                        <div v-if="mobileDaySlots.length === 0" class="rounded-2xl border border-dashed border-border bg-card py-10 text-center text-sm text-muted-foreground">
                            Sin horarios para este día
                        </div>
                        <button
                            v-for="slot in mobileDaySlots" :key="slot.id"
                            @click="handleMobileSlotClick(slot)"
                            class="flex w-full items-center gap-2.5 rounded-2xl border-2 px-3 py-3 text-left transition active:scale-[0.98]"
                            :class="[
                                selectedSlots.find(s => s.id === slot.id)
                                    ? 'border-primary'
                                    : slot.status === 'available' || (slot.status === 'pre_reserved' && slot.pre_reserved_count === 0) ? 'border-emerald-200 bg-emerald-50 dark:border-emerald-900 dark:bg-emerald-950/40' :
                                      slot.status === 'pre_reserved' ? 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950/40' :
                                      slot.status === 'reserved' ? 'border-blue-200 bg-blue-50 dark:border-blue-900 dark:bg-blue-950/40' :
                                      slot.is_finished ? 'border-violet-200 bg-violet-50 dark:border-violet-900 dark:bg-violet-950/40' :
                                      'border-border bg-card'
                            ]"
                        >
                            <!-- Dot de color -->
                            <span class="h-3 w-3 shrink-0 rounded-full"
                                :class="slot.is_finished ? 'bg-violet-500' :
                                    slot.status === 'reserved' ? 'bg-blue-500' :
                                    slot.status === 'pre_reserved' && slot.pre_reserved_count > 0 ? 'bg-amber-500' :
                                    slot.status === 'blocked' ? 'bg-gray-400' :
                                    'bg-emerald-500'"
                            ></span>
                            <!-- Hora -->
                            <div class="min-w-0 flex-1 overflow-hidden">
                                <p class="truncate text-sm font-bold text-foreground">{{ slot.start_formatted }} – {{ slot.end_formatted }}</p>
                                <p class="truncate text-xs text-muted-foreground">
                                    {{ slot.is_finished ? '🏁 Finalizado' :
                                       slot.status === 'reserved' ? '🔒 Reservado' :
                                       slot.status === 'pre_reserved' && slot.pre_reserved_count > 0 ? `⏳ ${slot.pre_reserved_count} esperando pago` :
                                       slot.status === 'blocked' ? `⛔ ${slot.block_reason || 'Bloqueado'}` :
                                       `✅ $${slot.price.toLocaleString()}` }}
                                </p>
                            </div>
                            <!-- Chevron para reservados -->
                            <ChevronRight v-if="slot.status === 'reserved' || slot.status === 'pre_reserved'" class="h-4 w-4 shrink-0 text-muted-foreground" />
                            <!-- Checkbox para disponibles/bloqueados -->
                            <div v-else-if="slot.status === 'available' || slot.status === 'blocked'"
                                class="flex h-5 w-5 shrink-0 items-center justify-center rounded-md border-2 transition"
                                :class="selectedSlots.find(s => s.id === slot.id) ? 'border-primary bg-primary' : 'border-border bg-background'">
                                <svg v-if="selectedSlots.find(s => s.id === slot.id)" class="h-3 w-3 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </div>
                        </button>
                    </div>
                </div>

                <!-- ══ DESKTOP: FullCalendar semanal ══ -->
                <div class="hidden lg:block min-w-0 w-full">
                    <!-- Acciones bloqueo desktop -->
                    <div v-if="selectedSlots.length > 0" class="mb-3 flex items-center gap-2">
                        <span class="flex-1 text-xs font-semibold text-primary">{{ selectedSlots.length }} slot(s) seleccionado(s)</span>
                        <button v-if="selectedSlots.some(s => s.status === 'available')" @click="blockSelected"
                            class="flex items-center gap-1.5 rounded-xl bg-gray-600 px-3 py-2 text-xs font-semibold text-white hover:bg-gray-700">
                            <Lock class="h-3.5 w-3.5" /> Bloquear
                        </button>
                        <button v-if="selectedSlots.some(s => s.status === 'blocked')" @click="unblockSelected"
                            class="flex items-center gap-1.5 rounded-xl bg-primary px-3 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary/90">
                            <Unlock class="h-3.5 w-3.5" /> Desbloquear
                        </button>
                    </div>
                    <!-- Leyenda -->
                    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs">
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-emerald-500"></span><span class="text-muted-foreground">Disponible</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-amber-500"></span><span class="text-muted-foreground">Esperando pago</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-blue-500"></span><span class="text-muted-foreground">Reservado</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-violet-600"></span><span class="text-muted-foreground">Finalizado</span></div>
                        <div class="flex items-center gap-1.5"><span class="h-3 w-3 rounded-full bg-gray-500"></span><span class="text-muted-foreground">Bloqueado</span></div>
                    </div>
                    <div class="overflow-hidden rounded-2xl border border-border bg-card shadow-sm w-full min-w-0">
                        <div class="w-full overflow-x-auto">
                            <FullCalendar ref="calendarRef" :options="calendarOptions" class="fc-theme-custom" />
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </AppAdminLayout>
</template>

<style>
.fc-theme-custom .fc-timegrid-slot { height: 56px !important; }
.fc-theme-custom .fc-scrollgrid { border: none !important; }
</style>
