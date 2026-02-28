<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import {
    CalendarDays,
    History,
    Home,
    LogOut,
    Settings,
    ShoppingCart,
    User,
} from 'lucide-vue-next';
import { computed, onMounted, ref } from 'vue';
import AppToast from '@/components/AppToast.vue';
import NotificationBell from '@/components/NotificationBell.vue';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { requestPushPermission, subscribeToPush, usePush } from '@/composables/usePush';
import type { BreadcrumbItem } from '@/types';

defineProps<{ breadcrumbs?: BreadcrumbItem[] }>();

const page = usePage();
const { isCurrentUrl } = useCurrentUrl();

const appName  = computed(() => (page.props as any).app_name || 'Cancha');
const user     = computed(() => (page.props as any).auth?.user);
const initials = computed(() => user.value?.name?.charAt(0)?.toUpperCase() ?? '?');

const showUserMenu = ref(false);
const { isSupported, permission } = usePush();

function logout() {
    router.post('/logout');
}

onMounted(() => {
    // Suscribir si ya tiene permiso concedido
    subscribeToPush();
});

const navItems = [
    { title: 'Inicio',      href: '/dashboard',         icon: Home },
    { title: 'Calendario',  href: '/calendar',           icon: CalendarDays },
    { title: 'Carrito',     href: '/cart',               icon: ShoppingCart },
    { title: 'Reservas',    href: '/reservations',       icon: History },
    { title: 'Perfil',      href: '/settings/profile',   icon: User },
];

const currentTitle = computed(() =>
    navItems.find((item) => isCurrentUrl(item.href))?.title ?? appName.value
);
</script>

