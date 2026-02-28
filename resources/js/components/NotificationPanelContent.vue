<script setup lang="ts">
import { Bell, Check, CheckCheck, Trash2, X } from 'lucide-vue-next';

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

defineProps<{
    notifications: Notification[];
    unreadCount: number;
    loading: boolean;
}>();

const emit = defineEmits<{
    close: [];
    markRead: [n: Notification];
    markAllRead: [];
    remove: [n: Notification];
    click: [n: Notification];
}>();

function timeAgo(dateStr: string): string {
    const diff = Math.floor((Date.now() - new Date(dateStr).getTime()) / 1000);
    if (diff < 60)    return 'ahora';
    if (diff < 3600)  return `${Math.floor(diff / 60)}m`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h`;
    return `${Math.floor(diff / 86400)}d`;
}
</script>

<template>
    <!-- Header -->
    <div class="flex shrink-0 items-center justify-between border-b border-border px-4 py-3">
        <div class="flex items-center gap-2">
            <Bell class="h-4 w-4 text-primary" />
            <h3 class="text-sm font-bold text-foreground">Notificaciones</h3>
            <span v-if="unreadCount > 0"
                class="rounded-full bg-red-100 px-1.5 py-0.5 text-[10px] font-bold text-red-600 dark:bg-red-950 dark:text-red-400">
                {{ unreadCount }} nueva{{ unreadCount !== 1 ? 's' : '' }}
            </span>
        </div>
        <div class="flex items-center gap-1">
            <button v-if="unreadCount > 0" @click="emit('markAllRead')"
                class="flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-primary transition hover:bg-primary/10"
                title="Marcar todas como leídas">
                <CheckCheck class="h-3.5 w-3.5" />
                Todas leídas
            </button>
            <button @click="emit('close')"
                class="flex h-7 w-7 items-center justify-center rounded-lg text-muted-foreground transition hover:bg-muted">
                <X class="h-3.5 w-3.5" />
            </button>
        </div>
    </div>

    <!-- Lista -->
    <div class="overflow-y-auto flex-1">
        <!-- Loading -->
        <div v-if="loading" class="flex items-center justify-center py-8">
            <div class="h-5 w-5 animate-spin rounded-full border-2 border-primary border-t-transparent"></div>
        </div>

        <!-- Vacío -->
        <div v-else-if="notifications.length === 0"
            class="flex flex-col items-center justify-center gap-2 py-12 text-center">
            <Bell class="h-10 w-10 text-muted-foreground/30" />
            <p class="text-sm font-medium text-muted-foreground">Sin notificaciones</p>
            <p class="text-xs text-muted-foreground/60">Te avisaremos cuando haya novedades</p>
        </div>

        <!-- Items -->
        <div v-else>
            <div
                v-for="n in notifications" :key="n.id"
                class="group flex cursor-pointer items-start gap-3 border-b border-border/50 px-4 py-3.5 transition-colors last:border-0 active:scale-[0.99]"
                :class="n.readAt ? 'bg-background hover:bg-muted/50' : 'bg-primary/5 hover:bg-primary/10'"
                @click="emit('click', n)"
            >
                <!-- Ícono -->
                <div class="relative mt-0.5 shrink-0">
                    <span class="text-2xl leading-none">{{ n.icon }}</span>
                    <span v-if="!n.readAt"
                        class="absolute -right-1 -top-1 h-2.5 w-2.5 rounded-full bg-primary ring-2 ring-background"></span>
                </div>

                <!-- Contenido -->
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-foreground">{{ n.title }}</p>
                    <p class="mt-0.5 text-xs leading-snug text-muted-foreground line-clamp-2">{{ n.body }}</p>
                    <div class="mt-1.5 flex items-center gap-2">
                        <p class="text-[10px] text-muted-foreground/60">{{ timeAgo(n.createdAt) }}</p>
                        <span v-if="n.url" class="text-[10px] font-medium text-primary">Ver detalle →</span>
                    </div>
                </div>

                <!-- Acciones: siempre visibles en móvil, hover en desktop -->
                <div class="flex shrink-0 flex-col items-end gap-1 opacity-100 transition lg:opacity-0 lg:group-hover:opacity-100">
                    <button v-if="!n.readAt" @click.stop="emit('markRead', n)"
                        class="flex h-7 w-7 items-center justify-center rounded-md text-primary hover:bg-primary/10"
                        title="Marcar como leída">
                        <Check class="h-3.5 w-3.5" />
                    </button>
                    <button @click.stop="emit('remove', n)"
                        class="flex h-7 w-7 items-center justify-center rounded-md text-muted-foreground hover:bg-red-50 hover:text-red-500 dark:hover:bg-red-950"
                        title="Eliminar">
                        <Trash2 class="h-3.5 w-3.5" />
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
