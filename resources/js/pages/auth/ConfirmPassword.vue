<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';
</script>

<template>
    <AuthLayout
        title="Confirma tu contraseña 🔒"
        description="Esta es un área segura. Confirma tu contraseña para continuar."
    >
        <Head title="Confirmar contraseña" />

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-5">
                <div class="grid gap-1.5">
                    <Label for="password" class="text-sm font-medium">Contraseña</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        class="h-11 rounded-xl"
                        required
                        autocomplete="current-password"
                        autofocus
                        placeholder="••••••••"
                    />
                    <InputError :message="errors.password" />
                </div>

                <Button
                    class="h-11 w-full rounded-xl bg-emerald-600 text-sm font-semibold hover:bg-emerald-700"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Verificando...' : 'Confirmar contraseña' }}
                </Button>
            </div>
        </Form>
    </AuthLayout>
</template>
