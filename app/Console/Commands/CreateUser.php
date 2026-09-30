<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class CreateUser extends Command
{
    protected $signature = 'rada:user:create';

    protected $description = 'Create an active RADA user account';

    public function handle(): int
    {
        $name = $this->ask('User name');
        $email = $this->ask('Email address');

        if (! is_string($name) || trim($name) === '' || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Enter a name and a valid email address.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('A user with this email address already exists.');

            return self::FAILURE;
        }

        $password = $this->secret('Password (at least 12 characters)');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('The password must contain at least 12 characters.');

            return self::FAILURE;
        }

        User::create([
            'name' => trim($name),
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->info('User account created.');

        return self::SUCCESS;
    }
}
