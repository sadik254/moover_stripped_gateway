<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingStatusChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public string $previousStatus) {}

    public function build(): self
    {
        return $this
            ->subject("Booking #{$this->booking->id} status: ".str_replace('_', ' ', $this->booking->status))
            ->view('emails.booking_status_changed');
    }
}
