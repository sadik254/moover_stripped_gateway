<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingTrackingStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $trackingUrl) {}

    public function build(): self
    {
        return $this->subject("Track your driver for booking #{$this->booking->id}")
            ->view('emails.booking_tracking_started');
    }
}
