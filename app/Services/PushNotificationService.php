<?php

namespace App\Services;

use App\Models\PushSubscription;
use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class PushNotificationService
{
    private ?WebPush $webPush = null;

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
            $this->webPush = new WebPush([
                'VAPID' => [
                    'subject'    => config('app.url'),
                    'publicKey'  => $publicKey,
                    'privateKey' => $privateKey,
                ],
            ]);
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
                Log::warning("Push send error: {$report->getReason()} endpoint: {$report->getEndpoint()}");
                // Si el endpoint ya no existe, eliminarlo
                if ($report->isSubscriptionExpired()) {
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
