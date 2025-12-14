<?php

namespace App\Enums\Inventory;

enum BoatType: string
{
    // Power Boats
    case PowerBoats                     = 'power_boats';
    case AftCabinMotorYachts            = 'aft_cabin_motor_yachts';
    case AluminumFishingBoats           = 'aluminum_fishing_boats';
    case AntiqueClassicPowerBoats       = 'antique_classic_power_boats';
    case BassBoats                      = 'bass_boats';
    case BayBoats                       = 'bay_boats';
    case Bowriders                      = 'bowriders';
    case CenterConsoles                 = 'center_consoles';
    case ConvertibleBoats               = 'convertible_boats';
    case PowerCruisers                  = 'power_cruisers';
    case CuddyCabins                    = 'cuddy_cabins';
    case DeckBoats                      = 'deck_boats';
    case DowneastLobsterBoats           = 'downeast_lobster_boats';
    case DualConsoles                   = 'dual_consoles';
    case ExpressCruisers                = 'express_cruisers';
    case FlatsBoats                     = 'flats_boats';
    case FlybridgeMotorYachts           = 'flybridge_motor_yachts';
    case HighPerformancePowerBoats      = 'high_performance_power_boats';
    case HouseBoats                     = 'house_boats';
    case Inflatables                    = 'inflatables';
    case JetBoats                       = 'jet_boats';
    case JonBoats                       = 'jon_boats';
    case MegaYachts                     = 'mega_yachts';
    case MotorYachts                    = 'motor_yachts';
    case PilothousePowerBoats           = 'pilothouse_power_boats';
    case PontoonBoats                   = 'pontoon_boats';
    case PowerCatamarans                = 'power_catamarans';
    case Runabouts                      = 'runabouts';
    case SkiWakeboardBoats              = 'ski_wakeboard_boats';
    case Skiffs                         = 'skiffs';
    case SportFishingBoats              = 'sport_fishing_boats';
    case SportsCruisers                 = 'sports_cruisers';
    case Tenders                        = 'tenders';
    case Trawlers                       = 'trawlers';
    case WalkaroundBoats                = 'walkaround_boats';

    // Sailboats
    case Sailboats                      = 'sailboats';
    case AntiqueClassicSailboats        = 'antique_classic_sailboats';
    case BeachCatamarans                = 'beach_catamarans';
    case Catamarans                     = 'catamarans';
    case CenterCockpitSailboats         = 'center_cockpit_sailboats';
    case SailingCruisers                = 'sailing_cruisers';
    case CruiserRacers                  = 'cruiser_racers';
    case Daysailers                     = 'daysailers';
    case DeckSaloonSailboats            = 'deck_saloon_sailboats';
    case SailingDinghies                = 'sailing_dinghies';
    case Ketches                        = 'ketches';
    case Motorsailers                   = 'motorsailers';
    case PilothouseSailboats            = 'pilothouse_sailboats';
    case RacingSailboats                = 'racing_sailboats';
    case Trimarans                      = 'trimarans';

    // Unpowered and Other
    case UnpoweredBoats                 = 'unpowered_boats';
    case Dinghies                       = 'dinghies';
    case Kayaks                         = 'kayaks';
    case PersonalWatercraft             = 'personal_watercraft';

    public function id(): int
    {
        return match ($this) {
            self::PowerBoats => 1,
            self::AftCabinMotorYachts => 2,
            self::AluminumFishingBoats => 3,
            self::AntiqueClassicPowerBoats => 4,
            self::BassBoats => 5,
            self::BayBoats => 6,
            self::Bowriders => 7,
            self::CenterConsoles => 8,
            self::ConvertibleBoats => 9,
            self::PowerCruisers => 10,
            self::CuddyCabins => 11,
            self::DeckBoats => 12,
            self::DowneastLobsterBoats => 13,
            self::DualConsoles => 14,
            self::ExpressCruisers => 15,
            self::FlatsBoats => 16,
            self::FlybridgeMotorYachts => 17,
            self::HighPerformancePowerBoats => 18,
            self::HouseBoats => 19,
            self::Inflatables => 20,
            self::JetBoats => 21,
            self::JonBoats => 22,
            self::MegaYachts => 23,
            self::MotorYachts => 24,
            self::PilothousePowerBoats => 25,
            self::PontoonBoats => 26,
            self::PowerCatamarans => 27,
            self::Runabouts => 28,
            self::SkiWakeboardBoats => 29,
            self::Skiffs => 30,
            self::SportFishingBoats => 31,
            self::SportsCruisers => 32,
            self::Tenders => 33,
            self::Trawlers => 34,
            self::WalkaroundBoats => 35,
            self::Sailboats => 36,
            self::AntiqueClassicSailboats => 37,
            self::BeachCatamarans => 38,
            self::Catamarans => 39,
            self::CenterCockpitSailboats => 40,
            self::SailingCruisers => 41,
            self::CruiserRacers => 42,
            self::Daysailers => 43,
            self::DeckSaloonSailboats => 44,
            self::SailingDinghies => 45,
            self::Ketches => 46,
            self::Motorsailers => 47,
            self::PilothouseSailboats => 48,
            self::RacingSailboats => 49,
            self::Trimarans => 50,
            self::UnpoweredBoats => 51,
            self::Dinghies => 52,
            self::Kayaks => 53,
            self::PersonalWatercraft => 54,
        };
    }

    public function label(): string
    {
        return ucwords(str_replace('_', ' ', $this->value));
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id' => $case->id(),
            'value' => $case->value,
            'name' => $case->label(),
        ], self::cases());
    }
}
