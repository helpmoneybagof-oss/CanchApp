<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?WebPush $webPush = null;

    private function resolveCaBundle(): bool|string
    {
        foreach ([
            getenv('CURL_CA_BUNDLE') ?: null,
            ini_get('curl.cainfo') ?: null,
            ini_get('openssl.cafile') ?: null,
            'C:/wamp64/apps/phpmyadmin5.2.3/vendor/composer/ca-bundle/res/cacert.pem',
            base_path('vendor/composer/ca-bundle/res/cacert.pem'),
        ] as $path) {
            if ($path && is_file($path)) {
                return $path;
            }
        }
        return true; // Sistema (funciona en Linux/Mac y en Windows con php.ini configurado)
    }

    public function __construct()
    {
        $publicKey  = config('app.vapid_public_key', '');
        $privateKey = config('app.vapid_private_key', '');

        // Si las claves VAPID no están configuradas o son inválidas, no inicializar WebPush
        // para evitar que crashee toda la app
        if (empty($publicKey) || empty($privateKey)) {
            return;
        }

        try {
            $this->webPush = new WebPush(
                auth: [
                    'VAPID' => [
                        'subject'    => config('app.url'),
                        'publicKey'  => $publicKey,
                        'privateKey' => $privateKey,
                    ],
                ],
                clientOptions: [
                    // Forzamos un CA bundle válido. cURL en Windows ignora la env CURL_CA_BUNDLE,
                    // y WAMP no trae cacert.pem listo, así que cae en error 60 al hablar con FCM.
                    // En Linux/prod el cliente lo deja en true (verify por defecto del sistema).
                    'verify' => $this->resolveCaBundle(),
                ],
            );
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('PushNotificationService: No se pudo inicializar WebPush: ' . $e->getMessage());
            $this->webPush = null;
        }
    }

    /**
     * Enviar notificación push a un usuario específico.
     */
    public function sendToUser(int $userId, string $title, string $body, array $data = []): void
    {
        if (!$this->webPush) return;

        $subscriptions = PushSubscription::where('user_id', $userId)->get();

        if ($subscriptions->isEmpty()) {
            return;
        }

        $payload = json_encode([
            'title' => $title,
            'body'  => $body,
            'icon'  => '/icons/pwa-192.png',
            'badge' => '/icons/pwa-96.png',
            'data'  => $data,
        ]);

        foreach ($subscriptions as $sub) {
            try {
                $this->webPush->queueNotification(
                    Subscription::create([
                        'endpoint'        => $sub->endpoint,
                        'keys' => [
                            'p256dh' => $sub->p256dh,
                            'auth'   => $sub->auth,
                        ],
                    ]),
                    $payload
                );
            } catch (\Throwable $e) {
                Log::warning("Push queue error para user #{$userId}: {$e->getMessage()}");
            }
        }

        foreach ($this->webPush->flush() as $report) {
            if (! $report->isSuccess()) {
                $reason = $report->getReason();
                Log::warning("Push send error: {$reason} endpoint: {$report->getEndpoint()}");

                // Eliminar el endpoint si:
                //  - WebPush lo marca como expired (404/410 detectado por la librería), o
                //  - Guzzle lanzó un HTTP exception 404/410 (el reason contiene el código),
                //  - El reason indica "Gone" o "expired".
                $isGone = $report->isSubscriptionExpired()
                    || str_contains($reason, '410 Gone')
                    || str_contains($reason, '404 Not Found')
                    || stripos($reason, 'expired') !== false;

                if ($isGone) {
                    PushSubscription::where('endpoint', $report->getEndpoint())->delete();
                }
            }
        }
    }

    /**
     * Enviar notificación push a todos los admins.
     */
    public function sendToAdmins(string $title, string $body, array $data = []): void
    {
        $adminIds = \App\Models\User::where('role', 'admin')->pluck('id');

        foreach ($adminIds as $adminId) {
            $this->sendToUser($adminId, $title, $body, $data);
        }
    }
}
