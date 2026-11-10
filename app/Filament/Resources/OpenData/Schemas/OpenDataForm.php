<?php

namespace App\Filament\Resources\OpenData\Schemas;

use App\Enums\OpenDataResourceCode;
use App\Models\OpenDataResource as OpenDataResourceModel;
use App\Services\OpenDataPortal;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class OpenDataForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('code')
                    ->label('Тип ресурсу')
                    ->columnSpanFull()
                    ->options(OpenDataResourceCode::class)
                    ->rule(Rule::in(array_map(
                        static fn (OpenDataResourceCode $code): string => $code->value,
                        OpenDataResourceCode::cases(),
                    )))
                    ->required()
                    ->native(false)
                    ->unique(
                        table: (new OpenDataResourceModel)->getTable(),
                        column: 'code',
                        ignoreRecord: true,
                    ),
                Select::make('package_id')
                    ->label('Набір даних')
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set): void {
                        $set('resource_id', null);
                    })
                    ->getSearchResultsUsing(fn (string $search): array => app(OpenDataPortal::class)
                        ->searchPackages($search))
                    ->getOptionLabelUsing(fn (?string $value): ?string => filled($value)
                        ? app(OpenDataPortal::class)->getPackageLabel($value)
                        : null),
                Select::make('resource_id')
                    ->label('Ресурс')
                    ->disabled(fn (Get $get): bool => blank($get('package_id')))
                    ->required()
                    ->options(function (Get $get): array {
                        $packageId = $get('package_id');

                        return is_string($packageId) && filled($packageId)
                            ? app(OpenDataPortal::class)->searchResources($packageId, '')
                            : [];
                    }),
            ]);
    }
}
