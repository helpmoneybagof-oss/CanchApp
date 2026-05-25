<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SlotStatusChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $courtId,
        public string $date,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel('slots.'.$this->courtId),
        ];
    }

    public function broadcastAs(): string
    {
        return 'slot.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'court_id' => $this->courtId,
            'date' => $this->date,
        ];
    }
}
