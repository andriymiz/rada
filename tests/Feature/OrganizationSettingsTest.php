<?php

use App\Filament\Pages\OrganizationSettings;
use App\Filament\Resources\PlenaryMeetings\Pages\ListPlenaryMeetings;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
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
        'name' => 'Міська рада',
        'edrpou' => '12345678',
        'katoottg' => 'UA61040090010012345',
        'open_data_id' => '123e4567-e89b-42d3-a456-426614174000',
    ])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Дані організації збережено');

    $organization = CouncilOrganization::query()->firstOrFail();

    $this->assertModelExists($organization);

    $component->fillForm([
        'name' => 'Міська рада (оновлена)',
        'edrpou' => '12345678',
        'katoottg' => 'UA61040090010012345',
        'open_data_id' => '11111111-2222-4333-8444-555555555555',
    ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CouncilOrganization::query()->count())->toBe(1)
        ->and($organization->fresh()->name)->toBe('Міська рада (оновлена)')
        ->and($organization->fresh()->open_data_id)->toBe('11111111-2222-4333-8444-555555555555');
});

it('rejects a non-uuid open data identifier', function (): void {
    livewire(OrganizationSettings::class)
        ->fillForm([
            'name' => 'Міська рада',
            'edrpou' => '12345678',
            'katoottg' => 'UA61040090010012345',
            'open_data_id' => 'not-a-uuid',
        ])
        ->call('save')
        ->assertHasFormErrors(['open_data_id' => 'uuid']);

    expect(CouncilOrganization::query()->exists())->toBeFalse();
});

it('creates meetings under the organization attached to their convocation', function () {
    $organization = CouncilOrganization::factory()->create([
        'name' => 'Міська рада',
    ]);
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $session = CouncilSession::factory()
        ->for($convocation, 'convocation')
        ->create();

    $page = livewire(ListPlenaryMeetings::class)
        ->callAction(TestAction::make('create'), [
            'council_session_id' => $session->id,
            'date' => '2025-06-12',
        ])
        ->assertHasNoFormErrors();

    $meeting = PlenaryMeeting::query()->firstOrFail();

    expect($meeting->councilSession->convocation->organization->is($organization))->toBeTrue();
});

it('does not allow non-admin users to access organization settings', function () {
    $this->actingAs(User::factory()->create());

    livewire(OrganizationSettings::class)->assertForbidden();
});
