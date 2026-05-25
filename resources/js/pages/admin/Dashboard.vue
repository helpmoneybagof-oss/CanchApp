<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { BarChart3, CalendarCheck, Clock, DollarSign, Goal } from 'lucide-vue-next';
import { useRealtimeAdmin } from '@/composables/useRealtime';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

defineProps<{
    stats: {
        reservations_today: number;
        reservations_pending: number;
        income_month: number;
    };
    upcoming_today: {
        id: number;
        user_name: string;
        start_time: string;
        end_time: string;
        status: string;
        payment_status: string;
        total_price: number;
        confirmation_code: string;
    }[];
}>();

// Tiempo real: recarga stats y lista de reservas de hoy cuando hay cambios
useRealtimeAdmin(['stats', 'upcoming_today']);
</script>

<template>
    <Head title="Dashboard Admin" />

    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Header -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-foreground">Dashboard</h1>
                <p class="text-sm text-muted-foreground">
                    Resumen del día y alertas importantes
                </p>
            </div>

            <!-- Stats grid -->
            <div class="mb-6 grid grid-cols-1 gap-3 sm:grid-cols-3 lg:gap-4">
                <!-- Reservas hoy -->
                <div
                    class="flex flex-col gap-2 rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">
                            Reservas hoy
                        </span>
                        <div class="rounded-xl bg-primary/10 p-2">
                            <CalendarCheck class="h-4 w-4 text-primary" />
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-foreground">
                        {{ stats.reservations_today }}
                    </span>
                </div>

                <!-- Pendientes de pago -->
                <div
                    class="flex flex-col gap-2 rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">
                            Pendientes pago
                        </span>
                        <div class="rounded-xl bg-amber-500/10 p-2">
                            <CalendarCheck class="h-4 w-4 text-amber-500" />
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-foreground">
                        {{ stats.reservations_pending }}
                    </span>
                </div>

                <!-- Ingresos del mes -->
                <div
                    class="flex flex-col gap-2 rounded-2xl border border-border bg-card p-4 shadow-sm"
                >
                    <div class="flex items-center justify-between">
                        <span class="text-xs font-medium text-muted-foreground">
                            Ingresos mes
                        </span>
                        <div class="rounded-xl bg-emerald-500/10 p-2">
                            <DollarSign class="h-4 w-4 text-emerald-500" />
                        </div>
                    </div>
                    <span class="text-2xl font-bold text-foreground">
                        ${{ stats.income_month.toLocaleString() }}
                    </span>
                </div>
            </div>

            <!-- Reservas de hoy -->
            <div class="mb-6 rounded-2xl border border-border bg-card p-4 shadow-sm lg:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-foreground">Reservas de hoy</h2>
                    <a href="/admin/reservations" class="text-xs font-medium text-primary hover:underline">Ver todas →</a>
                </div>

                <div v-if="upcoming_today.length === 0" class="flex flex-col items-center py-8 text-center">
                    <CalendarCheck class="mb-2 h-8 w-8 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">Sin reservas para hoy</p>
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="r in upcoming_today"
                        :key="r.id"
                        class="flex items-center justify-between rounded-xl border border-border px-3 py-2.5"
                    >
                        <div class="flex items-center gap-3">
                            <div class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/10">
                                <Clock class="h-4 w-4 text-primary" />
                            </div>
                            <div>
                                <p class="text-sm font-medium text-foreground">{{ r.user_name }}</p>
                                <p class="text-xs text-muted-foreground">{{ r.start_time }} – {{ r.end_time }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-sm font-bold text-foreground">${{ r.total_price.toLocaleString() }}</p>
                            <span
                                class="text-xs font-semibold"
                                :class="r.payment_status === 'paid' ? 'text-emerald-600' : 'text-amber-600'"
                            >
                                {{ r.payment_status === 'paid' ? 'Pagado' : 'Sin pagar' }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm lg:p-6">
                <h2 class="mb-4 text-base font-semibold text-foreground">
                    Accesos rápidos
                </h2>
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <a
                        href="/admin/reservations"
                        class="flex flex-col items-center gap-2 rounded-xl border border-border p-4 text-center transition-all hover:border-primary hover:bg-primary/5"
                    >
                        <CalendarCheck class="h-6 w-6 text-primary" />
                        <span class="text-xs font-medium text-foreground">Ver reservas</span>
                    </a>
                    <a
                        href="/admin/calendar"
                        class="flex flex-col items-center gap-2 rounded-xl border border-border p-4 text-center transition-all hover:border-primary hover:bg-primary/5"
                    >
                        <BarChart3 class="h-6 w-6 text-primary" />
                        <span class="text-xs font-medium text-foreground">Calendario</span>
                    </a>
                    <a
                        href="/admin/courts"
                        class="flex flex-col items-center gap-2 rounded-xl border border-border p-4 text-center transition-all hover:border-primary hover:bg-primary/5"
                    >
                        <Goal class="h-6 w-6 text-primary" />
                        <span class="text-xs font-medium text-foreground">Canchas</span>
                    </a>
                    <a
                        href="/admin/reports"
                        class="flex flex-col items-center gap-2 rounded-xl border border-border p-4 text-center transition-all hover:border-primary hover:bg-primary/5"
                    >
                        <BarChart3 class="h-6 w-6 text-primary" />
                        <span class="text-xs font-medium text-foreground">Reportes</span>
                    </a>
                </div>
            </div>
        </div>
    </AppAdminLayout>
</template>
