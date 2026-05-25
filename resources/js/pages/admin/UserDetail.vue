<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { ArrowLeft, CalendarDays, Clock, UserCheck, UserX } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

const toast = useToast();

interface UserInfo {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    active: boolean;
    created_at: string;
}

interface Reservation {
    id: number;
    date: string;
    start_time: string;
    end_time: string;
    status: string;
    payment_status: string;
    total_price: number;
    confirmation_code: string;
}

const props = defineProps<{
    user: UserInfo;
    reservations: Reservation[];
}>();

const statusLabel: Record<string, string> = {
    pending: 'Pendiente', confirmed: 'Confirmada', cancelled: 'Cancelada', completed: 'Completada',
};
const statusColor: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-400',
    confirmed: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
    cancelled: 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
    completed: 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400',
};

function toggleActive() {
    const action = props.user.active ? 'deshabilitar' : 'habilitar';
    if (!confirm(`¿Deseas ${action} a ${props.user.name}?`)) return;
    router.patch(`/admin/users/${props.user.id}/toggle`, {}, {
        onSuccess: () => toast.success(`Usuario ${props.user.active ? 'deshabilitado' : 'habilitado'}.`, '¡Listo!'),
        onError: (e) => toast.error(Object.values(e)[0] as string, 'Error'),
    });
}
</script>

<template>
    <Head :title="`Usuario: ${user.name}`" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Back -->
            <a href="/admin/users" class="mb-5 flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground transition">
                <ArrowLeft class="h-4 w-4" /> Volver a usuarios
            </a>

            <!-- Perfil -->
            <div class="mb-5 rounded-2xl border border-border bg-card p-5 shadow-sm" :class="!user.active ? 'opacity-70' : ''">
                <div class="flex items-start justify-between gap-4">
                    <div class="flex items-center gap-4">
                        <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-full bg-primary/10">
                            <span class="text-2xl font-bold text-primary">{{ user.name.charAt(0).toUpperCase() }}</span>
                        </div>
                        <div>
                            <h1 class="text-xl font-bold text-foreground">{{ user.name }}</h1>
                            <p class="text-sm text-muted-foreground">{{ user.email }}</p>
                            <p v-if="user.phone" class="text-sm text-muted-foreground">{{ user.phone }}</p>
                            <p class="mt-1 text-xs text-muted-foreground">Cliente desde {{ user.created_at }}</p>
                        </div>
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                            :class="user.active
                                ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                            {{ user.active ? 'Activo' : 'Deshabilitado' }}
                        </span>
                        <button @click="toggleActive"
                            class="flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                            :class="user.active
                                ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400'">
                            <component :is="user.active ? UserX : UserCheck" class="h-3.5 w-3.5" />
                            {{ user.active ? 'Deshabilitar' : 'Habilitar' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Historial de reservas -->
            <div>
                <h2 class="mb-3 text-base font-semibold text-foreground">
                    Historial de reservas ({{ reservations.length }})
                </h2>

                <div v-if="reservations.length === 0"
                    class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-12 text-center">
                    <CalendarDays class="mb-2 h-10 w-10 text-muted-foreground/40" />
                    <p class="text-sm text-muted-foreground">Sin reservas registradas</p>
                </div>

                <div v-else class="space-y-3">
                    <div v-for="r in reservations" :key="r.id"
                        class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                        <div class="mb-2 flex items-start justify-between">
                            <div>
                                <p class="font-mono text-xs text-muted-foreground">#{{ r.confirmation_code }}</p>
                                <div class="mt-1 flex items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold" :class="statusColor[r.status]">
                                        {{ statusLabel[r.status] }}
                                    </span>
                                    <span class="rounded-full px-2 py-0.5 text-xs font-semibold"
                                        :class="r.payment_status === 'paid'
                                            ? 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400'
                                            : 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400'">
                                        {{ r.payment_status === 'paid' ? 'Pagado' : 'Sin pagar' }}
                                    </span>
                                </div>
                            </div>
                            <p class="text-base font-bold text-foreground">${{ r.total_price.toLocaleString() }}</p>
                        </div>
                        <div class="space-y-1">
                            <div class="flex items-center gap-2 text-sm text-foreground">
                                <CalendarDays class="h-3.5 w-3.5 text-primary shrink-0" />
                                <span>{{ r.date }}</span>
                            </div>
                            <div class="flex items-center gap-2 text-sm text-foreground">
                                <Clock class="h-3.5 w-3.5 text-primary shrink-0" />
                                <span>{{ r.start_time }} – {{ r.end_time }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </AppAdminLayout>
</template>
