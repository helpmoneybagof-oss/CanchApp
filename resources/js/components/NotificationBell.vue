<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Bell, Check, CheckCheck, Trash2, X } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import axios from 'axios';

interface Notification {
    id: string;
    type: string;
    title: string;
    body: string;
    icon: string;
    url: string | null;
    readAt: string | null;
    createdAt: string;
}

const notifications = ref<Notification[]>([]);
const unreadCount   = ref(0);
const open          = ref(false);
const loading       = ref(false);

const unread = computed(() => notifications.value.filter(n => !n.readAt));

// ── Cargar notificaciones ──────────────────────────────────────────────────────
async function load() {
    loading.value = true;
    try {
        const res = await axios.get('/api/notifications');
        notifications.value = res.data.notifications;
        unreadCount.value   = res.data.unreadCount;
    } catch (e) {
        console.error(e);
    } finally {
        loading.value = false;
    }
}

// ── Marcar una como leída ──────────────────────────────────────────────────────
async function markRead(n: Notification) {
    if (n.readAt) return;
    try {
        const res = await axios.post(`/api/notifications/${n.id}/read`);
        n.readAt      = new Date().toISOString();
        unreadCount.value = res.data.unreadCount;
    } catch (e) {
        console.error(e);
    }
}

// ── Marcar todas como leídas ───────────────────────────────────────────────────
async function markAllRead() {
    try {
        await axios.post('/api/notifications/read-all');
        notifications.value.forEach(n => { if (!n.readAt) n.readAt = new Date().toISOString(); });
        unreadCount.value = 0;
    } catch (e) {
        console.error(e);
    }
}

// ── Eliminar notificación ──────────────────────────────────────────────────────
async function remove(n: Notification) {
    try {
        const res = await axios.delete(`/api/notifications/${n.id}`);
        notifications.value = notifications.value.filter(x => x.id !== n.id);
        unreadCount.value   = res.data.unreadCount;
    } catch (e) {
        console.error(e);
    }
}

// ── Click en notificación → marcar leída y navegar ────────────────────────────
function handleClick(n: Notification) {
    markRead(n);
    open.value = false;
    if (n.url) router.visit(n.url);
}

// ── Tiempo relativo ────────────────────────────────────────────────────────────
function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)   return 'ahora';
    if (diff < 3600) return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
}

// ── Realtime: escuchar canal privado ──────────────────────────────────────────
let realtimeChannel: any = null;

function subscribeRealtime(userId: number) {
    if (!userId || !window.Echo) return;
    realtimeChannel = window.Echo.private(`notifications.${userId}`)
        .listen('.notification.created', (data: any) => {
            // Agregar al inicio de la lista
            notifications.value.unshift({
                id:        data.id,
                type:      data.type,
                title:     data.title,
                body:      data.body,
                icon:      data.icon,
                url:       data.url,
                readAt:    null,
                createdAt: data.createdAt,
            });
            unreadCount.value = data.unreadCount;
        });
}

// ── Cerrar dropdown al hacer clic afuera ──────────────────────────────────────
function handleOutsideClick(e: MouseEvent) {
    const el = document.getElementById('notification-bell');
    if (el && !el.contains(e.target as Node)) open.value = false;
}

function toggleOpen() {
    open.value = !open.value;
}

const props = defineProps<{ userId: number }>();

onMounted(() => {
    load();
    subscribeRealtime(props.userId);
    // Usamos mousedown para cerrar al clicar afuera,
    // así no interfiere con el click del botón campana
    document.addEventListener('mousedown', handleOutsideClick);
});

onUnmounted(() => {
    if (realtimeChannel) window.Echo?.leave(`notifications.${props.userId}`);
    document.removeEventListener('mousedown', handleOutsideClick);
});
</script>

<template>
    <div id="notification-bell" class="relative">
        <!-- Botón campana -->
        <button
            @click="open = !open"
            class="relative flex h-9 w-9 items-center justify-center rounded-xl text-muted-foreground transition hover:bg-muted hover:text-foreground"
            aria-label="Notificaciones"
        >
            <Bell class="h-5 w-5" :class="unreadCount > 0 ? 'text-primary' : ''" />
            <!-- Badge -->
            <span v-if="unreadCount > 0"
                class="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-red-500 text-[9px] font-bold text-white">
                {{ unreadCount > 9 ? '9+' : unreadCount }}
            </span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-1"
        >
            <div v-if="open"
                class="absolute right-0 z-50 mt-2 w-80 origin-top-right overflow-hidden rounded-2xl border border-border bg-background shadow-xl"
                style="max-height: min(480px, 80vh); display: flex; flex-direction: column;"
            >
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-border px-4 py-3">
                    <div class="flex items-center gap-2">
                        <Bell class="h-4 w-4 text-primary" />
                        <h3 class="text-sm font-bold text-foreground">Notificaciones</h3>
                        <span v-if="unreadCount > 0"
                            class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600 dark:bg-red-950 dark:text-red-400">
                            {{ unreadCount }} nueva{{ unreadCount !== 1 ? 's' : '' }}
                        </span>
                    </div>
                    <div class="flex items-center gap-1">
                        <button v-if="unreadCount > 0" @click="markAllRead"
                            class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary transition hover:bg-primary/10"
                            title="Marcar todas como leídas">
                            <CheckCheck class="h-3.5 w-3.5" />
                            Todas leídas
                        </button>
                        <button @click="open = false"
                            class="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-muted">
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>

                <!-- Lista -->
                <div class="overflow-y-auto">
                    <!-- Loading -->
                    <div v-if="loading" class="flex items-center justify-center py-8">
                        <div class="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
                    </div>

                    <!-- Vacío -->
                    <div v-else-if="notifications.length === 0"
                        class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        <Bell class="h-8 w-8 text-muted-foreground/30" />
                        <p class="text-sm text-muted-foreground">Sin notificaciones</p>
                    </div>

                    <!-- Items -->
                    <div v-else>
                        <div
                            v-for="n in notifications" :key="n.id"
                            class="group flex cursor-pointer items-start gap-3 border-b border-border/50 px-4 py-3 transition last:border-0"
                            :class="n.readAt ? 'bg-background hover:bg-muted/50' : 'bg-primary/5 hover:bg-primary/10'"
                            @click="handleClick(n)"
                        >
                            <!-- Ícono / dot no leída -->
                            <div class="relative mt-0.5 shrink-0">
                                <span class="text-xl leading-none">{{ n.icon }}</span>
                                <span v-if="!n.readAt"
                                    class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-primary ring-2 ring-background"></span>
                            </div>

                            <!-- Contenido -->
                            <div class="min-w-0 flex-1">
                                <p class="truncate text-sm font-semibold text-foreground">{{ n.title }}</p>
                                <p class="mt-0.5 text-xs leading-snug text-muted-foreground line-clamp-2">{{ n.body }}</p>
                                <p class="mt-1 text-[10px] text-muted-foreground/60">{{ timeAgo(n.createdAt) }}</p>
                            </div>

                            <!-- Acciones al hover -->
                            <div class="flex shrink-0 flex-col items-end gap-1 opacity-0 transition group-hover:opacity-100">
                                <button v-if="!n.readAt" @click.stop="markRead(n)"
                                    class="flex h-6 w-6 items-center justify-center rounded-md text-primary hover:bg-primary/10"
                                    title="Marcar como leída">
                                    <Check class="h-3.5 w-3.5" />
                                </button>
                                <button @click.stop="remove(n)"
                                    class="flex h-6 w-6 items-center justify-center rounded-md text-muted-foreground hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950"
                                    title="Eliminar">
                                    <Trash2 class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>
