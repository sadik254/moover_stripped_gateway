<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class CreateAdminUser extends Command
{
    protected $signature = 'user:create-admin
                            {name? : The admin\'s full name}
                            {email? : The admin\'s email address}
                            {--password= : The admin password (omit to enter it securely)}';

    protected $description = 'Create an administrator account without an existing admin session';

    public function handle(): int
    {
        $name = $this->argument('name') ?: $this->ask('Admin name');
        $email = $this->argument('email') ?: $this->ask('Admin email');
        $password = $this->option('password') ?: $this->secret('Admin password (minimum 6 characters)');

        $validator = Validator::make([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
        ]);

        if ($validator->fails()) {
            $this->components->error($validator->errors()->first());

            return self::FAILURE;
        }

        $admin = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'user_type' => 'admin',
        ]);

        $this->components->info("Admin created: {$admin->email} (ID: {$admin->id})");

        return self::SUCCESS;
    }
}
