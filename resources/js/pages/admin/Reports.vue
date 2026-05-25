<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    Activity,
    ArrowDownRight,
    ArrowUpRight,
    BarChart3,
    CalendarDays,
    Clock,
    DollarSign,
    Goal,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

interface Summary {
    total_reservations: number;
    total_income: number;
    court_income: number;
    pending_payments: number;
    cancelled_reservations: number;
}

interface Comparison {
    prev_income: number;
    prev_reservations: number;
    income_change: number | null;
    reservations_change: number | null;
}

interface DailyItem { date: string; income: number; count: number; }
interface WeekdayItem { day: string; count: number; }
interface HourlyItem { hour: number; label: string; count: number; }
interface PaymentItem { key: string; label: string; count: number; }
interface TopClient { name: string; email: string; total_reservations: number; total_spent: number; }
interface RecentReservation {
    id: number;
    user_name: string;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    payment_status: string;
    total_price: number;
    confirmation_code: string;
}

const props = defineProps<{
    filters: { from: string; to: string };
    summary: Summary;
    comparison: Comparison;
    daily_income: DailyItem[];
    by_weekday: WeekdayItem[];
    hourly_distribution: HourlyItem[];
    payment_breakdown: PaymentItem[];
    top_clients: TopClient[];
    most_reserved_court: { name: string; type: string | null; count: number } | null;
    recent_reservations: RecentReservation[];
}>();

const filterFrom = ref(props.filters.from);
const filterTo   = ref(props.filters.to);
const activeRange = ref<string>('');

function applyFilters() {
    router.get('/admin/reports', { from: filterFrom.value, to: filterTo.value }, { preserveState: true, replace: true });
}

function setRange(range: string) {
    activeRange.value = range;
    const today = new Date();
    const fmt = (d: Date) => d.toISOString().split('T')[0];
    if (range === 'today') {
        filterFrom.value = fmt(today);
        filterTo.value   = fmt(today);
    } else if (range === 'week') {
        const mon = new Date(today); mon.setDate(today.getDate() - today.getDay() + 1);
        filterFrom.value = fmt(mon);
        filterTo.value   = fmt(today);
    } else if (range === 'month') {
        filterFrom.value = fmt(new Date(today.getFullYear(), today.getMonth(), 1));
        filterTo.value   = fmt(today);
    } else if (range === 'last_month') {
        filterFrom.value = fmt(new Date(today.getFullYear(), today.getMonth() - 1, 1));
        filterTo.value   = fmt(new Date(today.getFullYear(), today.getMonth(), 0));
    }
    applyFilters();
}

// ── Derivados ──
const cancellationRate = computed(() => {
    const total = props.summary.total_reservations + props.summary.cancelled_reservations;
    return total > 0 ? Math.round((props.summary.cancelled_reservations / total) * 100) : 0;
});

const maxDailyIncome = computed(() => Math.max(...props.daily_income.map(d => d.income), 1));
const maxWeekday     = computed(() => Math.max(...props.by_weekday.map(d => d.count), 1));
const maxHourly      = computed(() => Math.max(...props.hourly_distribution.map(d => d.count), 1));
const maxTopClient   = computed(() => Math.max(...props.top_clients.map(c => c.total_reservations), 1));

const totalDailyIncome = computed(() =>
    props.daily_income.reduce((s, d) => s + d.income, 0)
);

// Limpiar la distribución horaria — solo mostrar las horas con valores y rango con datos
const activeHours = computed(() => {
    const items = props.hourly_distribution;
    const firstWithData = items.findIndex(h => h.count > 0);
    const lastWithData  = items.length - 1 - [...items].reverse().findIndex(h => h.count > 0);
    if (firstWithData === -1) return items.slice(8, 23); // default 8a-10p
    const from = Math.max(0, firstWithData - 1);
    const to   = Math.min(items.length - 1, lastWithData + 1);
    return items.slice(from, to + 1);
});

// ── Path SVG del histograma ──
const chartWidth = 800;
const chartHeight = 220;
const chartPadding = { top: 16, right: 16, bottom: 24, left: 44 };
const innerW = chartWidth - chartPadding.left - chartPadding.right;
const innerH = chartHeight - chartPadding.top - chartPadding.bottom;

