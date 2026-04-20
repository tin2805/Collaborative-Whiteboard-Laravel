<?php

namespace App\Events;

use App\Models\Room;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class CanvasObjectDeleted implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $objectId;

    public function __construct(Room $room, string $objectId)
    {
        $this->roomId = $room->id;
        $this->objectId = $objectId;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('rooms.' . $this->roomId),
        ];
    }
}
