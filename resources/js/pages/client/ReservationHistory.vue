<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { CalendarDays, ChevronRight, Clock, Goal } from 'lucide-vue-next';
import AppClientLayout from '@/layouts/AppClientLayout.vue';

interface Reservation {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    duration_hours: number;
    status: string;
    payment_status: string;
    total_price: number;
    confirmation_code: string;
    court_name: string | null;
}

defineProps<{ reservations: Reservation[] }>();

const statusLabel: Record<string, string> = {
    pending: 'Pendiente',
    confirmed: 'Confirmada',
    cancelled: 'Cancelada',
    completed: 'Completada',
};

const statusColor: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700',
    confirmed: 'bg-emerald-100 text-emerald-700',
    cancelled: 'bg-red-100 text-red-700',
    completed: 'bg-gray-100 text-gray-600',
};
</script>

<template>
    <Head title="Historial de Reservas" />

    <AppClientLayout>
        <div class="p-4 lg:p-6">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-foreground">Historial</h1>
                <p class="text-sm text-muted-foreground">Reservas pasadas y canceladas</p>
            </div>

            <div
                v-if="reservations.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center"
            >
                <CalendarDays class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">Sin historial aún</p>
                <p class="text-sm text-muted-foreground">Tus reservas pasadas aparecerán aquí.</p>
            </div>

            <div v-else class="space-y-3">
                <div
                    v-for="r in reservations"
                    :key="r.id"
                    @click="router.visit(`/reservations/${r.id}`)"
                    class="cursor-pointer rounded-2xl border border-border bg-card p-4 shadow-sm opacity-80 transition hover:border-primary/40 hover:opacity-100 hover:shadow-md"
                >
                    <div class="mb-3 flex items-start justify-between">
                        <div>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="statusColor[r.status]"
                            >
                                {{ statusLabel[r.status] }}
                            </span>
                            <p class="mt-1 text-xs text-muted-foreground font-mono">#{{ r.confirmation_code }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <p class="text-base font-bold text-foreground">
                                ${{ r.total_price.toLocaleString() }}
                            </p>
                            <ChevronRight class="h-4 w-4 text-muted-foreground" />
                        </div>
                    </div>
                    <div class="space-y-1.5">
                        <div v-if="r.court_name" class="flex items-center gap-2 text-sm text-foreground">
                            <Goal class="h-4 w-4 text-muted-foreground shrink-0" />
                            <span class="font-medium">{{ r.court_name }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-foreground">
                            <CalendarDays class="h-4 w-4 text-muted-foreground shrink-0" />
                            <span>{{ r.date }}</span>
                        </div>
                        <div class="flex items-center gap-2 text-sm text-foreground">
                            <Clock class="h-4 w-4 text-muted-foreground shrink-0" />
                            <span>{{ r.start_time }} – {{ r.end_time }} ({{ r.duration_hours }}h)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppClientLayout>
</template>
