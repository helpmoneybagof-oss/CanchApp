<script setup lang="ts">
import { Head, Link, usePage } from '@inertiajs/vue3';
import { CalendarDays, CheckCircle, Clock, Download, ShoppingBag, Smartphone, Star } from 'lucide-vue-next';
import { computed, onMounted, onUnmounted, ref } from 'vue';

withDefaults(
    defineProps<{
        canRegister: boolean;
    }>(),
    {
        canRegister: true,
    },
);

const page    = usePage();
const appName = computed(() => (page.props as any).app_name || 'Cancha Sintética');
const appAddr = computed(() => (page.props as any).app_address || '');
const initials = computed(() => appName.value.charAt(0).toUpperCase());

// ── PWA Install ──
const canInstall  = ref(false);
const isInstalled = ref(false);
const installing  = ref(false);

function checkInstalled() {
    isInstalled.value = (window as any).isPWAInstalled?.() ?? false;
}

function onInstallable() {
    canInstall.value = true;
}

onMounted(() => {
    checkInstalled();
    window.addEventListener('pwa-installable', onInstallable);
    // Si ya hay prompt disponible (navegación SPA)
    if ((window as any).showPWAInstallPrompt) {
        // Intentar detectar si hay prompt esperando
        canInstall.value = !isInstalled.value;
    }
});

onUnmounted(() => {
    window.removeEventListener('pwa-installable', onInstallable);
});

async function installApp() {
    if (!(window as any).showPWAInstallPrompt) return;
    installing.value = true;
    const accepted = await (window as any).showPWAInstallPrompt();
    installing.value = false;
    if (accepted) {
        canInstall.value = false;
        isInstalled.value = true;
    }
}
</script>

