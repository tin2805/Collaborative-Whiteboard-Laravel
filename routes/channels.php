<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

Broadcast::channel('rooms.{roomId}', function ($user, $roomId) {
    // Basic check: if room is public or user is assigned to it
    $room = \App\Models\Room::find($roomId);
    if (!$room) return false;
    
    return $room->is_public || $room->users()->where('user_id', $user->id)->exists();
});