const barWidth = computed(() => {
    const n = props.daily_income.length;
    if (n === 0) return 0;
    return Math.max(4, innerW / n - 4);
});

function barX(i: number): number {
    const n = props.daily_income.length;
    const slot = n > 0 ? innerW / n : 0;
    return chartPadding.left + i * slot + (slot - barWidth.value) / 2;
}

function barY(value: number): number {
    return chartPadding.top + innerH - (value / maxDailyIncome.value) * innerH;
}

function barH(value: number): number {
    return Math.max(2, (value / maxDailyIncome.value) * innerH);
}

// Líneas de grilla Y (4 líneas)
const gridLines = computed(() => {
    const lines = [];
    for (let i = 0; i <= 4; i++) {
        const value = (maxDailyIncome.value / 4) * (4 - i);
        const y = chartPadding.top + (innerH / 4) * i;
        lines.push({ y, value });
    }
    return lines;
});

// ── Hover tooltip para histograma ──
const hovered = ref<{ x: number; y: number; date: string; income: number; count: number } | null>(null);

function showTooltip(i: number, d: DailyItem) {
    hovered.value = {
        x: barX(i) + barWidth.value / 2,
        y: barY(d.income),
        date: d.date,
        income: d.income,
        count: d.count,
    };
}
function hideTooltip() { hovered.value = null; }

// ── Tablas ──
const statusLabel: Record<string, string> = {
    pending: 'Pendiente', confirmed: 'Confirmada', cancelled: 'Cancelada', completed: 'Completada',
};
const statusColor: Record<string, string> = {
    pending:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    completed: 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
};

const paymentColorBar: Record<string, string> = {
    paid:            'from-emerald-400 to-emerald-600',
    payment_review:  'from-blue-400 to-blue-600',
    pending_payment: 'from-amber-400 to-amber-600',
    unpaid:          'from-orange-400 to-orange-600',
    rejected:        'from-red-400 to-red-600',
};

const weekdayColors = [
    'from-rose-400 to-rose-600',
    'from-orange-400 to-orange-600',
    'from-amber-400 to-amber-600',
    'from-lime-400 to-lime-600',
    'from-emerald-400 to-emerald-600',
    'from-sky-400 to-sky-600',
    'from-violet-400 to-violet-600',
];

function fmtMoney(n: number): string {
    if (n >= 1_000_000) return '$' + (n / 1_000_000).toFixed(1) + 'M';
    if (n >= 1_000) return '$' + (n / 1_000).toFixed(0) + 'K';
    return '$' + n.toLocaleString();
}
</script>

