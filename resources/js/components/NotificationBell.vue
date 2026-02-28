<script setup lang="ts">
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { Bell, Check, CheckCheck, Trash2, X } from 'lucide-vue-next';
import { router } from '@inertiajs/vue3';
import axios from 'axios';
import NotificationPanelContent from '@/components/NotificationPanelContent.vue';

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
    if (n.url) {
        // Pequeño delay para que el bottom sheet se cierre antes de navegar
        setTimeout(() => router.visit(n.url as string), 150);
    }
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
    const target = e.target as HTMLElement | null;
    if (!target) return;

    // Si el click ocurre dentro del panel (desktop o móvil con Teleport), no cerrar
    if (target.closest('[data-notification-panel]')) return;

    const el = document.getElementById('notification-bell');
    if (el && !el.contains(target)) open.value = false;
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

        <!-- Dropdown desktop (lg+) -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 translate-y-1"
        >
            <div v-if="open"
                data-notification-panel
                class="absolute right-0 z-50 mt-2 hidden w-80 origin-top-right overflow-hidden rounded-2xl border border-border bg-background shadow-xl lg:flex lg:flex-col"
                style="max-height: min(480px, 80vh);"
            >
                <NotificationPanelContent
                    :notifications="notifications"
                    :unreadCount="unreadCount"
                    :loading="loading"
                    @close="open = false"
                    @markRead="markRead"
                    @markAllRead="markAllRead"
                    @remove="remove"
                    @click="handleClick"
                />
            </div>
        </Transition>

        <!-- Bottom sheet móvil -->
        <Teleport to="body">
            <Transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="opacity-0"
                enter-to-class="opacity-100"
                leave-active-class="transition duration-200 ease-in"
                leave-from-class="opacity-100"
                leave-to-class="opacity-0"
            >
                <div v-if="open"
                    class="fixed inset-0 z-50 flex items-end bg-black/50 backdrop-blur-sm lg:hidden"
                    @click.self="open = false"
                >
                    <Transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-full"
                        enter-to-class="translate-y-0"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0"
                        leave-to-class="translate-y-full"
                    >
                        <div v-if="open"
                            data-notification-panel
                            class="w-full rounded-t-3xl bg-background shadow-2xl flex flex-col"
                            style="max-height: 80vh; padding-bottom: env(safe-area-inset-bottom)"
                        >
                            <!-- Handle -->
                            <div class="flex justify-center pt-3 pb-1">
                                <div class="h-1 w-10 rounded-full bg-muted-foreground/30"></div>
                            </div>
                            <NotificationPanelContent
                                :notifications="notifications"
                                :unreadCount="unreadCount"
                                :loading="loading"
                                @close="open = false"
                                @markRead="markRead"
                                @markAllRead="markAllRead"
                                @remove="remove"
                                @click="handleClick"
                            />
                        </div>
                    </Transition>
                </div>
            </Transition>
        </Teleport>
    </div>
</template>
