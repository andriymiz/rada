<?php

use App\Enums\UserRole;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Illuminate\Support\Facades\Hash;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->admin = User::factory()->admin()->create();
    $this->actingAs($this->admin);
});

it('lists users', function () {
    $users = User::factory()->count(3)->create();

    livewire(ListUsers::class)->assertCanSeeTableRecords($users);
});

it('creates a user with a role', function () {
    livewire(CreateUser::class)
        ->fillForm([
            'name' => 'Іван',
            'email' => 'ivan@example.com',
            'password' => 'secret-pass',
            'role' => UserRole::Employee,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('email', 'ivan@example.com')->firstOrFail();
    expect($user->role)->toBe(UserRole::Employee)
        ->and(Hash::check('secret-pass', $user->password))->toBeTrue();
});

it('validates required fields and unique email', function () {
    livewire(CreateUser::class)
        ->fillForm(['name' => null, 'email' => $this->admin->email, 'password' => null, 'role' => null])
        ->call('create')
        ->assertHasFormErrors(['name' => 'required', 'email' => 'unique', 'password' => 'required', 'role' => 'required']);
});

it('views a user', function () {
    $user = User::factory()->create();

    $page = livewire(ViewUser::class, ['record' => $user->id])->assertOk();

    expect(array_values($page->instance()->getBreadcrumbs()))
        ->toBe(['Налаштування', 'Користувачі']);
});

it('displays timestamps in Kyiv time while keeping them stored in UTC', function () {
    $user = User::factory()->create([
        'created_at' => '2026-10-24 21:30:00',
        'updated_at' => '2026-10-25 02:30:00',
    ]);

    livewire(ViewUser::class, ['record' => $user->id])
        ->assertSee('25.10.2026 00:30')
        ->assertSee('25.10.2026 04:30');

    $table = livewire(ListUsers::class)->instance()->getTable();
    $createdAtColumn = $table->getColumn('created_at')->record($user);

    expect($createdAtColumn->formatState($createdAtColumn->getState()))
        ->toBe('25.10.2026 00:30');

    expect(DatePicker::make('date')->getDisplayFormat())
        ->toBe('d.m.Y');

    expect(DateTimePicker::make('date_time')->getDisplayFormat())
        ->toBe('d.m.Y H:i:s');

    expect(TimePicker::make('time')->getDisplayFormat())
        ->toBe('H:i:s');

    $this->assertDatabaseHas('users', [
        'id' => $user->id,
        'created_at' => '2026-10-24 21:30:00',
        'updated_at' => '2026-10-25 02:30:00',
    ]);
});

it('edits a user and keeps the password when left blank', function () {
    $user = User::factory()->create();
    $hash = $user->password;

    livewire(EditUser::class, ['record' => $user->id])
        ->fillForm(['name' => 'Новий', 'role' => UserRole::Admin, 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();
    expect($user->name)->toBe('Новий')
        ->and($user->role)->toBe(UserRole::Admin)
        ->and($user->password)->toBe($hash);
});

it('deletes a user', function () {
    $user = User::factory()->create();

    livewire(EditUser::class, ['record' => $user->id])->callAction(DeleteAction::class);

    $this->assertModelMissing($user);
});

it('filters users by role', function () {
    $employee = User::factory()->create();

    livewire(ListUsers::class)
        ->filterTable('role', UserRole::Employee->value)
        ->assertCanSeeTableRecords([$employee])
        ->assertCanNotSeeTableRecords([$this->admin]);
});

it('denies employees access to user management', function () {
    $this->actingAs(User::factory()->create());

    livewire(ListUsers::class)->assertForbidden();
});