<template>
    <Head title="Reportes" />
    <AppAdminLayout>
        <div class="space-y-5 p-4 lg:p-6">
            <!-- ═══════ HEADER ═══════ -->
            <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <div class="mb-1 flex items-center gap-2">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-gradient-to-br from-indigo-500 to-purple-600 text-white shadow-lg shadow-indigo-500/30">
                            <BarChart3 class="h-5 w-5" />
                        </span>
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">Dashboard de Reportes</h1>
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Análisis de reservas, ingresos y comportamiento del negocio
                    </p>
                </div>
                <div class="text-right">
                    <p class="text-xs text-muted-foreground">Rango actual</p>
                    <p class="text-sm font-semibold text-foreground">{{ filters.from }} — {{ filters.to }}</p>
                </div>
            </div>

            <!-- ═══════ FILTROS ═══════ -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button v-for="r in [
                            { id: 'today', label: 'Hoy' },
                            { id: 'week', label: 'Esta semana' },
                            { id: 'month', label: 'Este mes' },
                            { id: 'last_month', label: 'Mes anterior' }
                        ]" :key="r.id"
                        @click="setRange(r.id)"
                        :class="[
                            'rounded-xl px-3 py-1.5 text-xs font-semibold transition shadow-sm',
                            activeRange === r.id
                                ? 'bg-gradient-to-r from-indigo-500 to-purple-600 text-white shadow-indigo-500/30'
                                : 'border border-border bg-background text-foreground hover:border-primary hover:bg-muted'
                        ]">
                        {{ r.label }}
                    </button>
                </div>
                <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Desde</label>
                        <input v-model="filterFrom" type="date"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <div class="flex-1">
                        <label class="mb-1 block text-xs font-medium text-muted-foreground">Hasta</label>
                        <input v-model="filterTo" type="date"
                            class="w-full rounded-xl border border-border bg-background px-3 py-2 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <button @click="applyFilters"
                        class="flex items-center gap-1.5 rounded-xl bg-gradient-to-r from-indigo-500 to-purple-600 px-5 py-2 text-sm font-semibold text-white shadow-md shadow-indigo-500/30 transition hover:shadow-lg hover:shadow-indigo-500/40 active:scale-95">
                        <BarChart3 class="h-4 w-4" /> Aplicar
                    </button>
                </div>
            </div>

            <!-- ═══════ KPI HERO + CARDS ═══════ -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
                <!-- KPI Principal: Ingresos totales -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-emerald-500 via-emerald-600 to-teal-600 p-5 text-white shadow-xl shadow-emerald-500/30">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="absolute -bottom-6 -left-6 h-24 w-24 rounded-full bg-white/10 blur-xl"></div>
                    <div class="relative">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                                    <DollarSign class="h-5 w-5" />
                                </div>
                                <span class="text-sm font-medium opacity-90">Ingresos totales</span>
                            </div>
                            <span v-if="comparison.income_change !== null"
                                :class="[
                                    'flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-bold backdrop-blur-sm',
                                    comparison.income_change >= 0 ? 'bg-white/25' : 'bg-black/20'
                                ]">
                                <ArrowUpRight v-if="comparison.income_change >= 0" class="h-3 w-3" />
                                <ArrowDownRight v-else class="h-3 w-3" />
                                {{ Math.abs(comparison.income_change) }}%
                            </span>
                        </div>
                        <p class="mb-1 text-3xl font-bold tracking-tight lg:text-4xl">
                            ${{ summary.total_income.toLocaleString() }}
                        </p>
                        <p class="text-xs opacity-80">
                            vs ${{ comparison.prev_income.toLocaleString() }} en período anterior
                        </p>
                    </div>
                </div>

                <!-- KPI: Reservas -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-indigo-500 via-indigo-600 to-purple-600 p-5 text-white shadow-xl shadow-indigo-500/30">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                                    <CalendarDays class="h-5 w-5" />
                                </div>
                                <span class="text-sm font-medium opacity-90">Reservas confirmadas</span>
                            </div>
                            <span v-if="comparison.reservations_change !== null"
                                :class="[
                                    'flex items-center gap-0.5 rounded-full px-2 py-0.5 text-xs font-bold backdrop-blur-sm',
                                    comparison.reservations_change >= 0 ? 'bg-white/25' : 'bg-black/20'
                                ]">
                                <ArrowUpRight v-if="comparison.reservations_change >= 0" class="h-3 w-3" />
                                <ArrowDownRight v-else class="h-3 w-3" />
                                {{ Math.abs(comparison.reservations_change) }}%
                            </span>
                        </div>
                        <p class="mb-1 text-3xl font-bold tracking-tight lg:text-4xl">
                            {{ summary.total_reservations }}
                        </p>
                        <p class="text-xs opacity-80">
                            {{ summary.cancelled_reservations }} canceladas · {{ cancellationRate }}% tasa cancelación
                        </p>
                    </div>
                </div>

                <!-- KPI: Cancha más reservada -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-amber-500 via-orange-500 to-pink-500 p-5 text-white shadow-xl shadow-amber-500/30">
                    <div class="absolute -right-6 -top-6 h-32 w-32 rounded-full bg-white/10 blur-2xl"></div>
                    <div class="relative">
                        <div class="mb-3 flex items-center gap-2">
                            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                                <Goal class="h-5 w-5" />
                            </div>
                            <span class="text-sm font-medium opacity-90">Cancha más reservada</span>
                        </div>
                        <p v-if="most_reserved_court" class="mb-1 text-2xl font-bold tracking-tight lg:text-3xl">
                            {{ most_reserved_court.name }}
                        </p>
                        <p v-else class="mb-1 text-2xl font-bold tracking-tight lg:text-3xl opacity-80">
                            Sin datos
                        </p>
                        <p class="text-xs opacity-80">
                            <template v-if="most_reserved_court">
                                {{ most_reserved_court.count }} reserva{{ most_reserved_court.count === 1 ? '' : 's' }}<template v-if="most_reserved_court.type"> · {{ most_reserved_court.type }}</template>
                            </template>
                            <template v-else>
                                Aún no hay reservas en el período
                            </template>
                        </p>
                    </div>
                </div>
            </div>

            <!-- ═══════ Mini KPIs ═══════ -->
            <div class="grid grid-cols-2 gap-3 lg:grid-cols-3">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm transition hover:shadow-md">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Ingresos cancha</span>
                        <Goal class="h-4 w-4 text-sky-500" />
                    </div>
                    <p class="text-xl font-bold text-foreground">${{ summary.court_income.toLocaleString() }}</p>
                    <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-muted">
                        <div class="h-full rounded-full bg-gradient-to-r from-sky-400 to-sky-600" style="width: 100%"></div>
                    </div>
                    <p class="mt-1 text-[10px] text-muted-foreground">Por horarios reservados</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm transition hover:shadow-md">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Pendientes pago</span>
                        <Clock class="h-4 w-4 text-orange-500" />
                    </div>
                    <p class="text-xl font-bold text-foreground">{{ summary.pending_payments }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">Reservas sin pagar</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm transition hover:shadow-md">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Canceladas</span>
                        <Activity class="h-4 w-4 text-rose-500" />
                    </div>
                    <p class="text-xl font-bold text-foreground">{{ summary.cancelled_reservations }}</p>
                    <p class="mt-1 text-[10px] text-muted-foreground">{{ cancellationRate }}% tasa</p>
                </div>
            </div>

            <!-- ═══════ HISTOGRAMA INGRESOS ═══════ -->
            <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <div class="mb-4 flex items-center justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-sm font-bold text-foreground">
                            <TrendingUp class="h-4 w-4 text-indigo-500" />
                            Histograma de ingresos diarios
                        </h2>
                        <p class="text-xs text-muted-foreground">
                            Total acumulado: <span class="font-semibold text-foreground">${{ totalDailyIncome.toLocaleString() }}</span>
                        </p>
                    </div>
                </div>

                <div v-if="daily_income.length === 0" class="py-10 text-center text-sm text-muted-foreground">
                    Sin datos para este período
                </div>

                <div v-else class="relative w-full overflow-x-auto">
                    <svg :viewBox="`0 0 ${chartWidth} ${chartHeight}`" class="w-full" :style="`min-width: ${Math.max(chartWidth, daily_income.length * 30)}px`">
                        <defs>
                            <linearGradient id="barGrad" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#818cf8" />
                                <stop offset="100%" stop-color="#6366f1" />
                            </linearGradient>
                            <linearGradient id="barGradHover" x1="0" x2="0" y1="0" y2="1">
                                <stop offset="0%" stop-color="#a78bfa" />
                                <stop offset="100%" stop-color="#7c3aed" />
                            </linearGradient>
                        </defs>

                        <!-- Grid lines -->
                        <g class="opacity-60">
                            <line v-for="g in gridLines" :key="g.y"
                                :x1="chartPadding.left" :x2="chartWidth - chartPadding.right"
                                :y1="g.y" :y2="g.y"
                                stroke="currentColor" stroke-dasharray="3,3" stroke-width="0.5"
                                class="text-muted-foreground" />
                        </g>

                        <!-- Y axis labels -->
                        <g class="text-[10px]">
                            <text v-for="g in gridLines" :key="`label-${g.y}`"
                                :x="chartPadding.left - 6" :y="g.y + 3"
                                text-anchor="end"
                                fill="currentColor"
                                class="text-muted-foreground">
                                {{ fmtMoney(g.value) }}
                            </text>
                        </g>

                        <!-- Bars -->
                        <g>
                            <rect v-for="(d, i) in daily_income" :key="d.date"
                                :x="barX(i)" :y="barY(d.income)"
                                :width="barWidth" :height="barH(d.income)"
                                rx="3"
                                :fill="hovered && hovered.date === d.date ? 'url(#barGradHover)' : 'url(#barGrad)'"
                                class="cursor-pointer transition-all"
                                @mouseenter="showTooltip(i, d)"
                                @mouseleave="hideTooltip" />
                        </g>

                        <!-- X axis labels -->
                        <g class="text-[9px]">
                            <text v-for="(d, i) in daily_income" :key="`x-${d.date}`"
                                :x="barX(i) + barWidth / 2" :y="chartHeight - 6"
                                text-anchor="middle"
                                fill="currentColor"
                                class="text-muted-foreground"
                                v-show="daily_income.length <= 31 || i % Math.ceil(daily_income.length / 15) === 0">
                                {{ d.date }}
                            </text>
                        </g>

                        <!-- Tooltip -->
                        <g v-if="hovered" style="pointer-events: none;">
                            <line :x1="hovered.x" :x2="hovered.x"
                                :y1="hovered.y" :y2="chartPadding.top + innerH"
                                stroke="#6366f1" stroke-width="1" stroke-dasharray="2,2" />
                            <foreignObject :x="Math.min(Math.max(hovered.x - 70, 0), chartWidth - 140)"
                                :y="Math.max(hovered.y - 60, 0)" width="140" height="56">
                                <div xmlns="http://www.w3.org/1999/xhtml"
                                    class="rounded-lg border border-indigo-200 bg-white p-2 shadow-lg dark:border-indigo-800 dark:bg-zinc-900">
                                    <p class="text-[10px] font-semibold text-indigo-600 dark:text-indigo-400">{{ hovered.date }}</p>
                                    <p class="text-xs font-bold text-foreground">${{ hovered.income.toLocaleString() }}</p>
                                    <p class="text-[10px] text-muted-foreground">{{ hovered.count }} reservas</p>
                                </div>
                            </foreignObject>
                        </g>
                    </svg>
                </div>
            </div>

            <!-- ═══════ Día de semana ═══════ -->
            <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground">
                    <CalendarDays class="h-4 w-4 text-violet-500" />
                    Reservas por día de semana
                </h2>
                <div class="space-y-2.5">
                    <div v-for="(d, i) in by_weekday" :key="d.day" class="flex items-center gap-3">
                        <span class="w-20 shrink-0 text-xs font-medium text-muted-foreground">{{ d.day }}</span>
                        <div class="relative flex-1 overflow-hidden rounded-full bg-muted/60" style="height: 22px;">
                            <div :class="`absolute inset-y-0 left-0 rounded-full bg-gradient-to-r ${weekdayColors[i]} flex items-center justify-end pr-2 shadow-sm transition-all duration-500`"
                                :style="`width: ${d.count > 0 ? Math.max(8, (d.count / maxWeekday) * 100) : 0}%`">
                                <span v-if="d.count > 0" class="text-[10px] font-bold text-white drop-shadow">
                                    {{ d.count }}
                                </span>
                            </div>
                        </div>
                        <span class="w-8 shrink-0 text-right text-xs font-bold text-foreground">{{ d.count }}</span>
                    </div>
                </div>
            </div>

            <!-- ═══════ ROW: HORAS PICO + PAYMENT BREAKDOWN ═══════ -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Horas pico -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground">
                        <Clock class="h-4 w-4 text-orange-500" />
                        Horas pico
                    </h2>
                    <div v-if="activeHours.every(h => h.count === 0)" class="py-6 text-center text-sm text-muted-foreground">
                        Sin reservas en este período
                    </div>
                    <div v-else class="flex items-end gap-1" style="min-height: 110px;">
                        <div v-for="h in activeHours" :key="h.hour"
                            class="group relative flex flex-1 flex-col items-center"
                            :title="`${h.label}: ${h.count} reservas`">
                            <span class="mb-1 text-[9px] font-bold text-foreground opacity-0 transition group-hover:opacity-100">
                                {{ h.count }}
                            </span>
                            <div class="w-full rounded-t-md bg-gradient-to-t from-orange-400 to-amber-300 transition-all hover:from-orange-500 hover:to-amber-400"
                                :style="`height: ${Math.max(4, (h.count / maxHourly) * 90)}px`"></div>
                            <span class="mt-1 text-[9px] text-muted-foreground">{{ h.label }}</span>
                        </div>
                    </div>
                </div>

                <!-- Estado de pagos -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground">
                        <DollarSign class="h-4 w-4 text-emerald-500" />
                        Estado de pagos
                    </h2>
                    <div class="space-y-2.5">
                        <div v-for="p in payment_breakdown" :key="p.key" class="flex items-center gap-3">
                            <span class="w-28 shrink-0 text-xs font-medium text-muted-foreground">{{ p.label }}</span>
                            <div class="relative flex-1 overflow-hidden rounded-full bg-muted/60" style="height: 20px;">
                                <div :class="`absolute inset-y-0 left-0 rounded-full bg-gradient-to-r ${paymentColorBar[p.key]} transition-all duration-500`"
                                    :style="`width: ${summary.total_reservations > 0 ? (p.count / summary.total_reservations) * 100 : 0}%`"></div>
                            </div>
                            <span class="w-8 shrink-0 text-right text-xs font-bold text-foreground">{{ p.count }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══════ TOP PRODUCTS + RECIENTES ═══════ -->
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
                <!-- Top clientes que más reservan -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground">
                        <Users class="h-4 w-4 text-pink-500" />
                        Clientes que más reservan
                    </h2>
                    <div v-if="top_clients.length === 0" class="flex flex-col items-center py-8 text-center">
                        <Users class="mb-2 h-8 w-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">Sin reservas en este período</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="(c, i) in top_clients" :key="c.email || c.name" class="flex items-center gap-3">
                            <span :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold shadow-sm',
                                i === 0 ? 'bg-gradient-to-br from-yellow-400 to-amber-500 text-white' :
                                i === 1 ? 'bg-gradient-to-br from-gray-300 to-gray-400 text-white' :
                                i === 2 ? 'bg-gradient-to-br from-orange-400 to-orange-600 text-white' :
                                'bg-muted text-muted-foreground'
                            ]">
                                {{ c.name.charAt(0).toUpperCase() }}
                            </span>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-baseline justify-between gap-2">
                                    <p class="truncate text-sm font-semibold text-foreground">{{ c.name }}</p>
                                    <p class="shrink-0 text-sm font-bold text-pink-600 dark:text-pink-400">
                                        ${{ c.total_spent.toLocaleString() }}
                                    </p>
                                </div>
                                <div class="mt-1 flex items-center gap-2">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-muted">
                                        <div class="h-full rounded-full bg-gradient-to-r from-pink-400 to-rose-500"
                                            :style="`width: ${(c.total_reservations / maxTopClient) * 100}%`"></div>
                                    </div>
                                    <span class="text-[10px] text-muted-foreground">
                                        {{ c.total_reservations }} reserva{{ c.total_reservations === 1 ? '' : 's' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Últimas reservas -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-bold text-foreground">
                        <Users class="h-4 w-4 text-blue-500" />
                        Últimas reservas
                    </h2>
                    <div v-if="recent_reservations.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                        Sin reservas en este período
                    </div>
                    <div v-else class="max-h-80 space-y-2 overflow-y-auto pr-1">
                        <div v-for="r in recent_reservations" :key="r.id"
                            class="flex items-center justify-between rounded-xl border border-border/60 bg-background/50 px-3 py-2.5 transition hover:border-primary/40 hover:bg-muted/50">
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-foreground">{{ r.user_name }}</p>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <CalendarDays class="h-3 w-3 shrink-0" />
                                    <span>{{ r.date }}</span>
                                    <span class="opacity-50">·</span>
                                    <Clock class="h-3 w-3 shrink-0" />
                                    <span>{{ r.start_time }} – {{ r.end_time }}</span>
                                </div>
                            </div>
                            <div class="ml-3 flex items-center gap-2 shrink-0">
                                <span class="hidden rounded-full px-2 py-0.5 text-xs font-semibold sm:inline" :class="statusColor[r.status]">
                                    {{ statusLabel[r.status] }}
                                </span>
                                <p class="text-sm font-bold text-foreground">${{ r.total_price.toLocaleString() }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppAdminLayout>
</template>
