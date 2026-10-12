<?php

use App\Filament\Resources\Convocations\Pages\ListConvocations;
use App\Filament\Resources\Convocations\Resources\Deputies\DeputyResource;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\CreateDeputy;
use App\Filament\Resources\Convocations\Resources\Deputies\Pages\ListDeputies;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Person;
use App\Models\PoliticalParty;
use App\Models\User;
use Database\Seeders\ConvocationSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates a council convocation through its resource', function () {
    $firstOrganization = CouncilOrganization::factory()->create();
    CouncilOrganization::factory()->create();
    $previousConvocation = Convocation::factory()
        ->for($firstOrganization, 'organization')
        ->create(['label' => 'Восьме скликання']);

    livewire(ListConvocations::class)
        ->callAction(TestAction::make('create'), [
            'label' => 'Дев’яте скликання',
            'date_from' => '2020-01-01',
        ])
        ->assertHasNoFormErrors();

    $convocation = Convocation::query()->where('label', 'Дев’яте скликання')->firstOrFail();
    $previousConvocation->refresh();

    expect($convocation->organization->is($firstOrganization))->toBeTrue()
        ->and($convocation->date_from?->toDateString())->toBe('2020-01-01')
        ->and($convocation->date_until)->toBeNull()
        ->and($previousConvocation->date_until?->toDateString())->toBe('2019-12-31');
});

it('updates the previous convocation end date when the start date is edited', function () {
    $organization = CouncilOrganization::factory()->create();
    $previousConvocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create(['label' => 'Восьме скликання']);
    $convocation = Convocation::factory()
        ->for($organization, 'organization')
        ->create([
            'label' => 'Дев’яте скликання',
            'date_from' => '2020-01-01',
        ]);

    livewire(ListConvocations::class)
        ->callAction(TestAction::make('edit')->table($convocation), [
            'label' => $convocation->label,
            'date_from' => '2021-03-15',
        ])
        ->assertHasNoFormErrors();

    $previousConvocation->refresh();

    expect($previousConvocation->date_until?->toDateString())->toBe('2021-03-14');
});

it('seeds the first seven convocations and their council organization idempotently', function () {
    $this->seed(ConvocationSeeder::class);
    $this->seed(ConvocationSeeder::class);

    $organization = CouncilOrganization::query()->where('edrpou', 'TEST0001')->firstOrFail();

    $this->assertDatabaseCount('council_organizations', 1);
    $this->assertDatabaseCount('convocations', 7);

    expect($organization->convocations()->orderBy('id')->pluck('label')->all())
        ->toBe([
            'Перше скликання',
            'Друге скликання',
            'Третє скликання',
            'Четверте скликання',
            'П’яте скликання',
            'Шосте скликання',
            'Сьоме скликання',
        ]);
});

it('links to the nested deputies page from the convocation list', function () {
    $convocation = Convocation::factory()->create();
    $deputiesUrl = DeputyResource::getUrl(parameters: ['convocation' => $convocation]);

    livewire(ListConvocations::class)
        ->assertTableActionHasUrl('deputies', $deputiesUrl, $convocation)
        ->assertTableActionHasIcon('deputies', Heroicon::OutlinedUsers, $convocation)
        ->assertDontSee('deputies_count');

    expect(parse_url($deputiesUrl, PHP_URL_PATH))
        ->toBe("/panel/convocations/{$convocation->id}/deputies");
});

it('shows session counts without displaying a deputy count column', function () {
    $convocation = Convocation::factory()->create();
    $otherConvocation = Convocation::factory()->create();

    CouncilSession::factory()->count(3)->for($convocation, 'convocation')->create();
    CouncilSession::factory()->for($otherConvocation, 'convocation')->create();

    livewire(ListConvocations::class)
        ->assertTableColumnStateSet('sessions_count', 3, $convocation)
        ->assertTableColumnStateSet('sessions_count', 1, $otherConvocation)
        ->assertTableColumnDoesNotExist('deputies_count')
        ->assertDontSee('Організація ради');
});

it('shows the database identifier for each convocation', function () {
    $convocation = Convocation::factory()->create(['id' => 42]);

    livewire(ListConvocations::class)
        ->assertTableColumnStateSet('id', 42, $convocation);
});

it('lists newer convocations before older ones by default', function () {
    $olderConvocation = Convocation::factory()->create(['id' => 41]);
    $newerConvocation = Convocation::factory()->create(['id' => 42]);

    livewire(ListConvocations::class)
        ->assertCanSeeTableRecords([$newerConvocation, $olderConvocation], inOrder: true);
});

it('shows the date range for each convocation', function () {
    $convocation = Convocation::factory()->create([
        'date_from' => '2020-01-01',
        'date_until' => '2020-12-31',
    ]);

    livewire(ListConvocations::class)
        ->assertSee('01.01.2020 - 31.12.2020')
        ->assertDontSee('Дата початку')
        ->assertDontSee('Дата закінчення');
});

it('allows employees to access the nested deputies page', function () {
    $convocation = Convocation::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(DeputyResource::getUrl(parameters: ['convocation' => $convocation]))
        ->assertOk();
});

it('shows only deputies belonging to the selected convocation', function () {
    $convocation = Convocation::factory()->create();
    $otherConvocation = Convocation::factory()->create();
    $deputy = Deputy::factory()->for($convocation, 'convocation')->create();
    $otherDeputy = Deputy::factory()->for($otherConvocation, 'convocation')->create();

    livewire(ListDeputies::class, ['parentRecord' => $convocation])
        ->assertCanSeeTableRecords([$deputy])
        ->assertCanNotSeeTableRecords([$otherDeputy]);
});

it('creates a deputy through the selected convocation relationship', function () {
    $convocation = Convocation::factory()->create();
    $person = Person::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(CreateDeputy::class, ['parentRecord' => $convocation])
        ->fillForm([
            'person_id' => $person->id,
            'political_party_id' => $party->id,
            'biography' => 'Біографія депутата.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $deputy = Deputy::query()->where('person_id', $person->id)->firstOrFail();

    expect($deputy->convocation->is($convocation))->toBeTrue()
        ->and($deputy->politicalParty->is($party))->toBeTrue();
});

it('does not allow editing a deputy through a different convocation URL', function () {
    $convocation = Convocation::factory()->create();
    $otherConvocation = Convocation::factory()->create();
    $deputy = Deputy::factory()->for($otherConvocation, 'convocation')->create();
    $validUrl = DeputyResource::getUrl('edit', [
        'convocation' => $otherConvocation->id,
        'record' => $deputy->id,
    ]);
    $url = DeputyResource::getUrl('edit', [
        'convocation' => $convocation->id,
        'record' => $deputy->id,
    ]);

    $this->get($validUrl)->assertOk();
    $this->get($url)->assertNotFound();
});
