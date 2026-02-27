<script setup lang="ts">
import { CheckCircle, Info, TriangleAlert, X, XCircle } from 'lucide-vue-next';
import { useToast } from '@/composables/useToast';

const { toasts, remove } = useToast();

const icons = {
    success: CheckCircle,
    error: XCircle,
    warning: TriangleAlert,
    info: Info,
};

const colors = {
    success: 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-800 dark:bg-emerald-950 dark:text-emerald-200',
    error: 'border-red-200 bg-red-50 text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200',
    warning: 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-200',
    info: 'border-blue-200 bg-blue-50 text-blue-800 dark:border-blue-800 dark:bg-blue-950 dark:text-blue-200',
};

const iconColors = {
    success: 'text-emerald-500',
    error: 'text-red-500',
    warning: 'text-amber-500',
    info: 'text-blue-500',
};
</script>

<template>
    <Teleport to="body">
        <div
            class="fixed right-4 top-4 z-[100] flex flex-col gap-2 lg:right-6 lg:top-6"
            style="max-width: 360px; width: calc(100vw - 2rem)"
        >
            <TransitionGroup
                name="toast"
                tag="div"
                class="flex flex-col gap-2"
            >
                <div
                    v-for="toast in toasts"
                    :key="toast.id"
                    class="flex items-start gap-3 rounded-2xl border p-4 shadow-lg backdrop-blur-sm"
                    :class="colors[toast.type]"
                >
                    <component
                        :is="icons[toast.type]"
                        class="mt-0.5 h-5 w-5 shrink-0"
                        :class="iconColors[toast.type]"
                    />
                    <div class="flex-1 min-w-0">
                        <p v-if="toast.title" class="text-sm font-semibold leading-snug">
                            {{ toast.title }}
                        </p>
                        <p class="text-sm leading-snug" :class="toast.title ? 'mt-0.5 opacity-80' : ''">
                            {{ toast.message }}
                        </p>
                    </div>
                    <button
                        @click="remove(toast.id)"
                        class="shrink-0 rounded-lg p-0.5 opacity-60 transition hover:opacity-100"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </TransitionGroup>
        </div>
    </Teleport>
</template>

<style scoped>
.toast-enter-active {
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.toast-leave-active {
    transition: all 0.2s ease-in;
}
.toast-enter-from {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}
.toast-leave-to {
    opacity: 0;
    transform: translateX(100%) scale(0.9);
}
.toast-move {
    transition: transform 0.3s ease;
}
</style>
