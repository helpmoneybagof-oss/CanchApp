<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use App\Services\PushNotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushDebugController extends Controller
{
    /**
     * Devuelve información de estado para diagnosticar Push Notifications.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        $subs = PushSubscription::where('user_id', $user->id)->get(['id', 'endpoint', 'created_at']);

        return response()->json([
            'ok' => true,
            'server' => [
                'app_url' => config('app.url'),
                'vapid_public_key_configured' => (bool) config('app.vapid_public_key'),
                'vapid_private_key_configured' => (bool) config('app.vapid_private_key'),
            ],
            'subscriptions' => [
                'count' => $subs->count(),
                'items' => $subs->map(fn ($s) => [
                    'id' => $s->id,
                    'endpoint' => $s->endpoint,
                    'created_at' => optional($s->created_at)->toISOString(),
                ]),
            ],
        ]);
    }

    /**
     * Envía una notificación push de prueba al usuario autenticado.
     */
    public function test(Request $request, PushNotificationService $push): JsonResponse
    {
        $request->validate([
            'url' => 'nullable|string',
        ]);

        $push->sendToUser(
            userId: $request->user()->id,
            title: '🔔 Notificación de prueba',
            body: 'Si estás viendo esto, las Push Notifications están funcionando.',
            data: ['url' => $request->input('url', '/dashboard')],
        );

        return response()->json(['ok' => true]);
    }
}
