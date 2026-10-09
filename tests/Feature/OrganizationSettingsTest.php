<?php

use App\Filament\Pages\OrganizationSettings;
use App\Filament\Resources\PlenaryMeetings\Pages\ListPlenaryMeetings;
use App\Models\CouncilOrganization;
use App\Models\ParliamentaryConvocation;
use App\Models\ParliamentarySession;
use App\Models\PlenaryMeeting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates and updates the council organization', function () {
    $component = livewire(OrganizationSettings::class)
        ->assertOk();

    expect($component->instance()->getBreadcrumbs())->toBe([]);

    $component->fillForm([
        'name' => 'Зборівська міська рада',
        'edrpou' => '12345678',
        'katoottg' => 'UA61040090010012345',
    ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Дані організації збережено');

    $organization = CouncilOrganization::query()->firstOrFail();

    $this->assertModelExists($organization);

    $component->fillForm([
        'name' => 'Зборівська міська рада (оновлена)',
        'edrpou' => '12345678',
        'katoottg' => 'UA61040090010012345',
    ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CouncilOrganization::query()->count())->toBe(1)
        ->and($organization->fresh()->name)->toBe('Зборівська міська рада (оновлена)');
});

it('defaults new meetings to the configured council organization', function () {
    $organization = CouncilOrganization::factory()->create([
        'name' => 'Зборівська міська рада',
    ]);
    $convocation = ParliamentaryConvocation::factory()->create();
    $session = ParliamentarySession::factory()
        ->for($convocation, 'convocation')
        ->create();

    $page = livewire(ListPlenaryMeetings::class)
        ->callAction(TestAction::make('create'), [
            'parliamentary_session_id' => $session->id,
            'date' => '2025-06-12',
        ])
        ->assertHasNoFormErrors();

    $meeting = PlenaryMeeting::query()->firstOrFail();

    expect($meeting->organization_id)->toBe($organization->id);
});

it('does not allow non-admin users to access organization settings', function () {
    $this->actingAs(User::factory()->create());

    livewire(OrganizationSettings::class)->assertForbidden();
});
