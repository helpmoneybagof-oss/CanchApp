<?php

use Illuminate\Support\Facades\Broadcast;

// Canal privado por usuario (notificaciones de pago al cliente)
Broadcast::channel('user.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Canal privado para administradores
Broadcast::channel('admin', function ($user) {
    return $user->isAdmin();
});

// Canal privado de notificaciones por usuario (campana)
Broadcast::channel('notifications.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});
