<?php

namespace Database\Seeders;

use App\Enums\DatasetRecommendation;
use App\Models\CouncilOrganization;
use App\Models\Dataset;
use App\Models\DatasetGroup;
use Illuminate\Database\Seeder;

class DatasetSeeder extends Seeder
{
    /**
     * @var array<string, list<DatasetRecommendation>>
     */
    private const RECOMMENDATIONS_BY_GROUP = [
        'Усі розпорядники інформації' => [
            DatasetRecommendation::OrganizationsDirectory,
            DatasetRecommendation::OrganizationStructure,
            DatasetRecommendation::Normatives,
            DatasetRecommendation::NationalStandards,
            DatasetRecommendation::Reports,
            DatasetRecommendation::DatasetsRegister,
            DatasetRecommendation::AdministrativeData,
            DatasetRecommendation::LegalActs,
            DatasetRecommendation::RegulatoryActs,
            DatasetRecommendation::RegulatoryPlanning,
            DatasetRecommendation::LegalFramework,
            DatasetRecommendation::FinancialReports,
            DatasetRecommendation::FinancialPlanResults,
            DatasetRecommendation::InternationalAssistanceProperty,
            DatasetRecommendation::PublicInformationRegister,
            DatasetRecommendation::InformationAudit,
        ],
        'Архітектура та містобудування' => [
            DatasetRecommendation::AddressRegister,
            DatasetRecommendation::InfrastructureContributions,
            DatasetRecommendation::UrbanCadastre,
            DatasetRecommendation::BuildingPassports,
            DatasetRecommendation::ConstructionRegister,
            DatasetRecommendation::AdvertisingStructures,
            DatasetRecommendation::UrbanPlans,
        ],
        'Бюджет та фінанси' => [
            DatasetRecommendation::CharitableAssistance,
            DatasetRecommendation::InvestmentContracts,
            DatasetRecommendation::LocalPrograms,
        ],
        'Інфраструктура' => [
            DatasetRecommendation::ImprovementPermits,
            DatasetRecommendation::BuildingAccessibility,
            DatasetRecommendation::Parking,
            DatasetRecommendation::PublicToilets,
            DatasetRecommendation::MunicipalEquipment,
            DatasetRecommendation::UtilityConsumption,
            DatasetRecommendation::UtilityTariffs,
            DatasetRecommendation::RoadRepairs,
            DatasetRecommendation::ConstructionProjects,
            DatasetRecommendation::Playgrounds,
            DatasetRecommendation::DogParks,
            DatasetRecommendation::CivilProtectionShelters,
            DatasetRecommendation::ChargingStations,
        ],
        'Комунальна власність' => [
            DatasetRecommendation::LandTenants,
            DatasetRecommendation::HousingQueue,
            DatasetRecommendation::PropertyAvailableForLease,
            DatasetRecommendation::AvailableLand,
            DatasetRecommendation::MunicipalProperty,
            DatasetRecommendation::LeasedProperty,
            DatasetRecommendation::LandPrivatizationApplications,
            DatasetRecommendation::LeasePayments,
            DatasetRecommendation::SurveillanceCameras,
            DatasetRecommendation::EnergyManagedBuildings,
            DatasetRecommendation::WasteContainers,
            DatasetRecommendation::WasteManagementFacilities,
        ],
        'Місцеві ради' => [
            DatasetRecommendation::LocalDeputies,
            DatasetRecommendation::CouncilVoting,
        ],
        'Навколишнє середовище' => [
            DatasetRecommendation::TreesForRemoval,
            DatasetRecommendation::WasteServiceContracts,
        ],
        'Освіта' => [
            DatasetRecommendation::PreschoolQueue,
            DatasetRecommendation::SchoolCatchmentAreas,
            DatasetRecommendation::PreschoolCatchmentAreas,
        ],
        "Охорона здоров'я" => [
            DatasetRecommendation::Medicines,
            DatasetRecommendation::MedicalEquipment,
        ],
        'Торгівля' => [
            DatasetRecommendation::TemporaryStructures,
            DatasetRecommendation::Fairs,
            DatasetRecommendation::TradeFacilities,
        ],
        'Громадський транспорт' => [
            DatasetRecommendation::TransportVehicles,
            DatasetRecommendation::RealTimeTransport,
            DatasetRecommendation::TransportStops,
            DatasetRecommendation::TransportOperators,
            DatasetRecommendation::TransportSchedules,
        ],
        'Участь громадськості та інше' => [
            DatasetRecommendation::ParticipatoryBudget,
            DatasetRecommendation::Petitions,
            DatasetRecommendation::AdministrativeServices,
            DatasetRecommendation::HotlineAppeals,
            DatasetRecommendation::StrayAnimals,
            DatasetRecommendation::ParkingFines,
            DatasetRecommendation::BurialRegisters,
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $publisher = CouncilOrganization::query()->firstOrFail();

        foreach (self::RECOMMENDATIONS_BY_GROUP as $groupName => $recommendations) {
            $group = DatasetGroup::query()->where('name', $groupName)->firstOrFail();
            $sort = (int) Dataset::query()->where('group_id', $group->getKey())->max('sort');

            Dataset::query()->upsert(
                collect($recommendations)
                    ->map(function ($recommendation) use (&$sort, $publisher, $group) {
                        return [
                            'rec_code' => $recommendation->value,
                            'title' => $recommendation->getLabel(),
                            'publisher_id' => $publisher->getKey(),
                            'group_id' => $group->getKey(),
                            'sort' => ++$sort,
                        ];
                    })->all(),
                uniqueBy: ['rec_code'],
                update: ['updated_at'],
            );
        }
    }
}
