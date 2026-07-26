<?php

namespace App\Mail;

use App\Models\Company;
use App\Models\SystemConfig;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DispatcherCreatedPasswordMail extends Mailable
{
    use Queueable, SerializesModels;

    public User $dispatcher;
    public string $generatedPassword;
    public string $platformName;
    public ?string $companyEmail;
    public ?string $companyPhone;
    public ?string $companyAddress;
    public ?string $companyLogo;

    public function __construct(User $dispatcher, string $generatedPassword)
    {
        $company = Company::first();

        $this->dispatcher = $dispatcher;
        $this->generatedPassword = $generatedPassword;
        $this->platformName = (string) (SystemConfig::query()->value('platform_name') ?: 'Moover');
        $this->companyEmail = $company?->email;
        $this->companyPhone = $company?->phone;
        $this->companyAddress = $company?->address;
        $this->companyLogo = $company?->logo;
    }

    public function build(): self
    {
        return $this
            ->subject("Your {$this->platformName} dispatcher account credentials")
            ->view('emails.dispatcher_created_password');
    }
}
