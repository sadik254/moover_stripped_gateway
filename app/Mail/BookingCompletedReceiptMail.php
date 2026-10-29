<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingCompletedReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $receiptUrl,
    ) {
    }

    public function build(): self
    {
        return $this
            ->subject("Trip completed — booking #{$this->booking->id} receipt")
            ->view('emails.booking_completed_receipt');
    }
}
