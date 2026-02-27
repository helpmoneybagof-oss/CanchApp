<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="¿Olvidaste tu contraseña?"
        description="Ingresa tu correo y te enviaremos un enlace para restablecerla"
    >
        <Head title="Recuperar contraseña" />

        <div
            v-if="status"
            class="mb-5 flex items-center gap-3 rounded-lg bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
        >
            <span class="text-xl">📧</span>
            <span>¡Correo enviado! Revisa tu bandeja de entrada y sigue el enlace para restablecer tu contraseña.</span>
        </div>

        <div class="space-y-5">
            <Form v-bind="email.form()" v-slot="{ errors, processing }">
                <div class="grid gap-1.5">
                    <Label for="email" class="text-sm font-medium">Correo electrónico</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        autofocus
                        placeholder="tu@correo.com"
                        class="h-11 rounded-xl"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="mt-5">
                    <Button
                        class="h-11 w-full rounded-xl bg-emerald-600 text-sm font-semibold hover:bg-emerald-700"
                        :disabled="processing"
                        data-test="email-password-reset-link-button"
                    >
                        <Spinner v-if="processing" class="mr-2" />
                        {{ processing ? 'Enviando...' : 'Enviar enlace de recuperación' }}
                    </Button>
                </div>
            </Form>

            <div class="text-center text-sm text-muted-foreground">
                <span>¿Recordaste tu contraseña? </span>
                <TextLink :href="login()" class="font-semibold text-emerald-600 hover:text-emerald-700">Inicia sesión</TextLink>
            </div>
        </div>
    </AuthLayout>
</template>
