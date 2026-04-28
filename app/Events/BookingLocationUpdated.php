<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class BookingLocationUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public int $bookingId;
    public array $location;

    public function __construct(int $bookingId, array $location)
    {
        $this->bookingId = $bookingId;
        $this->location = $location;
    }

    public function broadcastOn(): array
    {
        return [new PrivateChannel('booking.' . $this->bookingId)];
    }

    public function broadcastAs(): string
    {
        return 'booking.location.updated';
    }
}

