<?php

use App\Filament\Resources\Convocations\ConvocationResource;
use App\Filament\Resources\Convocations\Pages\EditConvocation;
use App\Filament\Resources\Convocations\Pages\ListConvocations;
use App\Filament\Resources\Convocations\RelationManagers\DeputiesRelationManager;
use App\Models\Convocation;
use App\Models\CouncilOrganization;
use App\Models\CouncilSession;
use App\Models\Deputy;
use App\Models\Faction;
use App\Models\PermanentCommission;
use App\Models\Person;
use App\Models\PoliticalParty;
use App\Models\User;
use Database\Seeders\ConvocationSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;

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

    $page = livewire(ListConvocations::class)
        ->callAction(TestAction::make('create'), [
            'label' => 'Дев’яте скликання',
            'date_from' => '2020-01-01',
        ])
        ->assertHasNoFormErrors();

    $convocation = Convocation::query()->where('label', 'Дев’яте скликання')->firstOrFail();
    $page->assertRedirect(ConvocationResource::getUrl('edit', ['record' => $convocation]));
    $previousConvocation->refresh();

    expect($convocation->organization->is($firstOrganization))->toBeTrue()
        ->and($convocation->date_from?->toDateString())->toBe('2020-01-01')
        ->and($convocation->date_until)->toBeNull()
        ->and($previousConvocation->date_until)->toBeNull();
});

it('updates a convocation date range from its edit page', function () {
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

    livewire(EditConvocation::class, ['record' => $convocation->id])
        ->fillForm([
            'label' => $convocation->label,
            'date_from' => '2021-03-15',
            'date_until' => '2025-03-14',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $convocation->refresh();
    $previousConvocation->refresh();

    expect($convocation->date_from?->toDateString())->toBe('2021-03-15')
        ->and($convocation->date_until?->toDateString())->toBe('2025-03-14')
        ->and($previousConvocation->date_until)->toBeNull();
});

it('deletes a convocation that has no related records', function () {
    $convocation = Convocation::factory()->create();

    livewire(ListConvocations::class)
        ->assertTableActionExists('delete', record: $convocation)
        ->callTableAction('delete', $convocation);

    $this->assertDatabaseMissing('convocations', ['id' => $convocation->id]);
});

it('shows a failure notification and preserves a convocation that has related records', function () {
    $convocation = Convocation::factory()->create();
    Deputy::factory()->for($convocation, 'convocation')->create();
    CouncilSession::factory()->for($convocation, 'convocation')->create();
    PermanentCommission::factory()->for($convocation, 'convocation')->create();
    Faction::factory()->for($convocation, 'convocation')->create();

    livewire(ListConvocations::class)
        ->assertTableActionVisible('delete', $convocation)
        ->callTableAction('delete', $convocation)
        ->assertNotified('Спочатку видаліть пов’язані записи: депутати, сесії, постійні комісії, фракції.');

    $this->assertModelExists($convocation);
});

it('lists only the convocation deputies on its edit page with links to the nested deputy pages', function () {
    $convocation = Convocation::factory()->create();
    $party = PoliticalParty::factory()->create(['name' => 'Тестова партія']);
    $deputy = Deputy::factory()->for($convocation, 'convocation')->create(['political_party_id' => $party->id]);
    $deputy->update(['voting_identifier' => 'COUNCIL-007']);
    $otherDeputy = Deputy::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->assertCanSeeTableRecords([$deputy])
        ->assertCanNotSeeTableRecords([$otherDeputy])
        ->assertTableColumnHidden('convocation.label')
        ->assertTableColumnStateSet('politicalParty.name', 'Тестова партія', $deputy)
        ->assertTableColumnStateSet('voting_identifier', 'COUNCIL-007', $deputy)
        ->assertSee('Номер пульта')
        ->assertTableColumnStateSet('status', 'Активний', $deputy)
        ->assertTableActionExists('edit', record: $deputy)
        ->assertTableActionExists('delete', record: $deputy)
        ->assertTableActionExists('create');

    $this->get(ConvocationResource::getUrl('edit', ['record' => $convocation]))
        ->assertOk()
        ->assertSeeLivewire(DeputiesRelationManager::class);
});

it('sorts convocation deputies by their voting console number', function () {
    $convocation = Convocation::factory()->create();
    $laterNumber = Deputy::factory()
        ->for($convocation, 'convocation')
        ->create(['voting_identifier' => 'PULT-002']);
    $earlierNumber = Deputy::factory()
        ->for($convocation, 'convocation')
        ->create(['voting_identifier' => 'PULT-001']);

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->sortTable('voting_identifier', 'asc')
        ->assertCanSeeTableRecords([$earlierNumber, $laterNumber], inOrder: true);
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

it('opens the convocation edit page without a separate deputies page action', function () {
    $convocation = Convocation::factory()->create();
    $editUrl = ConvocationResource::getUrl('edit', ['record' => $convocation]);

    livewire(ListConvocations::class)
        ->assertTableActionDoesNotExist('deputies', record: $convocation)
        ->assertTableActionHasUrl('edit', $editUrl, $convocation)
        ->assertDontSee('deputies_count');

    $this->get($editUrl)->assertOk();
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

it('allows employees to access the convocation page with its deputies relation manager', function () {
    $convocation = Convocation::factory()->create();
    $this->actingAs(User::factory()->create());

    $this->get(ConvocationResource::getUrl('edit', ['record' => $convocation]))
        ->assertOk();
});

it('shows only deputies belonging to the selected convocation in its relation manager', function () {
    $convocation = Convocation::factory()->create();
    $otherConvocation = Convocation::factory()->create();
    $deputy = Deputy::factory()->for($convocation, 'convocation')->create();
    $otherDeputy = Deputy::factory()->for($otherConvocation, 'convocation')->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])
        ->assertCanSeeTableRecords([$deputy])
        ->assertCanNotSeeTableRecords([$otherDeputy]);
});

it('creates a deputy through the selected convocation relationship', function () {
    $convocation = Convocation::factory()->create();
    $person = Person::factory()->create();
    $party = PoliticalParty::factory()->create();

    livewire(DeputiesRelationManager::class, [
        'ownerRecord' => $convocation,
        'pageClass' => EditConvocation::class,
    ])->callTableAction('create', data: [
        'person_id' => $person->id,
        'political_party_id' => $party->id,
        'biography' => 'Біографія депутата.',
    ])
        ->assertHasNoFormErrors();

    $deputy = Deputy::query()->where('person_id', $person->id)->firstOrFail();

    expect($deputy->convocation->is($convocation))->toBeTrue()
        ->and($deputy->politicalParty->is($party))->toBeTrue();
});

it('does not expose a separate deputy edit page', function () {
    $convocation = Convocation::factory()->create();
    $otherConvocation = Convocation::factory()->create();
    $deputy = Deputy::factory()->for($otherConvocation, 'convocation')->create();

    $this->get("/panel/convocations/{$convocation->id}/deputies/{$deputy->id}/edit")
        ->assertNotFound();
});
