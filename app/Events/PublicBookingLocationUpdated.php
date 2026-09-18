<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class PublicBookingLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public string $channelKey, public array $location) {}

    public function broadcastOn(): array
    {
        return [new Channel('trip.'.$this->channelKey)];
    }

    public function broadcastAs(): string
    {
        return 'booking.location.updated';
    }
}
