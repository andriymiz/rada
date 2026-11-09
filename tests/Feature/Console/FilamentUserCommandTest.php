<?php

use App\Enums\UserRole;
use App\Models\User;

use function Pest\Laravel\artisan;

it('creates the first user as an admin and later users as employees', function () {
    artisan('filament:user', [
        '--name' => 'First User',
        '--email' => 'first@example.com',
        '--password' => 'secret-password',
        '--panel' => 'rada',
    ])->assertExitCode(0);

    $firstUser = User::query()->where('email', 'first@example.com')->firstOrFail();

    artisan('filament:user', [
        '--name' => 'Employee',
        '--email' => 'employee@example.com',
        '--password' => 'secret-password',
        '--panel' => 'rada',
    ])->assertExitCode(0);

    $secondUser = User::query()->where('email', 'employee@example.com')->firstOrFail();

    expect($firstUser->role)->toBe(UserRole::Admin)
        ->and($secondUser->role)->toBe(UserRole::Employee);
});
