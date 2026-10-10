<?php

use App\Filament\Pages\RadaDashboard;
use App\Models\User;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

it('greets the signed-in user by name on the dashboard', function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->create(['name' => 'Олена Ковальчук']));

    livewire(RadaDashboard::class)
        ->assertSee('Вітаємо, Олена Ковальчук');
});
