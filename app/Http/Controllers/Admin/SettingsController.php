<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Inertia\Inertia;
use Inertia\Response;

class SettingsController extends Controller
{
    // Claves que gestiona este panel
    private const KEYS = [
        'court_name',
        'court_address',
        'contact_phone',
        'contact_email',
        'court_price_per_hour',
        'nequi_number',
        'payment_expiry_minutes',
        'mail_host',
        'mail_port',
        'mail_username',
        'mail_password',
        'mail_from_address',
        'mail_from_name',
        'mail_encryption',
    ];

    public function index(): Response
    {
        $settings = [];
        foreach (self::KEYS as $key) {
            $settings[$key] = Setting::getValue($key, '');
        }

        return Inertia::render('admin/Settings', [
            'settings' => $settings,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'court_name'              => 'required|string|max:150',
            'court_address'           => 'nullable|string|max:255',
            'contact_phone'           => 'nullable|string|max:30',
            'contact_email'           => 'nullable|email|max:150',
            'court_price_per_hour'    => 'required|numeric|min:0',
            'nequi_number'            => 'nullable|string|max:20',
            'payment_expiry_minutes'  => 'nullable|integer|min:5|max:1440',
            'mail_host'               => 'nullable|string|max:255',
            'mail_port'               => 'nullable|string|max:10',
            'mail_username'           => 'nullable|string|max:255',
            'mail_password'           => 'nullable|string|max:255',
            'mail_from_address'       => 'nullable|email|max:150',
            'mail_from_name'          => 'nullable|string|max:150',
            'mail_encryption'         => 'nullable|string|in:tls,ssl,',
        ]);

        // No sobreescribir la contraseña si viene vacía
        if (empty($data['mail_password'])) {
            unset($data['mail_password']);
        }

        foreach ($data as $key => $value) {
            Setting::setValue($key, $value ?? '');
        }

        // Reconfigurar mailer en runtime con los nuevos valores
        $this->reconfigureMail();

        return redirect()->route('admin.settings')
            ->with('flash', ['type' => 'success', 'message' => 'Configuración guardada exitosamente.']);
    }

    /**
     * Reconfigura el mailer en runtime usando los valores guardados en Settings.
     * Así los Jobs que se encolen después usarán la nueva configuración.
     */
    private function reconfigureMail(): void
    {
        $host       = Setting::getValue('mail_host');
        $port       = Setting::getValue('mail_port');
        $username   = Setting::getValue('mail_username');
        $password   = Setting::getValue('mail_password');
        $fromAddr   = Setting::getValue('mail_from_address');
        $fromName   = Setting::getValue('mail_from_name');
        $encryption = Setting::getValue('mail_encryption', 'tls');

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
    }
}
