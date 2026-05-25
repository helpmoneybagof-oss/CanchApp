<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
}>();
</script>

<template>
    <AuthBase
        title="Bienvenido de nuevo 👋"
        description="Ingresa tus credenciales para acceder a tu cuenta"
    >
        <Head title="Iniciar sesión" />

        <div
            v-if="status"
            class="mb-4 rounded-lg bg-emerald-50 px-4 py-3 text-center text-sm font-medium text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-5"
        >
            <div class="grid gap-5">
                <div class="grid gap-1.5">
                    <Label for="email" class="text-sm font-medium">Correo electrónico</Label>
                    <Input
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        placeholder="tu@correo.com"
                        class="h-11 rounded-xl"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-1.5">
                    <div class="flex items-center justify-between">
                        <Label for="password" class="text-sm font-medium">Contraseña</Label>
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-xs text-emerald-600 hover:text-emerald-700"
                            :tabindex="5"
                        >
                            ¿Olvidaste tu contraseña?
                        </TextLink>
                    </div>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        placeholder="••••••••"
                        class="h-11 rounded-xl"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center gap-2.5">
                    <Checkbox id="remember" name="remember" :tabindex="3" />
                    <Label for="remember" class="cursor-pointer text-sm text-muted-foreground">
                        Recordar mi sesión
                    </Label>
                </div>

                <Button
                    type="submit"
                    class="h-11 w-full rounded-xl bg-emerald-600 text-sm font-semibold hover:bg-emerald-700"
                    :tabindex="4"
                    :disabled="processing"
                    data-test="login-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Ingresando...' : 'Iniciar sesión' }}
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                ¿No tienes cuenta?
                <TextLink :href="register()" :tabindex="6" class="font-semibold text-emerald-600 hover:text-emerald-700">
                    Regístrate gratis
                </TextLink>
            </div>
        </Form>
    </AuthBase>
</template>
