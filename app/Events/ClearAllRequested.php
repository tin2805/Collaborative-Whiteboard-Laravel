<?php

namespace App\Events;

use App\Models\Room;
use App\Models\User;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ClearAllRequested implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $roomId;
    public $requesterName;
    public $requesterId;

    public function __construct(Room $room, User $user)
    {
        $this->roomId = $room->id;
        $this->requesterName = $user->name;
        $this->requesterId = $user->id;
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('rooms.' . $this->roomId),
        ];
    }
}
