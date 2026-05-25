<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { AlertTriangle, CheckCircle, Clock, Upload, X } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AppClientLayout from '@/layouts/AppClientLayout.vue';
import { useToast } from '@/composables/useToast';
import { useRealtimeUser } from '@/composables/useRealtime';

interface ReservationPayment {
    id: number;
    confirmation_code: string;
    total_price: number;
    payment_status: string;
    payment_expires_at: string | null;
    payment_proof: string | null;
}

const props = defineProps<{
    reservation: ReservationPayment;
    nequi_number: string;
    expiry_minutes: number;
}>();

const page = usePage();
const toast = useToast();

const file = ref<File | null>(null);
const preview = ref<string | null>(props.reservation.payment_proof ?? null);
const reference = ref('');
const acceptedTerms = ref(false);
const uploading = ref(false);
const dragOver = ref(false);
const submitted = ref(props.reservation.payment_status === 'payment_review');

// ─── Countdown ────────────────────────────────────────────────────────────────
const secondsLeft = ref(0);
const expired = ref(false);
let countdownInterval: ReturnType<typeof setInterval> | null = null;

function calcSecondsLeft(): number {
    if (!props.reservation.payment_expires_at) return 0;
    const diff = Math.floor((new Date(props.reservation.payment_expires_at).getTime() - Date.now()) / 1000);
    return Math.max(0, diff);
}

const countdownLabel = computed(() => {
    const total = secondsLeft.value;
    const m = Math.floor(total / 60).toString().padStart(2, '0');
    const s = (total % 60).toString().padStart(2, '0');
    return `${m}:${s}`;
});

const countdownUrgent = computed(() => secondsLeft.value <= 60 && secondsLeft.value > 0);

function startCountdown() {
    if (!props.reservation.payment_expires_at) return;
    secondsLeft.value = calcSecondsLeft();
    if (secondsLeft.value <= 0) {
        expired.value = true;
        return;
    }
    countdownInterval = setInterval(() => {
        secondsLeft.value = calcSecondsLeft();
        if (secondsLeft.value <= 0) {
            expired.value = true;
            clearInterval(countdownInterval!);
            // Redirigir a mis reservas con mensaje de expiración
            setTimeout(() => {
                router.visit('/reservations', {
                    data: { expired: '1' },
                });
            }, 2000);
        }
    }, 1000);
}

const authUser = (page.props as any).auth?.user;

// Tiempo real: detecta aprobación o rechazo de pago sin recargar
useRealtimeUser(authUser?.id, [], (event, data) => {
    if (event === 'payment.approved' && data.id === props.reservation.id) {
        toast.success('¡Tu pago fue aprobado! Redirigiendo...', '✅ Pago aprobado');
        setTimeout(() => router.visit('/reservations'), 2000);
    }
    if (event === 'payment.rejected' && data.id === props.reservation.id) {
        toast.error('Tu comprobante fue rechazado. Por favor sube uno nuevo.', '❌ Rechazado');
        // Recargar para mostrar estado actualizado
        router.reload({ only: ['reservation'] });
    }
});

onMounted(() => {
    const flash = (page.props as any).flash;
    if (flash?.type === 'success') toast.success(flash.message, '¡Comprobante enviado!');
    if (flash?.type === 'error') toast.error(flash.message, 'Error');

    // Iniciar countdown solo si hay fecha de expiración y no está en revisión/pagado
    if (
        props.reservation.payment_expires_at &&
        !['payment_review', 'paid'].includes(props.reservation.payment_status)
    ) {
        startCountdown();
    }
});

onUnmounted(() => {
    if (countdownInterval) clearInterval(countdownInterval);
});

const statusLabel = computed(() => {
    const map: Record<string, string> = {
        unpaid: 'Sin pagar',
        pending_payment: 'Pago pendiente',
        payment_review: 'En revisión',
        paid: 'Pagado',
        rejected: 'Rechazado',
    };
    return map[props.reservation.payment_status] ?? props.reservation.payment_status;
});

const isRejected = computed(() => props.reservation.payment_status === 'rejected');

function onFileChange(e: Event) {
    const input = e.target as HTMLInputElement;
    if (input.files?.[0]) setFile(input.files[0]);
}

