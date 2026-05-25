<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Guardar o actualizar la suscripción push del usuario autenticado.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
            'p256dh' => 'required|string',
            'auth' => 'required|string',
        ]);

        PushSubscription::updateOrCreate(
            ['endpoint' => $request->endpoint],
            [
                'user_id' => $request->user()->id,
                'p256dh' => $request->p256dh,
                'auth' => $request->auth,
            ]
        );

        return response()->json(['ok' => true]);
    }

    /**
     * Eliminar la suscripción push (cuando el usuario revoca el permiso).
     */
    public function destroy(Request $request): JsonResponse
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        PushSubscription::where('endpoint', $request->endpoint)
            ->where('user_id', $request->user()->id)
            ->delete();

        return response()->json(['ok' => true]);
    }
}
