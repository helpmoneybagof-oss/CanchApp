<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3';
import { CalendarDays, Clock, MapPin } from 'lucide-vue-next';
import AppClientLayout from '@/layouts/AppClientLayout.vue';

const page = usePage();
const user = page.props.auth.user;
</script>

<template>
    <Head title="Inicio" />

    <AppClientLayout>
        <div class="p-4 lg:p-6">
            <!-- Bienvenida -->
            <div class="mb-6">
                <h1 class="text-2xl font-bold text-foreground">
                    Hola, {{ user.name.split(' ')[0] }} 👋
                </h1>
                <p class="text-sm text-muted-foreground">
                    ¿Listo para reservar tu cancha hoy?
                </p>
            </div>

            <!-- CTA principal -->
            <a
                href="/calendar"
                class="mb-6 flex items-center justify-between rounded-2xl bg-primary p-5 shadow-lg transition-transform active:scale-[0.98]"
            >
                <div>
                    <p class="text-sm font-medium text-primary-foreground/80">
                        Reserva ahora
                    </p>
                    <p class="text-xl font-bold text-primary-foreground">
                        Ver disponibilidad
                    </p>
                </div>
                <div class="rounded-xl bg-white/20 p-3">
                    <CalendarDays class="h-7 w-7 text-white" />
                </div>
            </a>

            <!-- Info rápida -->
            <div class="mb-6 grid grid-cols-2 gap-3">
                <div class="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="rounded-xl bg-primary/10 p-2.5">
                        <Clock class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Horario</p>
                        <p class="text-sm font-semibold text-foreground">5PM – 11PM</p>
                    </div>
                </div>
                <div class="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-sm">
                    <div class="rounded-xl bg-primary/10 p-2.5">
                        <MapPin class="h-5 w-5 text-primary" />
                    </div>
                    <div>
                        <p class="text-xs text-muted-foreground">Cancha</p>
                        <p class="text-sm font-semibold text-foreground">Sintética</p>
                    </div>
                </div>
            </div>

            <!-- Accesos rápidos -->
            <div class="rounded-2xl border border-border bg-card p-4 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold text-foreground">Accesos rápidos</h2>
                <div class="grid grid-cols-2 gap-3">
                    <a
                        href="/calendar"
                        class="flex flex-col items-center gap-2 rounded-xl border border-border p-3 text-center transition-all hover:border-primary hover:bg-primary/5"
                    >
                        <CalendarDays class="h-5 w-5 text-primary" />
                        <span class="text-xs font-medium text-foreground">Calendario</span>
                    </a>
                    <a
                        href="/reservations"
                        class="flex flex-col items-center gap-2 rounded-xl border border-border p-3 text-center transition-all hover:border-primary hover:bg-primary/5"
                    >
                        <Clock class="h-5 w-5 text-primary" />
                        <span class="text-xs font-medium text-foreground">Mis reservas</span>
                    </a>
                </div>
            </div>
        </div>
    </AppClientLayout>
</template>
