<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { Search, User, UserCheck, UserX, X, Eye } from 'lucide-vue-next';
import { ref, computed } from 'vue';
import { useToast } from '@/composables/useToast';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

const toast = useToast();

interface UserItem {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    active: boolean;
    created_at: string;
    total_reservations: number;
    active_reservations: number;
}

const props = defineProps<{
    users: { data: UserItem[]; links: any[] };
    filters: { search?: string; active?: string };
}>();

const filterSearch = ref(props.filters.search ?? '');
const filterActive = ref(props.filters.active ?? '');

function applyFilters() {
    router.get('/admin/users', {
        search: filterSearch.value || undefined,
        active: filterActive.value !== '' ? filterActive.value : undefined,
    }, { preserveState: true, replace: true });
}

function clearFilters() {
    filterSearch.value = '';
    filterActive.value = '';
    router.get('/admin/users', {}, { preserveState: true, replace: true });
}

const hasFilters = computed(() => !!filterSearch.value || filterActive.value !== '');

function toggleActive(user: UserItem) {
    const action = user.active ? 'deshabilitar' : 'habilitar';
    if (!confirm(`¿Deseas ${action} a ${user.name}?`)) return;
    router.patch(`/admin/users/${user.id}/toggle`, {}, {
        onSuccess: () => toast.success(`Usuario ${user.active ? 'deshabilitado' : 'habilitado'}.`, '¡Listo!'),
        onError: (e) => toast.error(Object.values(e)[0] as string, 'Error'),
    });
}
</script>

<template>
    <Head title="Usuarios" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <!-- Header -->
            <div class="mb-5">
                <h1 class="text-xl font-bold text-foreground">Usuarios</h1>
                <p class="text-sm text-muted-foreground">Gestión de clientes registrados</p>
            </div>

            <!-- Filtros -->
            <div class="mb-5 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <div class="relative">
                        <Search class="absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                        <input v-model="filterSearch" type="text" placeholder="Buscar por nombre, email o teléfono..."
                            @keyup.enter="applyFilters"
                            class="w-full rounded-xl border border-border bg-background py-2.5 pl-9 pr-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                    </div>
                    <select v-model="filterActive"
                        class="rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="">Todos</option>
                        <option value="1">Activos</option>
                        <option value="0">Deshabilitados</option>
                    </select>
                </div>
                <div class="mt-3 flex gap-2">
                    <button @click="applyFilters"
                        class="flex items-center gap-1.5 rounded-xl bg-primary px-4 py-2 text-xs font-semibold text-primary-foreground hover:bg-primary/90 transition">
                        <Search class="h-3.5 w-3.5" /> Buscar
                    </button>
                    <button v-if="hasFilters" @click="clearFilters"
                        class="flex items-center gap-1.5 rounded-xl border border-border px-4 py-2 text-xs font-medium text-foreground hover:bg-muted transition">
                        <X class="h-3.5 w-3.5" /> Limpiar
                    </button>
                </div>
            </div>

            <!-- Vacío -->
            <div v-if="users.data.length === 0"
                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border bg-card py-16 text-center">
                <User class="mb-3 h-12 w-12 text-muted-foreground/40" />
                <p class="font-semibold text-foreground">No hay usuarios</p>
                <p v-if="hasFilters" class="text-sm text-muted-foreground">Intenta con otros filtros</p>
            </div>

            <!-- Lista -->
            <div v-else class="space-y-3">
                <div v-for="u in users.data" :key="u.id"
                    class="rounded-2xl border border-border bg-card p-4 shadow-sm"
                    :class="!u.active ? 'opacity-60' : ''">
                    <div class="flex items-start justify-between gap-3">
                        <!-- Avatar + info -->
                        <div class="flex items-center gap-3">
                            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary/10">
                                <span class="text-base font-bold text-primary">{{ u.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div>
                                <p class="font-semibold text-foreground">{{ u.name }}</p>
                                <p class="text-xs text-muted-foreground">{{ u.email }}</p>
                                <p v-if="u.phone" class="text-xs text-muted-foreground">{{ u.phone }}</p>
                            </div>
                        </div>
                        <!-- Badges -->
                        <div class="flex flex-col items-end gap-1.5">
                            <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold"
                                :class="u.active
                                    ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400'
                                    : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400'">
                                {{ u.active ? 'Activo' : 'Deshabilitado' }}
                            </span>
                            <span class="text-xs text-muted-foreground">Desde {{ u.created_at }}</span>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="mt-3 flex items-center gap-4 border-t border-border pt-3 text-xs text-muted-foreground">
                        <span>{{ u.total_reservations }} reservas totales</span>
                        <span class="text-primary font-medium">{{ u.active_reservations }} activas</span>
                    </div>

                    <!-- Acciones -->
                    <div class="mt-3 flex gap-2">
                        <a :href="`/admin/users/${u.id}`"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-border py-2 text-xs font-medium text-foreground hover:bg-muted transition">
                            <Eye class="h-3.5 w-3.5" /> Ver historial
                        </a>
                        <button @click="toggleActive(u)"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border py-2 text-xs font-semibold transition"
                            :class="u.active
                                ? 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100 dark:border-red-800 dark:bg-red-900/20 dark:text-red-400'
                                : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100 dark:border-emerald-800 dark:bg-emerald-900/20 dark:text-emerald-400'">
                            <component :is="u.active ? UserX : UserCheck" class="h-3.5 w-3.5" />
                            {{ u.active ? 'Deshabilitar' : 'Habilitar' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Paginación -->
            <div v-if="users.links && users.links.length > 3" class="mt-5 flex items-center justify-center gap-1">
                <template v-for="link in users.links" :key="link.label">
                    <button v-if="link.url" @click="router.visit(link.url, { preserveScroll: true })"
                        class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl border border-border px-3 text-sm font-medium transition hover:bg-muted"
                        :class="link.active ? 'bg-primary text-primary-foreground border-primary' : 'bg-card text-foreground'"
                        v-html="link.label" />
                    <span v-else class="flex h-9 min-w-[2.25rem] items-center justify-center rounded-xl px-3 text-sm text-muted-foreground opacity-50" v-html="link.label" />
                </template>
            </div>
        </div>
    </AppAdminLayout>
</template>
