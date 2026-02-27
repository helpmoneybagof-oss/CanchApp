<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div class="flex min-h-svh">
        <!-- Panel izquierdo — branding (oculto en móvil) -->
        <div
            class="relative hidden w-1/2 flex-col justify-between overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-400 p-12 lg:flex"
        >
            <!-- Decoración de fondo -->
            <div class="absolute inset-0 opacity-10">
                <div class="absolute -left-20 -top-20 h-96 w-96 rounded-full bg-white"></div>
                <div class="absolute -bottom-32 -right-20 h-[500px] w-[500px] rounded-full bg-white"></div>
                <div class="absolute left-1/2 top-1/2 h-64 w-64 -translate-x-1/2 -translate-y-1/2 rounded-full bg-white opacity-20"></div>
            </div>

            <!-- Logo / nombre arriba -->
            <Link :href="home()" class="relative z-10 flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                    <span class="text-xl font-black text-white">⚽</span>
                </div>
                <span class="text-lg font-bold text-white">CanchApp</span>
            </Link>

            <!-- Texto central -->
            <div class="relative z-10 space-y-6">
                <div class="space-y-3">
                    <h2 class="text-4xl font-black leading-tight text-white">
                        Reserva tu cancha<br />
                        <span class="text-emerald-100">en segundos</span>
                    </h2>
                    <p class="text-lg text-emerald-100/90">
                        Gestiona horarios, consumibles y pagos desde un solo lugar.
                    </p>
                </div>

                <!-- Features -->
                <div class="space-y-3">
                    <div v-for="feature in [
                        { icon: '📅', text: 'Disponibilidad en tiempo real' },
                        { icon: '🛒', text: 'Consumibles incluidos en la reserva' },
                        { icon: '📄', text: 'Comprobante PDF por correo' },
                    ]" :key="feature.text"
                        class="flex items-center gap-3 rounded-xl bg-white/10 px-4 py-3 backdrop-blur-sm"
                    >
                        <span class="text-xl">{{ feature.icon }}</span>
                        <span class="text-sm font-medium text-white">{{ feature.text }}</span>
                    </div>
                </div>
            </div>

            <!-- Footer del panel -->
            <p class="relative z-10 text-sm text-emerald-100/70">
                © {{ new Date().getFullYear() }} CanchApp · Todos los derechos reservados
            </p>
        </div>

        <!-- Panel derecho — formulario -->
        <div class="flex w-full flex-col bg-background lg:w-1/2">

            <!-- ── MÓVIL: layout de pantalla completa con gradiente arriba ── -->
            <div class="flex min-h-svh flex-col lg:hidden">

                <!-- Franja superior verde -->
                <div class="relative overflow-hidden bg-gradient-to-br from-emerald-600 via-emerald-500 to-teal-400 px-6 pb-14 pt-12">
                    <div class="absolute -right-12 -top-12 h-44 w-44 rounded-full bg-white/10"></div>
                    <div class="absolute -left-8 bottom-0 h-28 w-28 rounded-full bg-white/10"></div>

                    <Link :href="home()" class="relative z-10 mb-5 flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-white/20 backdrop-blur-sm">
                            <span class="text-lg font-black text-white">⚽</span>
                        </div>
                        <span class="text-base font-bold text-white">CanchApp</span>
                    </Link>

                    <div class="relative z-10 space-y-1">
                        <h1 class="text-2xl font-black text-white">{{ title }}</h1>
                        <p class="text-sm text-emerald-100/85">{{ description }}</p>
                    </div>
                </div>

                <!-- Tarjeta blanca que crece para llenar el resto de la pantalla -->
                <div class="relative -mt-6 flex flex-1 flex-col rounded-t-3xl bg-background px-6 pt-8 pb-8 shadow-[0_-4px_24px_rgba(0,0,0,0.08)]">
                    <div class="flex flex-1 flex-col justify-between">
                        <!-- Formulario -->
                        <div>
                            <slot />
                        </div>
                        <!-- Footer móvil -->
                        <p class="mt-8 text-center text-xs text-muted-foreground">
                            © {{ new Date().getFullYear() }} CanchApp · Todos los derechos reservados
                        </p>
                    </div>
                </div>
            </div>

            <!-- ── DESKTOP: columna centrada normal ── -->
            <div class="hidden flex-1 flex-col items-center justify-center px-16 lg:flex">
                <div class="w-full max-w-sm">
                    <div class="mb-8 space-y-1.5">
                        <h1 class="text-2xl font-bold tracking-tight text-foreground">
                            {{ title }}
                        </h1>
                        <p class="text-sm text-muted-foreground">
                            {{ description }}
                        </p>
                    </div>
                    <slot />
                </div>
            </div>

        </div>
    </div>
</template>
