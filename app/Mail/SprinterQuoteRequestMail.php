<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\SystemConfig;
use App\Models\VehicleClass;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SprinterQuoteRequestMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Company $company,
        public VehicleClass $vehicleClass,
        public array $quoteRequest,
        public bool $isAdminCopy = false,
    ) {
    }

    public function build(): self
    {
        $platformName = (string) (
            SystemConfig::query()->where('company_id', $this->company->id)->value('platform_name')
            ?: $this->company->name
            ?: 'Moover'
        );

        return $this
            ->subject($this->isAdminCopy
                ? "New Sprinter quote request for {$platformName}"
                : "We received your Sprinter request — {$platformName}")
            ->view('emails.sprinter_quote_request', [
                'platformName' => $platformName,
                'companyEmail' => $this->company->email,
                'companyPhone' => $this->company->phone,
                'companyAddress' => $this->company->address,
                'companyLogo' => $this->company->logo,
            ]);
    }
}
