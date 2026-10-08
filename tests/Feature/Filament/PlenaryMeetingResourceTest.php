<?php

use App\Filament\Resources\PlenaryMeetings\Pages\ListPlenaryMeetings;
use App\Filament\Resources\PlenaryMeetings\PlenaryMeetingResource;
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

it('lists plenary meetings with session, convocation, organization, and date', function () {
    $organization = CouncilOrganization::factory()->create(['name' => 'Зборівська міська рада']);
    $convocation = ParliamentaryConvocation::factory()->create(['name' => '8 скликання']);
    $olderSession = ParliamentarySession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '98 сесія']);
    $newerSession = ParliamentarySession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '99 сесія']);
    $olderMeeting = PlenaryMeeting::factory()
        ->for($organization, 'organization')
        ->for($olderSession, 'parliamentarySession')
        ->create(['date' => '2025-05-20']);
    $newerMeeting = PlenaryMeeting::factory()
        ->for($organization, 'organization')
        ->for($newerSession, 'parliamentarySession')
        ->create(['date' => '2025-06-12']);

    $page = livewire(ListPlenaryMeetings::class)
        ->assertCanSeeTableRecords([$newerMeeting, $olderMeeting], inOrder: true)
        ->assertSeeInOrder([
            'Пленарне засідання ради — 99 сесія (8 скликання)',
            '12.06.2025',
            'Зборівська міська рада',
            'Пленарне засідання ради — 98 сесія (8 скликання)',
            '20.05.2025',
        ]);

    expect(parse_url(PlenaryMeetingResource::getUrl(), PHP_URL_PATH))->toBe('/panel/meetings')
        ->and($page->instance()->getBreadcrumbs())->toBe([]);
});

it('creates a plenary meeting from the list action', function () {
    $organization = CouncilOrganization::factory()->create();
    $convocation = ParliamentaryConvocation::factory()->create(['name' => '8 скликання']);
    $session = ParliamentarySession::factory()
        ->for($convocation, 'convocation')
        ->create(['name' => '99 сесія']);

    livewire(ListPlenaryMeetings::class)
        ->callAction(TestAction::make('create'), [
            'organization_id' => $organization->id,
            'parliamentary_session_id' => $session->id,
            'date' => '2025-06-12',
        ])
        ->assertHasNoFormErrors();

    $meeting = PlenaryMeeting::query()->firstOrFail();

    $this->assertModelExists($meeting);

    expect($meeting->organization_id)->toBe($organization->id)
        ->and($meeting->parliamentary_session_id)->toBe($session->id)
        ->and($meeting->date->toDateString())->toBe('2025-06-12');
});
