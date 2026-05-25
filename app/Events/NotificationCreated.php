<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NotificationCreated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly int    $userId,
        public readonly string $id,
        public readonly string $type,
        public readonly string $title,
        public readonly string $body,
        public readonly string $icon,
        public readonly ?string $url,
        public readonly int    $unreadCount,
    ) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("notifications.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'notification.created';
    }

    public function broadcastWith(): array
    {
        return [
            'id'          => $this->id,
            'type'        => $this->type,
            'title'       => $this->title,
            'body'        => $this->body,
            'icon'        => $this->icon,
            'url'         => $this->url,
            'unreadCount' => $this->unreadCount,
            'createdAt'   => now()->toISOString(),
        ];
    }
}
