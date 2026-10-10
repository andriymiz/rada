<?php

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

it('groups organization and users under settings in the sidebar', function () {
    Filament::setCurrentPanel('rada');
    $this->actingAs(User::factory()->admin()->create());

    $settingsItem = collect(Filament::getNavigation())
        ->flatMap(fn (NavigationGroup $group) => $group->getItems())
        ->first(fn (NavigationItem $item): bool => $item->getLabel() === 'Налаштування');

    $childLabels = collect($settingsItem?->getChildItems() ?? [])
        ->map(fn (NavigationItem $item): string => $item->getLabel())
        ->sort()
        ->values()
        ->all();

    expect($childLabels)->toBe(['Користувачі', 'Організація']);
});
