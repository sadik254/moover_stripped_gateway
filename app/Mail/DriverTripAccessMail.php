<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DriverTripAccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $driverUrl) {}

    public function build(): self
    {
        return $this->subject("Trip control link for booking #{$this->booking->id}")
            ->view('emails.driver_trip_access');
    }
}
