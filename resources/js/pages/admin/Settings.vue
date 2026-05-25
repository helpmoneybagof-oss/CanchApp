<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { Save, Settings as SettingsIcon, KeyRound } from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import PushDebugCard from '@/components/PushDebugCard.vue';
import { useToast } from '@/composables/useToast';
import AppAdminLayout from '@/layouts/AppAdminLayout.vue';

const toast = useToast();
const page = usePage();

const props = defineProps<{
    settings: {
        court_name: string;
        court_address: string;
        contact_phone: string;
        contact_email: string;
        court_price_per_hour: string;
        nequi_number: string;
        payment_expiry_minutes: string;
        mail_host: string;
        mail_port: string;
        mail_username: string;
        mail_password: string;
        mail_from_address: string;
        mail_from_name: string;
        mail_encryption: string;
    };
}>();

const form = ref({ ...props.settings });
const saving = ref(false);
const errors = ref<Record<string, string>>({});

// Cambio de contraseña
const passwordForm = ref({ current_password: '', password: '', password_confirmation: '' });
const savingPassword = ref(false);
const passwordErrors = ref<Record<string, string>>({});

onMounted(() => {
    const flash = (page.props as any).flash;
    if (flash?.type === 'success') toast.success(flash.message, '¡Guardado!');
});

function save() {
    errors.value = {};
    saving.value = true;
    router.put('/admin/settings', form.value, {
        onError: (e) => { errors.value = e; },
        onFinish: () => { saving.value = false; },
    });
}

function changePassword() {
    passwordErrors.value = {};
    savingPassword.value = true;
    router.post('/admin/settings/password', passwordForm.value, {
        onSuccess: () => { passwordForm.value = { current_password: '', password: '', password_confirmation: '' }; },
        onError: (e) => { passwordErrors.value = e; },
        onFinish: () => { savingPassword.value = false; },
    });
}
</script>

