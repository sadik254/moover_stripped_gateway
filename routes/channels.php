<?php

use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
|
| Client subscribes to: private-booking.{bookingId}
| Server channel name: booking.{bookingId}
|
*/

Broadcast::channel('booking.{bookingId}', function ($user, $bookingId) {
    return $user instanceof User && in_array((string) $user->user_type, ['admin', 'dispatcher'], true);
});

