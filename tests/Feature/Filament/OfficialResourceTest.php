<?php

use App\Filament\Resources\Officials\OfficialResource;
use App\Filament\Resources\Officials\Pages\CreateOfficial;
use App\Filament\Resources\Officials\Pages\EditOfficial;
use App\Filament\Resources\Officials\Pages\ListOfficials;
use App\Models\ContactPoint;
use App\Models\CouncilOrganization;
use App\Models\Official;
use App\Models\Person;
use App\Models\Reception;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Schema;

use function Pest\Livewire\livewire;

beforeEach(function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());
});

it('creates an official with a person, organization and contact points', function () {
    $person = Person::factory()->create();
    $organization = CouncilOrganization::factory()->create();

    livewire(CreateOfficial::class)
        ->fillForm([
            'person_id' => $person->id,
            'organization_id' => $organization->id,
            'position' => 'Міський голова',
            'image' => 'https://example.com/photo.jpg',
            'homepage' => 'https://example.com/official',
            'contactPoints' => [
                [
                    'type' => 'email',
                    'value' => 'official@example.com',
                    'is_public' => true,
                    'note' => 'Робоча пошта',
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $official = Official::query()->where('position', 'Міський голова')->firstOrFail();

    expect($official->person->is($person))->toBeTrue()
        ->and($official->organization->is($organization))->toBeTrue()
        ->and($official->contactPoints)->toHaveCount(1)
        ->and($official->contactPoints->first()->value)->toBe('official@example.com');

    $this->assertDatabaseHas(ContactPoint::class, [
        'contactable_type' => $official->getMorphClass(),
        'contactable_id' => $official->id,
        'type' => 'email',
        'value' => 'official@example.com',
    ]);
});

it('shows the ukrainian officials resource and its list', function () {
    $official = Official::factory()->create(['position' => 'Секретар']);

    livewire(ListOfficials::class)
        ->assertCanSeeTableRecords([$official])
        ->assertSee('Посадовці')
        ->assertTableColumnStateSet('position', 'Секретар', $official);

    $this->get(OfficialResource::getUrl())
        ->assertOk()
        ->assertSee('Посадовці');
});

it('links a reception location to an official', function () {
    $official = Official::factory()->create();

    livewire(EditOfficial::class, ['record' => $official->id])
        ->callFormComponentAction('reception-add-section', 'addReception', [
            'katoottg' => 'UA12345678901234567',
            'address_post_code' => '47000',
            'address_admin_unit_l1' => 'Україна',
            'address_admin_unit_l2' => 'Тернопільська область',
            'address_post_name' => 'м. Зборів',
            'address_thoroughfare' => 'вул. Шевченка',
            'address_locator_designator' => '1',
            'opening_hours' => 'Пн 09:00-12:00',
        ])
        ->assertHasNoFormComponentActionErrors();

    expect($official->refresh()->reception->address_post_name)->toBe('м. Зборів')
        ->and($official->reception->receptionable->is($official))->toBeTrue();

    $this->assertDatabaseHas(Reception::class, [
        'receptionable_type' => $official->getMorphClass(),
        'receptionable_id' => $official->id,
        'address_post_name' => 'м. Зборів',
    ]);

    expect(Schema::hasTable('receptions'))->toBeTrue()
        ->and(Schema::hasTable('deputy_receptions'))->toBeFalse()
        ->and(Schema::hasTable('official_receptions'))->toBeFalse();
});
