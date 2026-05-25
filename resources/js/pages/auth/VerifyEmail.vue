<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    status?: string;
}>();
</script>

<template>
    <AuthLayout
        title="Verifica tu correo 📧"
        description="Haz clic en el enlace que te enviamos para activar tu cuenta."
    >
        <Head title="Verificar correo" />

        <div
            v-if="status === 'verification-link-sent'"
            class="mb-5 rounded-lg bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
        >
            Se ha enviado un nuevo enlace de verificación a tu correo electrónico.
        </div>

        <div class="rounded-xl border border-border bg-muted/40 p-5 text-center">
            <div class="mb-3 text-4xl">📬</div>
            <p class="text-sm text-muted-foreground">
                Revisa tu bandeja de entrada y haz clic en el enlace de verificación para continuar.
            </p>
        </div>

        <Form
            v-bind="send.form()"
            class="mt-5 space-y-4 text-center"
            v-slot="{ processing }"
        >
            <Button
                class="h-11 w-full rounded-xl bg-emerald-600 text-sm font-semibold hover:bg-emerald-700"
                :disabled="processing"
            >
                <Spinner v-if="processing" class="mr-2" />
                {{ processing ? 'Enviando...' : 'Reenviar correo de verificación' }}
            </Button>

            <TextLink
                :href="logout()"
                as="button"
                class="mx-auto block text-sm text-muted-foreground hover:text-foreground"
            >
                Cerrar sesión
            </TextLink>
        </Form>
    </AuthLayout>
</template>
