<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import axios from 'axios';
import { subscribeToPush, requestPushPermission, usePush } from '@/composables/usePush';

const { isSupported, permission } = usePush();

const loading = ref(false);
const status = ref<any>(null);
const error = ref<string | null>(null);

const vapid = computed(() => (window as any).__vapid_public_key__ || '');

const swScope = ref<string | null>(null);
const hasBrowserSubscription = ref<boolean | null>(null);

async function refreshClientState() {
    try {
        if (!('serviceWorker' in navigator)) {
            swScope.value = null;
            hasBrowserSubscription.value = null;
            return;
        }

        const reg = await navigator.serviceWorker.getRegistration();
        swScope.value = reg?.scope ?? null;

        if (reg && 'pushManager' in reg) {
            const sub = await reg.pushManager.getSubscription();
            hasBrowserSubscription.value = !!sub;
        } else {
            hasBrowserSubscription.value = null;
        }
    } catch {
        // Si algo falla, no romper la UI
        swScope.value = null;
        hasBrowserSubscription.value = null;
    }
}

async function refreshStatus() {
    await refreshClientState();
    loading.value = true;
    error.value = null;
    try {
        const res = await axios.get('/api/push/status', {
            headers: { 'Accept': 'application/json' },
        });
        status.value = res.data;
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? e?.message ?? 'Error consultando estado push';
    } finally {
        loading.value = false;
    }
}

async function ensureSubscribed() {
    await subscribeToPush();
    await refreshStatus();
}

async function askPermission() {
    await requestPushPermission();
    await refreshStatus();
}

async function sendTest() {
    loading.value = true;
    error.value = null;
    try {
        await axios.post('/api/push/test', { url: '/dashboard' }, {
            headers: { 'Accept': 'application/json' },
        });
    } catch (e: any) {
        error.value = e?.response?.data?.message ?? e?.message ?? 'Error enviando push de prueba';
    } finally {
        loading.value = false;
    }
}

onMounted(() => {
    refreshStatus();
});
</script>

<template>
    <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
        <div class="mb-3 flex items-center justify-between gap-2">
            <div>
                <h3 class="text-sm font-semibold text-foreground">Notificaciones Push (diagnóstico)</h3>
                <p class="text-xs text-muted-foreground">Útil para revisar por qué no llegan al celular.</p>
            </div>
            <button @click="refreshStatus" :disabled="loading"
                class="rounded-xl border border-border px-3 py-2 text-xs font-semibold hover:bg-muted disabled:opacity-60">
                {{ loading ? 'Actualizando…' : 'Actualizar' }}
            </button>
        </div>

        <div v-if="error" class="mb-3 rounded-xl border border-destructive/30 bg-destructive/10 px-3 py-2 text-xs text-destructive">
            {{ error }}
        </div>

        <div class="grid grid-cols-1 gap-2 text-xs">
            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2">
                <span>Soportado en este dispositivo</span>
                <span :class="isSupported ? 'text-emerald-600' : 'text-red-600'">{{ isSupported ? 'Sí' : 'No' }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2">
                <span>Permiso</span>
                <span class="font-semibold">{{ permission }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2">
                <span>VAPID public key (frontend)</span>
                <span :class="vapid ? 'text-emerald-600' : 'text-red-600'">{{ vapid ? 'OK' : 'Vacía' }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2">
                <span>Service Worker registrado</span>
                <span :class="swScope ? 'text-emerald-600' : 'text-red-600'">{{ swScope ? 'Sí' : 'No' }}</span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2">
                <span>Suscripción en el dispositivo</span>
                <span v-if="hasBrowserSubscription === null" class="text-muted-foreground">—</span>
                <span v-else :class="hasBrowserSubscription ? 'text-emerald-600' : 'text-red-600'">{{ hasBrowserSubscription ? 'Sí' : 'No' }}</span>
            </div>

            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2" v-if="status">
                <span>VAPID configurado (servidor)</span>
                <span :class="status.server?.vapid_public_key_configured && status.server?.vapid_private_key_configured ? 'text-emerald-600' : 'text-red-600'">
                    {{ status.server?.vapid_public_key_configured && status.server?.vapid_private_key_configured ? 'OK' : 'Falta' }}
                </span>
            </div>
            <div class="flex items-center justify-between rounded-xl bg-muted/40 px-3 py-2" v-if="status">
                <span>Suscripciones guardadas (BD)</span>
                <span class="font-semibold">{{ status.subscriptions?.count ?? 0 }}</span>
            </div>
        </div>

        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-3">
            <button @click="askPermission" :disabled="loading || !isSupported"
                class="rounded-xl bg-amber-500 px-3 py-2 text-xs font-bold text-white hover:bg-amber-600 disabled:opacity-60">
                Pedir permiso
            </button>
            <button @click="ensureSubscribed" :disabled="loading || !isSupported"
                class="rounded-xl bg-primary px-3 py-2 text-xs font-bold text-primary-foreground hover:bg-primary/90 disabled:opacity-60">
                Guardar suscripción
            </button>
            <button @click="sendTest" :disabled="loading || !isSupported"
                class="rounded-xl border border-border px-3 py-2 text-xs font-bold hover:bg-muted disabled:opacity-60">
                Enviar prueba
            </button>
        </div>

        <p class="mt-3 text-[11px] text-muted-foreground">
            Nota iPhone: para Push Web debe estar instalada como PWA y iOS 16.4+.
        </p>
    </div>
</template>
