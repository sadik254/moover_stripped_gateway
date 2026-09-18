<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingDriverAssignedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking) {}

    public function build(): self
    {
        return $this
            ->subject("Driver assigned for booking #{$this->booking->id}")
            ->view('emails.booking_driver_assigned');
    }
}
