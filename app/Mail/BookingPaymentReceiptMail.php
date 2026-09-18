<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\BookingPayment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingPaymentReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Booking $booking, public BookingPayment $payment, public string $receiptUrl) {}

    public function build(): self
    {
        return $this->subject("Payment receipt for booking #{$this->booking->id}")
            ->view('emails.booking_payment_receipt');
    }
}
