<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureMailFromSettings();
        $this->configureNotifications();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Schema::defaultStringLength(191);

        Date::use(CarbonImmutable::class);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Sobreescribe los correos de notificación de Laravel en español.
     */
    protected function configureNotifications(): void
    {
        // Prioriza el nombre guardado en settings por el admin, si no cae al APP_NAME del .env
        try {
            $appName = \App\Models\Setting::getValue('court_name') ?: config('app.name');
        } catch (\Throwable) {
            $appName = config('app.name');
        }

        ResetPassword::toMailUsing(function (mixed $notifiable, string $token) use ($appName): MailMessage {
            $url = url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false));

            $expire = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

            return (new MailMessage)
                ->subject('Restablecer contraseña — ' . $appName)
                ->greeting('¡Hola!')
                ->line('Recibiste este correo porque solicitaste restablecer la contraseña de tu cuenta.')
                ->action('Restablecer contraseña', $url)
                ->line("Este enlace expirará en {$expire} minutos.")
                ->line('Si no solicitaste restablecer tu contraseña, no es necesario hacer nada.')
                ->salutation('Saludos, ' . $appName);
        });

        VerifyEmail::toMailUsing(function (mixed $notifiable, string $url) use ($appName): MailMessage {
            return (new MailMessage)
                ->subject('Verifica tu correo electrónico — ' . $appName)
                ->greeting('¡Bienvenido/a!')
                ->line('Haz clic en el botón de abajo para verificar tu dirección de correo electrónico.')
                ->action('Verificar correo electrónico', $url)
                ->line('Si no creaste una cuenta, no es necesario hacer nada.')
                ->salutation('Saludos, ' . $appName);
        });
    }

    /**
     * Reconfigura el mailer SMTP usando los valores guardados en la tabla settings.
     * Permite que el admin configure el correo desde el panel sin tocar el .env.
     */
    protected function configureMailFromSettings(): void
    {
        // Solo si la tabla settings existe (evita error en migraciones fresh)
        try {
            $host       = \App\Models\Setting::getValue('mail_host');
            $port       = \App\Models\Setting::getValue('mail_port');
            $username   = \App\Models\Setting::getValue('mail_username');
            $password   = \App\Models\Setting::getValue('mail_password');
            $fromAddr   = \App\Models\Setting::getValue('mail_from_address');
            $fromName   = \App\Models\Setting::getValue('mail_from_name');
            $encryption = \App\Models\Setting::getValue('mail_encryption', 'tls');

            if ($host && $port && $username) {
                Config::set('mail.mailers.smtp.host', $host);
                Config::set('mail.mailers.smtp.port', (int) $port);
                Config::set('mail.mailers.smtp.username', $username);
                Config::set('mail.mailers.smtp.password', $password);
                Config::set('mail.mailers.smtp.encryption', $encryption ?: null);
                Config::set('mail.mailers.smtp.scheme', null);
                Config::set('mail.default', 'smtp');
            }

            if ($fromAddr) {
                Config::set('mail.from.address', $fromAddr);
                Config::set('mail.from.name', $fromName ?: config('app.name'));
            }
        } catch (\Throwable) {
            // La tabla settings aún no existe (ej: primera migración). Silencioso.
        }
    }
}