function onDrop(e: DragEvent) {
    dragOver.value = false;
    const f = e.dataTransfer?.files?.[0];
    if (f) setFile(f);
}

function setFile(f: File) {
    file.value = f;
    const reader = new FileReader();
    reader.onload = (ev) => { preview.value = ev.target?.result as string; };
    reader.readAsDataURL(f);
}

function removeFile() {
    file.value = null;
    preview.value = null;
}

function submit() {
    if (!file.value) return;
    uploading.value = true;

    const formData = new FormData();
    formData.append('proof', file.value);
    formData.append('reference', reference.value);
    formData.append('_method', 'POST');

    router.post(
        `/reservations/${props.reservation.id}/payment/proof`,
        formData,
        {
            onError: (errors) => {
                const msg = Object.values(errors)[0] as string;
                toast.error(msg, 'Error al enviar');
                uploading.value = false;
            },
            onFinish: () => { uploading.value = false; },
        },
    );
}
</script>

<template>
    <Head title="Pagar reserva" />
    <AppClientLayout>
        <div class="p-4 lg:p-6 max-w-lg mx-auto">

            <!-- Encabezado -->
            <div class="mb-6">
                <h1 class="text-xl font-bold text-foreground">Pago con Nequi</h1>
                <p class="text-sm text-muted-foreground">Transfiere y sube tu comprobante</p>
            </div>

            <!-- Estado: En revisión -->
            <div v-if="submitted && !isRejected"
                class="mb-6 flex flex-col items-center justify-center rounded-2xl border border-emerald-200 bg-emerald-50 p-8 text-center dark:border-emerald-900 dark:bg-emerald-950">
                <CheckCircle class="mb-3 h-12 w-12 text-emerald-500" />
                <h2 class="mb-1 text-lg font-bold text-emerald-800 dark:text-emerald-200">¡Comprobante enviado!</h2>
                <p class="text-sm text-emerald-700 dark:text-emerald-300">Tu pago está en revisión. El administrador lo confirmará pronto.</p>
            </div>

            <!-- Estado: Rechazado -->
            <div v-if="isRejected"
                class="mb-6 flex items-center gap-3 rounded-2xl border border-destructive/30 bg-destructive/10 px-4 py-3">
                <X class="h-5 w-5 shrink-0 text-destructive" />
                <p class="text-sm font-medium text-destructive">Tu comprobante fue rechazado. Por favor sube uno nuevo.</p>
            </div>

            <!-- Resumen de la reserva -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-4 shadow-sm">
                <div class="flex items-center justify-between mb-1">
                    <span class="text-xs text-muted-foreground">Código</span>
                    <span class="font-mono text-sm font-bold text-primary">{{ reservation.confirmation_code }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-xs text-muted-foreground">Total a pagar</span>
                    <span class="text-lg font-bold text-foreground">${{ reservation.total_price.toLocaleString() }}</span>
                </div>
            </div>

            <!-- Countdown expirado -->
            <div v-if="expired"
                class="mb-4 flex flex-col items-center justify-center rounded-2xl border border-destructive/30 bg-destructive/10 p-6 text-center">
                <AlertTriangle class="mb-2 h-10 w-10 text-destructive" />
                <p class="font-bold text-destructive">¡Tiempo agotado!</p>
                <p class="text-sm text-destructive/80">Tu reserva ha expirado. Serás redirigido en un momento...</p>
            </div>

            <!-- Countdown activo -->
            <div v-if="!expired && secondsLeft > 0 && !submitted"
                class="mb-4 flex items-center justify-between rounded-2xl border px-5 py-3 transition-colors"
                :class="countdownUrgent
                    ? 'border-destructive/40 bg-destructive/10'
                    : 'border-amber-200 bg-amber-50 dark:border-amber-900 dark:bg-amber-950'">
                <div class="flex items-center gap-2">
                    <Clock class="h-5 w-5 shrink-0" :class="countdownUrgent ? 'text-destructive' : 'text-amber-500'" />
                    <p class="text-sm font-medium" :class="countdownUrgent ? 'text-destructive' : 'text-amber-800 dark:text-amber-200'">
                        {{ countdownUrgent ? '¡Apresúrate!' : 'Tiempo restante para pagar' }}
                    </p>
                </div>
                <span class="font-mono text-2xl font-black tabular-nums"
                    :class="countdownUrgent ? 'text-destructive' : 'text-amber-700 dark:text-amber-300'">
                    {{ countdownLabel }}
                </span>
            </div>

            <!-- Instrucciones Nequi -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">¿Cómo pagar?</h2>
                <ol class="space-y-2 text-sm text-muted-foreground list-decimal list-inside">
                    <li>Abre la app de <strong class="text-foreground">Nequi</strong> en tu celular.</li>
                    <li>
                        Transfiere <strong class="text-foreground">${{ reservation.total_price.toLocaleString() }}</strong> al número:
                        <div class="mt-1.5 flex items-center justify-center rounded-xl bg-primary/10 py-2">
                            <span class="text-xl font-bold tracking-widest text-primary">{{ nequi_number || 'No configurado' }}</span>
                        </div>
                    </li>
                    <li>Guarda el comprobante y súbelo abajo.</li>
                </ol>
            </div>

            <!-- Formulario de comprobante -->
            <div class="mb-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">Subir comprobante</h2>

                <!-- Zona de carga -->
                <div
                    class="mb-3 relative flex flex-col items-center justify-center rounded-xl border-2 border-dashed transition"
                    :class="dragOver ? 'border-primary bg-primary/5' : 'border-border bg-muted/30'"
                    @dragover.prevent="dragOver = true"
                    @dragleave="dragOver = false"
                    @drop.prevent="onDrop"
                >
                    <!-- Preview imagen -->
                    <div v-if="preview" class="relative w-full">
                        <img :src="preview" alt="Comprobante" class="w-full max-h-64 object-contain rounded-xl p-2" />
                        <button @click="removeFile"
                            class="absolute top-2 right-2 flex h-7 w-7 items-center justify-center rounded-full bg-destructive text-white shadow">
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Sin archivo -->
                    <div v-else class="py-10 flex flex-col items-center gap-2">
                        <Upload class="h-8 w-8 text-muted-foreground/60" />
                        <p class="text-sm text-muted-foreground">Arrastra tu imagen aquí o</p>
                        <label class="cursor-pointer rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground">
                            Seleccionar archivo
                            <input type="file" class="hidden" accept="image/*,.pdf" @change="onFileChange" />
                        </label>
                        <p class="text-xs text-muted-foreground">JPG, PNG, WEBP o PDF — máx. 5 MB</p>
                    </div>
                </div>

                <!-- Referencia opcional -->
                <div class="mb-4">
                    <label class="mb-1.5 block text-sm font-medium text-foreground">
                        Número de referencia
                        <span class="font-normal text-muted-foreground">(opcional)</span>
                    </label>
                    <input v-model="reference" type="text" placeholder="Ej: 0012345678"
                        class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground placeholder:text-muted-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>

                <!-- Términos y condiciones de la reserva -->
                <label class="mb-4 flex cursor-pointer items-start gap-3 rounded-xl border border-border bg-muted/30 p-3">
                    <input v-model="acceptedTerms" type="checkbox"
                        class="mt-0.5 h-4 w-4 shrink-0 rounded accent-primary" />
                    <span class="text-xs text-foreground">
                        Acepto los <strong>términos y condiciones de la reserva</strong>: me comprometo a
                        llegar con <strong>15 minutos de anterioridad</strong> a la hora reservada para
                        no retrasar otros partidos programados. Si no puedo asistir, debo avisar con al
                        menos <strong>3 horas de anticipación</strong> para reprogramar el horario; de lo
                        contrario, no será posible reprogramar y el dinero <strong>no será reembolsable</strong>,
                        pues no se podrá revender ese horario.
                    </span>
                </label>

                <button @click="submit" :disabled="!file || !acceptedTerms || uploading"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3 text-sm font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-[0.98] disabled:opacity-60">
                    <Upload class="h-4 w-4" />
                    {{ uploading ? 'Enviando...' : 'Enviar comprobante' }}
                </button>
            </div>

        </div>
    </AppClientLayout>
</template>