<template>
    <div class="flex min-h-screen bg-background">

        <!-- ── Sidebar desktop ── -->
        <aside class="hidden w-64 flex-col border-r border-border bg-sidebar lg:flex">
            <!-- Logo -->
            <div class="flex h-16 items-center border-b border-border px-5">
                <Link href="/dashboard" class="flex items-center gap-3 min-w-0 flex-1">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-primary">
                        <span class="text-sm font-black text-primary-foreground">{{ appName.charAt(0) }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-[10px] font-semibold uppercase tracking-wider text-muted-foreground">Cancha Sintética</p>
                        <p class="truncate text-sm font-bold text-foreground leading-tight">{{ appName }}</p>
                    </div>
                </Link>
            </div>

            <!-- Banner notificaciones push -->
            <div v-if="isSupported && permission === 'default'"
                class="mx-3 mt-3 flex items-center gap-2 rounded-xl bg-amber-50 px-3 py-2 dark:bg-amber-950">
                <span class="text-lg">🔔</span>
                <div class="min-w-0 flex-1">
                    <p class="text-xs font-semibold text-amber-800 dark:text-amber-200">Activa las notificaciones</p>
                    <p class="text-[10px] text-amber-700 dark:text-amber-300">Recibe alertas de tus reservas</p>
                </div>
                <button @click="requestPushPermission"
                    class="shrink-0 rounded-lg bg-amber-500 px-2 py-1 text-[10px] font-bold text-white hover:bg-amber-600">
                    Activar
                </button>
            </div>

            <!-- Nav -->
            <nav class="flex-1 space-y-0.5 px-3 py-4">
                <Link
                    v-for="item in navItems" :key="item.href" :href="item.href"
                    class="flex items-center gap-3 rounded-xl px-3 py-2.5 text-sm font-medium transition-all"
                    :class="isCurrentUrl(item.href)
                        ? 'bg-primary text-primary-foreground shadow-sm'
                        : 'text-sidebar-foreground hover:bg-sidebar-accent hover:text-sidebar-accent-foreground'"
                >
                    <component :is="item.icon" class="h-5 w-5 shrink-0" />
                    <span>{{ item.title }}</span>
                </Link>
            </nav>

            <!-- User footer desktop -->
            <div class="border-t border-border p-4 space-y-1">
                <Link href="/settings/profile" class="flex items-center gap-3 rounded-xl p-2 hover:bg-sidebar-accent transition">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary/15">
                        <span class="text-sm font-bold text-primary">{{ initials }}</span>
                    </div>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-sm font-semibold text-foreground">{{ user?.name }}</p>
                        <p class="truncate text-xs text-muted-foreground">{{ user?.email }}</p>
                    </div>
                </Link>
                <button @click="logout"
                    class="flex w-full items-center gap-3 rounded-xl px-3 py-2 text-sm font-medium text-muted-foreground hover:bg-sidebar-accent hover:text-foreground transition">
                    <LogOut class="h-4 w-4 shrink-0" />
                    Cerrar sesión
                </button>
            </div>
        </aside>

        <!-- ── Main content ── -->
        <div class="flex flex-1 flex-col">

            <!-- Header móvil estilo app nativa -->
            <header class="relative flex h-14 items-center justify-between border-b border-border bg-background px-4 lg:hidden">
                <!-- Avatar usuario (izquierda) — abre menú -->
                <button @click="showUserMenu = true"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-primary/15 active:scale-95 transition">
                    <span class="text-sm font-bold text-primary">{{ initials }}</span>
                </button>

                <!-- Nombre de la sección (centro) -->
                <span class="absolute left-1/2 -translate-x-1/2 text-base font-bold text-foreground whitespace-nowrap">
                    {{ currentTitle }}
                </span>

                <!-- Campana + Carrito (derecha) -->
                <div class="flex items-center gap-1">
                    <NotificationBell :user-id="user?.id" />
                    <Link href="/cart" class="flex h-8 w-8 items-center justify-center rounded-xl text-muted-foreground hover:text-foreground transition">
                        <ShoppingCart class="h-5 w-5" />
                    </Link>
                </div>
            </header>

            <!-- Header desktop (solo visible en lg+) -->
            <header class="hidden h-16 items-center justify-between border-b border-border bg-background px-6 lg:flex">
                <h1 class="text-base font-semibold text-foreground">{{ currentTitle }}</h1>
                <NotificationBell :user-id="user?.id" />
            </header>

            <!-- Page content -->
            <main class="flex-1 pb-24 lg:pb-0">
                <slot />
            </main>

            <AppToast />

            <!-- Menú de usuario móvil (bottom sheet) -->
            <Teleport to="body">
                <div v-if="showUserMenu"
                    class="fixed inset-0 z-50 flex items-end justify-center bg-black/50 backdrop-blur-sm"
                    @click.self="showUserMenu = false">
                    <div class="w-full rounded-t-3xl bg-background p-6 shadow-2xl">
                        <!-- Info usuario -->
                        <div class="mb-5 flex items-center gap-3">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary/15">
                                <span class="text-lg font-bold text-primary">{{ initials }}</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-semibold text-foreground truncate">{{ user?.name }}</p>
                                <p class="text-sm text-muted-foreground truncate">{{ user?.email }}</p>
                            </div>
                        </div>
                        <!-- Opciones -->
                        <div class="space-y-1">
                            <Link href="/settings/profile" @click="showUserMenu = false"
                                class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm font-medium text-foreground hover:bg-muted transition">
                                <Settings class="h-4 w-4 text-muted-foreground" /> Configuración
                            </Link>
                            <button @click="logout"
                                class="flex w-full items-center gap-3 rounded-xl px-4 py-3 text-sm font-semibold text-red-600 hover:bg-red-50 dark:hover:bg-red-900/20 transition">
                                <LogOut class="h-4 w-4" /> Cerrar sesión
                            </button>
                        </div>
                    </div>
                </div>
            </Teleport>

            <!-- Bottom nav móvil -->
            <nav
                class="fixed bottom-0 left-0 right-0 z-50 border-t border-border bg-background/95 backdrop-blur-md lg:hidden"
                style="padding-bottom: calc(env(safe-area-inset-bottom) + 12px)"
            >
                <div class="flex items-center justify-around px-1 py-2">
                    <Link
                        v-for="item in navItems" :key="item.href" :href="item.href"
                        class="flex flex-col items-center gap-0.5 px-3 py-2 rounded-2xl transition-all duration-150 active:scale-75 active:opacity-60"
                        :class="isCurrentUrl(item.href) ? 'text-primary' : 'text-muted-foreground'"
                    >
                        <component :is="item.icon" class="h-5 w-5 transition-transform duration-150"
                            :class="isCurrentUrl(item.href) ? 'stroke-[2.5px]' : 'stroke-[1.5px]'" />
                        <span class="text-[10px] font-medium leading-none"
                            :class="isCurrentUrl(item.href) ? 'font-semibold' : ''">
                            {{ item.title }}
                        </span>
                    </Link>
                </div>
            </nav>
        </div>
    </div>
</template>