<template>
    <Head title="Configuración" />
    <AppAdminLayout>
        <div class="p-4 lg:p-6">
            <div class="mb-6">
                <h1 class="text-xl font-bold text-foreground">Configuración</h1>
                <p class="text-sm text-muted-foreground">Parámetros generales del sistema</p>
            </div>

            <div class="mx-auto max-w-2xl space-y-5">
                <!-- Información de la cancha -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 flex items-center gap-2 text-sm font-semibold text-foreground">
                        <SettingsIcon class="h-4 w-4 text-primary" />
                        Información de la cancha
                    </h2>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">
                                Nombre de la cancha *
                            </label>
                            <input v-model="form.court_name" type="text"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="errors.court_name ? 'border-destructive' : ''" />
                            <p v-if="errors.court_name" class="mt-1 text-xs text-destructive">{{ errors.court_name }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Dirección</label>
                            <input v-model="form.court_address" type="text"
                                placeholder="Dirección física de la cancha"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                        </div>
                    </div>
                </div>

                <!-- Contacto -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Información de contacto</h2>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Teléfono de contacto</label>
                            <input v-model="form.contact_phone" type="text" placeholder="Ej: 3001234567"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Correo de contacto</label>
                            <input v-model="form.contact_email" type="email" placeholder="admin@cancha.com"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="errors.contact_email ? 'border-destructive' : ''" />
                            <p v-if="errors.contact_email" class="mt-1 text-xs text-destructive">{{ errors.contact_email }}</p>
                        </div>
                    </div>
                </div>

                <!-- Precios -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-4 text-sm font-semibold text-foreground">Precios</h2>
                    <div>
                        <label class="mb-1.5 block text-sm font-medium text-foreground">
                            Precio por hora (COP) *
                        </label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-sm font-semibold text-muted-foreground">$</span>
                            <input v-model="form.court_price_per_hour" type="number" min="0" step="1000"
                                class="w-full rounded-xl border border-border bg-background py-2.5 pl-7 pr-3 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="errors.court_price_per_hour ? 'border-destructive' : ''" />
                        </div>
                        <p v-if="errors.court_price_per_hour" class="mt-1 text-xs text-destructive">{{ errors.court_price_per_hour }}</p>
                        <p class="mt-1.5 text-xs text-muted-foreground">
                            Precio actual: <span class="font-semibold text-primary">${{ Number(form.court_price_per_hour).toLocaleString() }}</span> / hora
                        </p>
                    </div>
                </div>

                <!-- Pagos Nequi -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-1 text-sm font-semibold text-foreground">Pagos con Nequi</h2>
                    <p class="mb-4 text-xs text-muted-foreground">Configura el número al que los clientes deben transferir.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Número Nequi de la cancha</label>
                            <input v-model="form.nequi_number" type="text" placeholder="Ej: 3001234567"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="errors.nequi_number ? 'border-destructive' : ''" />
                            <p v-if="errors.nequi_number" class="mt-1 text-xs text-destructive">{{ errors.nequi_number }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">
                                Tiempo de expiración (minutos)
                            </label>
                            <input v-model="form.payment_expiry_minutes" type="number" min="5" max="1440" placeholder="30"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="errors.payment_expiry_minutes ? 'border-destructive' : ''" />
                            <p class="mt-1 text-xs text-muted-foreground">Minutos que tiene el cliente para subir el comprobante antes de que la reserva expire. (Por defecto: 30 min)</p>
                            <p v-if="errors.payment_expiry_minutes" class="mt-1 text-xs text-destructive">{{ errors.payment_expiry_minutes }}</p>
                        </div>
                    </div>
                </div>

                <!-- Correo SMTP -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-1 text-sm font-semibold text-foreground">Configuración de correo (SMTP)</h2>
                    <p class="mb-4 text-xs text-muted-foreground">Necesario para enviar confirmaciones, recordatorios y alertas por email.</p>
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">Servidor SMTP</label>
                                <input v-model="form.mail_host" type="text" placeholder="smtp.gmail.com"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">Puerto</label>
                                <input v-model="form.mail_port" type="text" placeholder="587"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Usuario / Email</label>
                            <input v-model="form.mail_username" type="text" placeholder="tu@correo.com"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Contraseña / App password</label>
                            <input v-model="form.mail_password" type="password" placeholder="••••••••••••"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                            <p class="mt-1 text-xs text-muted-foreground">Deja en blanco para no cambiar la contraseña actual.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">Encriptación</label>
                                <select v-model="form.mail_encryption"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                                    <option value="tls">TLS</option>
                                    <option value="ssl">SSL</option>
                                    <option value="">Ninguna</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1.5 block text-sm font-medium text-foreground">Nombre del remitente</label>
                                <input v-model="form.mail_from_name" type="text" placeholder="Cancha Sintética"
                                    class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                            </div>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Email del remitente</label>
                            <input v-model="form.mail_from_address" type="email" placeholder="noreply@cancha.com"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="errors.mail_from_address ? 'border-destructive' : ''" />
                            <p v-if="errors.mail_from_address" class="mt-1 text-xs text-destructive">{{ errors.mail_from_address }}</p>
                        </div>
                    </div>
                </div>

                <!-- Botón guardar -->
                <button @click="save" :disabled="saving"
                    class="flex w-full items-center justify-center gap-2 rounded-2xl bg-primary py-3.5 text-sm font-bold text-primary-foreground shadow transition hover:bg-primary/90 active:scale-[0.98] disabled:opacity-60">
                    <Save class="h-4 w-4" />
                    {{ saving ? 'Guardando...' : 'Guardar configuración' }}
                </button>

                <!-- Diagnóstico Push Notifications -->
                <PushDebugCard />

                <!-- Cambiar contraseña del admin -->
                <div class="rounded-2xl border border-border bg-card p-5 shadow-sm">
                    <h2 class="mb-1 flex items-center gap-2 text-sm font-semibold text-foreground">
                        <KeyRound class="h-4 w-4 text-primary" />
                        Cambiar contraseña
                    </h2>
                    <p class="mb-4 text-xs text-muted-foreground">Actualiza la contraseña de tu cuenta de administrador.</p>
                    <div class="space-y-4">
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Contraseña actual</label>
                            <input v-model="passwordForm.current_password" type="password" placeholder="••••••••"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="passwordErrors.current_password ? 'border-destructive' : ''" />
                            <p v-if="passwordErrors.current_password" class="mt-1 text-xs text-destructive">{{ passwordErrors.current_password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Nueva contraseña</label>
                            <input v-model="passwordForm.password" type="password" placeholder="Mínimo 8 caracteres"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"
                                :class="passwordErrors.password ? 'border-destructive' : ''" />
                            <p v-if="passwordErrors.password" class="mt-1 text-xs text-destructive">{{ passwordErrors.password }}</p>
                        </div>
                        <div>
                            <label class="mb-1.5 block text-sm font-medium text-foreground">Confirmar nueva contraseña</label>
                            <input v-model="passwordForm.password_confirmation" type="password" placeholder="Repite la nueva contraseña"
                                class="w-full rounded-xl border border-border bg-background px-3 py-2.5 text-sm text-foreground focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                        </div>
                        <button @click="changePassword" :disabled="savingPassword"
                            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-destructive py-3 text-sm font-bold text-white shadow transition hover:bg-destructive/90 active:scale-[0.98] disabled:opacity-60">
                            <KeyRound class="h-4 w-4" />
                            {{ savingPassword ? 'Actualizando...' : 'Cambiar contraseña' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </AppAdminLayout>
</template>