<template>
    <Head :title="`${appName} — Reservas en línea`" />

    <div class="min-h-screen bg-background text-foreground">
        <!-- Header -->
        <header class="sticky top-0 z-50 border-b border-border bg-background/95 backdrop-blur-md">
            <div class="mx-auto flex h-14 max-w-5xl items-center justify-between px-4">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-xl bg-primary">
                        <span class="text-sm font-black text-primary-foreground">{{ initials }}</span>
                    </div>
                    <span class="font-bold text-foreground">{{ appName }}</span>
                </div>
                <nav class="flex items-center gap-2">
                    <Link
                        v-if="$page.props.auth.user"
                        href="/dashboard"
                        class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                    >
                        Mi cuenta
                    </Link>
                    <template v-else>
                        <Link
                            href="/login"
                            class="rounded-xl px-4 py-2 text-sm font-medium text-foreground transition-all hover:bg-muted"
                        >
                            Ingresar
                        </Link>
                        <Link
                            v-if="canRegister"
                            href="/register"
                            class="rounded-xl bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition-all hover:bg-primary/90 active:scale-95"
                        >
                            Registrarse
                        </Link>
                    </template>
                </nav>
            </div>
        </header>

        <!-- Hero -->
        <section class="px-4 py-16 text-center lg:py-24">
            <div class="mx-auto max-w-2xl">
                <div class="mb-4 inline-flex items-center gap-2 rounded-full bg-primary/10 px-4 py-1.5 text-sm font-medium text-primary">
                    <Star class="h-4 w-4 fill-primary" />
                    Reservas 100% en línea
                </div>
                <h1 class="mb-4 text-4xl font-black leading-tight text-foreground lg:text-5xl">
                    Reserva tu cancha<br />
                    <span class="text-primary">cuando quieras</span>
                </h1>
                <p class="mb-8 text-lg text-muted-foreground">
                    Consulta disponibilidad en tiempo real, reserva tu horario favorito
                    y recibe confirmación instantánea en tu correo.
                </p>
                <div class="flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                    <Link
                        href="/calendar"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary px-6 py-3.5 text-base font-bold text-primary-foreground shadow-lg transition-all hover:bg-primary/90 active:scale-95 sm:w-auto"
                    >
                        <CalendarDays class="h-5 w-5" />
                        Ver disponibilidad
                    </Link>
                    <Link
                        v-if="canRegister && !$page.props.auth.user"
                        href="/register"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl border border-border px-6 py-3.5 text-base font-semibold text-foreground transition-all hover:bg-muted active:scale-95 sm:w-auto"
                    >
                        Crear cuenta gratis
                    </Link>
                    <!-- Botón instalar PWA -->
                    <button
                        v-if="canInstall && !isInstalled"
                        @click="installApp"
                        :disabled="installing"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl border-2 border-primary px-6 py-3.5 text-base font-bold text-primary transition-all hover:bg-primary/10 active:scale-95 disabled:opacity-60 sm:w-auto"
                    >
                        <Download class="h-5 w-5" />
                        {{ installing ? 'Instalando...' : 'Instalar app gratis' }}
                    </button>
                    <!-- Ya instalada -->
                    <div v-if="isInstalled"
                        class="flex w-full items-center justify-center gap-2 rounded-2xl bg-emerald-50 px-6 py-3.5 text-base font-semibold text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300 sm:w-auto">
                        <Smartphone class="h-5 w-5" />
                        App instalada ✓
                    </div>
                </div>
            </div>
        </section>

        <!-- Horarios destacados -->
        <section class="px-4 pb-12">
            <div class="mx-auto max-w-5xl">
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                        <div class="rounded-xl bg-primary/10 p-3">
                            <Clock class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Horario disponible</p>
                            <p class="text-base font-bold text-foreground">5:00 PM – 11:00 PM</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                        <div class="rounded-xl bg-primary/10 p-3">
                            <CalendarDays class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Días</p>
                            <p class="text-base font-bold text-foreground">Lunes a Domingo</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-4 rounded-2xl border border-border bg-card p-5 shadow-sm">
                        <div class="rounded-xl bg-primary/10 p-3">
                            <ShoppingBag class="h-6 w-6 text-primary" />
                        </div>
                        <div>
                            <p class="text-xs text-muted-foreground">Extras</p>
                            <p class="text-base font-bold text-foreground">Bebidas y snacks</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Características -->
        <section class="bg-muted/40 px-4 py-12">
            <div class="mx-auto max-w-5xl">
                <h2 class="mb-8 text-center text-2xl font-bold text-foreground">
                    Todo lo que necesitas
                </h2>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <div
                        v-for="feature in [
                            { title: 'Calendario en tiempo real', desc: 'Ve qué horarios están disponibles al instante, sin llamadas ni mensajes.' },
                            { title: 'Confirmación por correo', desc: 'Recibe tu comprobante de reserva en PDF directo a tu correo electrónico.' },
                            { title: 'Agrega consumibles', desc: 'Pide bebidas, snacks y más durante el proceso de reserva.' },
                            { title: 'Cancela con tiempo', desc: 'Cancela tu reserva con al menos 2 horas de anticipación sin inconvenientes.' },
                            { title: 'Recordatorios automáticos', desc: 'Te avisamos 24 horas y 2 horas antes de tu reserva para que no olvides.' },
                            { title: 'App instalable', desc: 'Instala la app en tu celular como si fuera nativa, sin tiendas de aplicaciones.' },
                        ]"
                        :key="feature.title"
                        class="rounded-2xl border border-border bg-card p-5 shadow-sm"
                    >
                        <div class="mb-3 flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-primary" />
                            <h3 class="font-semibold text-foreground">{{ feature.title }}</h3>
                        </div>
                        <p class="text-sm text-muted-foreground">{{ feature.desc }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- CTA final -->
        <section class="px-4 py-16 text-center">
            <div class="mx-auto max-w-lg">
                <h2 class="mb-4 text-2xl font-bold text-foreground">
                    ¿Listo para jugar?
                </h2>
                <p class="mb-6 text-muted-foreground">
                    Regístrate gratis y haz tu primera reserva en menos de 2 minutos.
                </p>
                <Link
                    v-if="canRegister"
                    href="/register"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-8 py-4 text-base font-bold text-primary-foreground shadow-lg transition-all hover:bg-primary/90 active:scale-95"
                >
                    Comenzar ahora →
                </Link>
                <Link
                    v-else
                    href="/login"
                    class="inline-flex items-center gap-2 rounded-2xl bg-primary px-8 py-4 text-base font-bold text-primary-foreground shadow-lg transition-all hover:bg-primary/90 active:scale-95"
                >
                    Ingresar →
                </Link>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-border px-4 py-6 text-center text-sm text-muted-foreground">
            <p>© 2026 {{ appName }}<span v-if="appAddr"> · {{ appAddr }}</span> · Todos los derechos reservados</p>
        </footer>
    </div>
</template>
