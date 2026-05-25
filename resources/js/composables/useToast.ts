import { ref } from 'vue';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface Toast {
    id: number;
    type: ToastType;
    title?: string;
    message: string;
    duration: number;
}

const toasts = ref<Toast[]>([]);
let nextId = 0;

function add(message: string, type: ToastType = 'info', title?: string, duration = 4000): number {
    const id = ++nextId;
    toasts.value.push({ id, type, title, message, duration });

    if (duration > 0) {
        setTimeout(() => remove(id), duration);
    }

    return id;
}

function remove(id: number): void {
    const idx = toasts.value.findIndex((t) => t.id === id);
    if (idx !== -1) toasts.value.splice(idx, 1);
}

function success(message: string, title?: string, duration = 4000): number {
    return add(message, 'success', title, duration);
}

function error(message: string, title?: string, duration = 5000): number {
    return add(message, 'error', title, duration);
}

function warning(message: string, title?: string, duration = 4000): number {
    return add(message, 'warning', title, duration);
}

function info(message: string, title?: string, duration = 4000): number {
    return add(message, 'info', title, duration);
}

export function useToast() {
    return { toasts, add, remove, success, error, warning, info };
}
