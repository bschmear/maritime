<?php

namespace App\Enums\Inventory;

enum BoatType: string
{
    case AftCabinMotorYachts            = 'aft_cabin_motor_yachts';
    case AluminumFishingBoats           = 'aluminum_fishing_boats';
    case AntiqueClassicPowerBoats       = 'antique_classic_power_boats';
    case AntiqueClassicSailboats        = 'antique_classic_sailboats';
    case BassBoats                      = 'bass_boats';
    case BayBoats                       = 'bay_boats';
    case BeachCatamarans                = 'beach_catamarans';
    case Bowriders                      = 'bowriders';
    case Catamarans                     = 'catamarans';
    case CenterCockpitSailboats         = 'center_cockpit_sailboats';
    case CenterConsoles                 = 'center_consoles';
    case ConvertibleBoats               = 'convertible_boats';
    case CruiserRacers                  = 'cruiser_racers';
    case CuddyCabins                    = 'cuddy_cabins';
    case Daysailers                     = 'daysailers';
    case DeckBoats                      = 'deck_boats';
    case DeckSaloonSailboats            = 'deck_saloon_sailboats';
    case Dinghies                       = 'dinghies';
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
    case Kayaks                         = 'kayaks';
    case Ketches                        = 'ketches';
    case MegaYachts                     = 'mega_yachts';
    case Motorsailers                   = 'motorsailers';
    case MotorYachts                    = 'motor_yachts';
    case PersonalWatercraft             = 'personal_watercraft';
    case PilothousePowerBoats           = 'pilothouse_power_boats';
    case PilothouseSailboats            = 'pilothouse_sailboats';
    case PontoonBoats                   = 'pontoon_boats';
    case PowerBoats                     = 'power_boats';
    case PowerCatamarans                = 'power_catamarans';
    case PowerCruisers                  = 'power_cruisers';
    case RacingSailboats                = 'racing_sailboats';
    case Runabouts                      = 'runabouts';
    case Sailboats                      = 'sailboats';
    case SailingCruisers                = 'sailing_cruisers';
    case SailingDinghies                = 'sailing_dinghies';
    case SkiWakeboardBoats              = 'ski_wakeboard_boats';
    case Skiffs                         = 'skiffs';
    case SportFishingBoats              = 'sport_fishing_boats';
    case SportsCruisers                 = 'sports_cruisers';
    case Tenders                        = 'tenders';
    case Trawlers                       = 'trawlers';
    case Trimarans                      = 'trimarans';
    case UnpoweredBoats                 = 'unpowered_boats';
    case WalkaroundBoats                = 'walkaround_boats';

    public function id(): int
    {
        return match ($this) {
            self::AftCabinMotorYachts => 1,
            self::AluminumFishingBoats => 2,
            self::AntiqueClassicPowerBoats => 3,
            self::AntiqueClassicSailboats => 4,
            self::BassBoats => 5,
            self::BayBoats => 6,
            self::BeachCatamarans => 7,
            self::Bowriders => 8,
            self::Catamarans => 9,
            self::CenterCockpitSailboats => 10,
            self::CenterConsoles => 11,
            self::ConvertibleBoats => 12,
            self::CruiserRacers => 13,
            self::CuddyCabins => 14,
            self::Daysailers => 15,
            self::DeckBoats => 16,
            self::DeckSaloonSailboats => 17,
            self::Dinghies => 18,
            self::DowneastLobsterBoats => 19,
            self::DualConsoles => 20,
            self::ExpressCruisers => 21,
            self::FlatsBoats => 22,
            self::FlybridgeMotorYachts => 23,
            self::HighPerformancePowerBoats => 24,
            self::HouseBoats => 25,
            self::Inflatables => 26,
            self::JetBoats => 27,
            self::JonBoats => 28,
            self::Kayaks => 29,
            self::Ketches => 30,
            self::MegaYachts => 31,
            self::Motorsailers => 32,
            self::MotorYachts => 33,
            self::PersonalWatercraft => 34,
            self::PilothousePowerBoats => 35,
            self::PilothouseSailboats => 36,
            self::PontoonBoats => 37,
            self::PowerBoats => 38,
            self::PowerCatamarans => 39,
            self::PowerCruisers => 40,
            self::RacingSailboats => 41,
            self::Runabouts => 42,
            self::Sailboats => 43,
            self::SailingCruisers => 44,
            self::SailingDinghies => 45,
            self::SkiWakeboardBoats => 46,
            self::Skiffs => 47,
            self::SportFishingBoats => 48,
            self::SportsCruisers => 49,
            self::Tenders => 50,
            self::Trawlers => 51,
            self::Trimarans => 52,
            self::UnpoweredBoats => 53,
            self::WalkaroundBoats => 54,
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
