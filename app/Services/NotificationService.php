<?php

namespace App\Services;

use App\Events\NotificationCreated;
use App\Models\User;
use Illuminate\Notifications\Notification;

class NotificationService
{
    /**
     * Envía una notificación a un usuario y hace broadcast en tiempo real.
     */
    public function notifyUser(User $user, Notification $notification): void
    {
        // Guardar directamente en BD sin queue para poder hacer broadcast inmediato
        $data = $notification->toArray($user);
        $id   = (string) \Illuminate\Support\Str::uuid();

        \DB::table('notifications')->insert([
            'id'              => $id,
            'type'            => get_class($notification),
            'notifiable_type' => get_class($user),
            'notifiable_id'   => $user->id,
            'data'            => json_encode($data),
            'read_at'         => null,
            'created_at'      => now(),
            'updated_at'      => now(),
        ]);

        $unreadCount = \DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', get_class($user))
            ->whereNull('read_at')
            ->count();

        broadcast(new NotificationCreated(
            userId:      $user->id,
            id:          $id,
            type:        $data['type']  ?? 'info',
            title:       $data['title'] ?? '',
            body:        $data['body']  ?? '',
            icon:        $data['icon']  ?? '🔔',
            url:         $data['url']   ?? null,
            unreadCount: $unreadCount,
        ));
    }

    /**
     * Envía una notificación a todos los administradores.
     */
    public function notifyAdmins(Notification $notification): void
    {
        $admins = User::where('role', 'admin')->get();
        foreach ($admins as $admin) {
            $this->notifyUser($admin, $notification);
        }
    }
}
