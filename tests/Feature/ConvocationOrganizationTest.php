<?php

use App\Filament\Resources\PlenaryMeetings\Pages\ListPlenaryMeetings;
use App\Models\Convocation;
use App\Models\CouncilSession;
use App\Models\Organization;
use App\Models\PlenaryMeeting;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates meetings under the organization attached to their convocation', function () {
    $organization = Organization::factory()->create([
        'name' => 'Міська рада',
    ]);
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create();
    $session = CouncilSession::factory()
        ->for($convocation, 'convocation')
        ->create();

    livewire(ListPlenaryMeetings::class)
        ->callAction(TestAction::make('create'), [
            'council_session_id' => $session->id,
            'date' => '2025-06-12',
        ])
        ->assertHasNoFormErrors();

    $meeting = PlenaryMeeting::query()->firstOrFail();

    expect($meeting->councilSession->convocation->organization->is($organization))->toBeTrue();
});
