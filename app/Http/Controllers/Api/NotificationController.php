<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /** GET /api/notifications — Lista de notificaciones del usuario autenticado */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        $notifications = $user->notifications()
            ->latest()
            ->limit(50)
            ->get()
            ->map(fn ($n) => $this->format($n));

        return response()->json([
            'notifications' => $notifications,
            'unreadCount' => $user->unreadNotifications()->count(),
        ]);
    }

    /** POST /api/notifications/{id}/read — Marcar una como leída */
    public function markRead(string $id): JsonResponse
    {
        $notification = Auth::user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    /** POST /api/notifications/read-all — Marcar todas como leídas */
    public function markAllRead(): JsonResponse
    {
        Auth::user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unreadCount' => 0]);
    }

    /** DELETE /api/notifications/{id} — Eliminar una notificación */
    public function destroy(string $id): JsonResponse
    {
        Auth::user()->notifications()->findOrFail($id)->delete();

        return response()->json([
            'unreadCount' => Auth::user()->unreadNotifications()->count(),
        ]);
    }

    private function format($n): array
    {
        return [
            'id' => $n->id,
            'type' => $n->data['type'] ?? 'info',
            'title' => $n->data['title'] ?? '',
            'body' => $n->data['body'] ?? '',
            'icon' => $n->data['icon'] ?? '🔔',
            'url' => $n->data['url'] ?? null,
            'readAt' => $n->read_at?->toISOString(),
            'createdAt' => $n->created_at->toISOString(),
        ];
    }
}
