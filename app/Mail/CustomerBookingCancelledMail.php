<?php

namespace App\Mail;

use App\Models\Booking;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class CustomerBookingCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Booking $booking,
        public string $previousStatus,
        public bool $isAdminCopy = false,
    ) {
    }

    public function build(): self
    {
        $companyName = $this->booking->company?->name ?? config('app.name', 'Moover');

        return $this
            ->subject($this->isAdminCopy
                ? "Customer cancelled booking #{$this->booking->id} — {$companyName}"
                : "Your booking #{$this->booking->id} has been cancelled")
            ->view('emails.customer_booking_cancelled');
    }
}
