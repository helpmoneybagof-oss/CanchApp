<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { BarChart3, CalendarDays, Clock, DollarSign, ShoppingBag, TrendingUp, X } from 'lucide-vue-next';
import { ref } from 'vue';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

interface Summary {
    total_reservations: number;
    total_income: number;
    court_income: number;
    consumables_income: number;
    pending_payments: number;
    cancelled_reservations: number;
}

interface DailyItem { date: string; income: number; count: number; }
interface WeekdayItem { day: string; count: number; }
interface TopProduct { name: string; total_qty: number; total_revenue: number; }
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
    daily_income: DailyItem[];
    by_weekday: WeekdayItem[];
    top_products: TopProduct[];
    recent_reservations: RecentReservation[];
}>();

const filterFrom = ref(props.filters.from);
const filterTo   = ref(props.filters.to);

function applyFilters() {
    router.get('/admin/reports', { from: filterFrom.value, to: filterTo.value }, { preserveState: true, replace: true });
}

function setRange(range: string) {
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

const maxDailyIncome = Math.max(...props.daily_income.map(d => d.income), 1);
const maxWeekday     = Math.max(...props.by_weekday.map(d => d.count), 1);

const statusLabel: Record<string, string> = {
    pending: 'Pendiente', confirmed: 'Confirmada', cancelled: 'Cancelada', completed: 'Completada',
};
const statusColor: Record<string, string> = {
    pending:   'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    completed: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
};
</script>

<template>
    <Head title="Reportes" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Header -->
            <div class="mb-5">
                <h1 class="text-xl font-bold text-foreground">Reportes</h1>
                <p class="text-sm text-muted-foreground">Análisis de reservas e ingresos</p>
            </div>

            <!-- Filtros de período -->
            <div class="mb-5 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button @click="setRange('today')"
                        class="rounded-xl border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted hover:border-primary transition">
                        Hoy
                    </button>
                    <button @click="setRange('week')"
                        class="rounded-xl border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted hover:border-primary transition">
                        Esta semana
                    </button>
                    <button @click="setRange('month')"
                        class="rounded-xl border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted hover:border-primary transition">
                        Este mes
                    </button>
                    <button @click="setRange('last_month')"
                        class="rounded-xl border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-muted hover:border-primary transition">
                        Mes anterior
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
                        class="flex items-center gap-1.5 rounded-xl bg-primary px-5 py-2 text-sm font-semibold text-primary-foreground hover:bg-primary/90 transition">
                        <BarChart3 class="h-4 w-4" /> Ver reporte
                    </button>
                </div>
            </div>

            <!-- Tarjetas de resumen -->
            <div class="mb-5 grid grid-cols-2 gap-3 lg:grid-cols-3">
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Reservas</span>
                        <CalendarDays class="h-4 w-4 text-primary" />
                    </div>
                    <p class="text-2xl font-bold text-foreground">{{ summary.total_reservations }}</p>
                    <p class="text-xs text-muted-foreground">{{ summary.cancelled_reservations }} canceladas</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Ingresos totales</span>
                        <DollarSign class="h-4 w-4 text-emerald-500" />
                    </div>
                    <p class="text-2xl font-bold text-foreground">${{ summary.total_income.toLocaleString() }}</p>
                    <p class="text-xs text-muted-foreground">{{ summary.pending_payments }} pendientes de pago</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Cancha</span>
                        <TrendingUp class="h-4 w-4 text-blue-500" />
                    </div>
                    <p class="text-2xl font-bold text-foreground">${{ summary.court_income.toLocaleString() }}</p>
                    <p class="text-xs text-muted-foreground">Ingresos por horarios</p>
                </div>
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Consumibles</span>
                        <ShoppingBag class="h-4 w-4 text-amber-500" />
                    </div>
                    <p class="text-2xl font-bold text-foreground">${{ summary.consumables_income.toLocaleString() }}</p>
                    <p class="text-xs text-muted-foreground">Ingresos por productos</p>
                </div>
                <div class="col-span-2 rounded-2xl border border-border bg-card p-4 shadow-sm lg:col-span-2">
                    <div class="mb-1 flex items-center justify-between">
                        <span class="text-xs text-muted-foreground">Promedio por reserva</span>
                        <BarChart3 class="h-4 w-4 text-purple-500" />
                    </div>
                    <p class="text-2xl font-bold text-foreground">
                        ${{ summary.total_reservations > 0 ? Math.round(summary.total_income / summary.total_reservations).toLocaleString() : 0 }}
                    </p>
                    <p class="text-xs text-muted-foreground">Por reserva confirmada</p>
                </div>
            </div>

            <!-- Gráfica de ingresos por día (barras CSS) -->
            <div v-if="daily_income.length > 0" class="mb-5 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-foreground">Ingresos por día</h2>
                <div class="flex items-end gap-1 overflow-x-auto pb-2" style="min-height: 120px;">
                    <div v-for="d in daily_income" :key="d.date"
                        class="flex flex-col items-center gap-1 shrink-0"
                        :style="`min-width: ${Math.max(24, Math.floor(640 / daily_income.length))}px`">
                        <span class="text-[9px] text-muted-foreground font-medium">
                            {{ d.income > 0 ? '$' + (d.income / 1000).toFixed(0) + 'K' : '' }}
                        </span>
                        <div class="w-full rounded-t-md bg-primary/80 transition-all"
                            :style="`height: ${Math.max(4, Math.round((d.income / maxDailyIncome) * 90))}px`"
                            :title="`${d.date}: $${d.income.toLocaleString()} (${d.count} reservas)`">
                        </div>
                        <span class="text-[9px] text-muted-foreground leading-tight text-center">{{ d.date }}</span>
                    </div>
                </div>
            </div>

            <!-- Por día de semana + Top productos (2 cols) -->
            <div class="mb-5 grid grid-cols-1 gap-5 lg:grid-cols-2">
                <!-- Reservas por día de semana -->
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Reservas por día de semana</h2>
                    <div class="space-y-2">
                        <div v-for="d in by_weekday" :key="d.day" class="flex items-center gap-3">
                            <span class="w-20 shrink-0 text-xs text-muted-foreground">{{ d.day }}</span>
                            <div class="flex-1 rounded-full bg-muted overflow-hidden h-5">
                                <div class="h-full rounded-full bg-primary/70 transition-all flex items-center justify-end pr-2"
                                    :style="`width: ${Math.max(d.count > 0 ? 8 : 0, Math.round((d.count / maxWeekday) * 100))}%`">
                                    <span v-if="d.count > 0" class="text-[10px] font-bold text-white">{{ d.count }}</span>
                                </div>
                            </div>
                            <span class="w-6 shrink-0 text-right text-xs font-semibold text-foreground">{{ d.count }}</span>
                        </div>
                    </div>
                </div>

                <!-- Top consumibles -->
                <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Top consumibles vendidos</h2>
                    <div v-if="top_products.length === 0" class="flex flex-col items-center py-8 text-center">
                        <ShoppingBag class="mb-2 h-8 w-8 text-muted-foreground/40" />
                        <p class="text-sm text-muted-foreground">Sin ventas en este período</p>
                    </div>
                    <div v-else class="space-y-3">
                        <div v-for="(p, i) in top_products" :key="p.name" class="flex items-center gap-3">
                            <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-primary/10 text-xs font-bold text-primary">
                                {{ i + 1 }}
                            </span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-foreground truncate">{{ p.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ p.total_qty }} unidades</p>
                            </div>
                            <p class="text-sm font-bold text-primary shrink-0">${{ p.total_revenue.toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Últimas reservas -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-4 text-sm font-semibold text-foreground">Últimas reservas del período</h2>
                <div v-if="recent_reservations.length === 0" class="py-6 text-center text-sm text-muted-foreground">
                    Sin reservas en este período
                </div>
                <div v-else class="space-y-2">
                    <div v-for="r in recent_reservations" :key="r.id"
                        class="flex items-center justify-between rounded-xl border border-border px-3 py-2.5">
                        <div class="flex items-center gap-3 min-w-0">
                            <div class="min-w-0">
                                <p class="text-sm font-medium text-foreground truncate">{{ r.user_name }}</p>
                                <div class="flex items-center gap-1.5 text-xs text-muted-foreground">
                                    <CalendarDays class="h-3 w-3 shrink-0" />
                                    <span>{{ r.date }}</span>
                                    <Clock class="h-3 w-3 shrink-0 ml-1" />
                                    <span>{{ r.start_time }} – {{ r.end_time }}</span>
                                </div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0 ml-3">
                            <span class="hidden sm:inline rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusColor[r.status]">
                                {{ statusLabel[r.status] }}
                            </span>
                            <p class="text-sm font-bold text-foreground">${{ r.total_price.toLocaleString() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppAdminLayout>
</template>
