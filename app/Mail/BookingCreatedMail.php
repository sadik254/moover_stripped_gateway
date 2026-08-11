<?php

namespace App\Mail;

use App\Models\Booking;
use App\Models\Company;
use App\Models\SystemConfig;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BookingCreatedMail extends Mailable
{
    use Queueable, SerializesModels;

    public Booking $booking;
    public bool $isAdminCopy;
    public string $platformName;
    public ?string $companyEmail;
    public ?string $companyPhone;
    public ?string $companyAddress;
    public ?string $companyLogo;
    public string $pickupTime;
    public string $serviceType;

    public function __construct(Booking $booking, bool $isAdminCopy = false)
    {
        $booking->loadMissing(['company', 'customer', 'vehicle', 'driver']);
        $company = $booking->company ?? Company::first();

        $this->booking = $booking;
        $this->isAdminCopy = $isAdminCopy;
        $this->platformName = (string) (
            SystemConfig::query()->where('company_id', $company?->id)->value('platform_name')
            ?: $company?->name
            ?: 'Moover'
        );
        $this->companyEmail = $company?->email;
        $this->companyPhone = $company?->phone;
        $this->companyAddress = $company?->address;
        $this->companyLogo = $company?->logo;
        $this->pickupTime = Carbon::parse($booking->pickup_time)->format('D, d M Y \a\t h:i A');
        $this->serviceType = str_replace('_', ' ', (string) $booking->service_type);
    }

    public function build(): self
    {
        $subject = $this->isAdminCopy
            ? "New booking #{$this->booking->id} for {$this->platformName}"
            : "Your {$this->platformName} booking request #{$this->booking->id}";

        return $this
            ->subject($subject)
            ->view('emails.booking_created');
    }
}
