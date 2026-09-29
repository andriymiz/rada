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
        $name = $this->ask('Ім’я користувача');
        $email = $this->ask('Електронна пошта');

        if (! is_string($name) || trim($name) === '' || ! is_string($email) || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Вкажіть ім’я та коректну адресу електронної пошти.');

            return self::FAILURE;
        }

        if (User::where('email', $email)->exists()) {
            $this->error('Користувач із такою адресою вже існує.');

            return self::FAILURE;
        }

        $password = $this->secret('Пароль (не менше 12 символів)');

        if (! is_string($password) || mb_strlen($password) < 12) {
            $this->error('Пароль має містити щонайменше 12 символів.');

            return self::FAILURE;
        }

        User::create([
            'name' => trim($name),
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        $this->info('Обліковий запис створено.');

        return self::SUCCESS;
    }
}
