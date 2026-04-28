<?php

declare(strict_types=1);

namespace App\Enums\Inventory;

/**
 * Slugs and order align with {@see base_path('app/Domain/BoatMake/Schema/boat_types.json')}.
 * {@see self::id()} is 1-based index in that sorted list (keep case declaration order in sync when editing).
 */
enum BoatType: string
{
    case PowerAft = 'power-aft';
    case PowerAirboat = 'power-airboat';
    case PowerAll = 'power-all';
    case PowerAluminum = 'power-aluminum';
    case PowerAntique = 'power-antique';
    case PowerBarge = 'power-barge';
    case PowerBass = 'power-bass';
    case PowerBay = 'power-bay';
    case PowerBowrider = 'power-bowrider';
    case PowerCargo = 'power-cargo';
    case PowerCenter = 'power-center';
    case PowerCommercial = 'power-commercial';
    case PowerConvertible = 'power-convertible';
    case PowerCruiser = 'power-cruiser';
    case PowerCruiseship = 'power-cruiseship';
    case PowerCuddy = 'power-cuddy';
    case PowerDeck = 'power-deck';
    case PowerDinghy = 'power-dinghy';
    case PowerDive = 'power-dive';
    case PowerDowneast = 'power-downeast';
    case PowerDragger = 'power-dragger';
    case PowerDualconsole = 'power-dualconsole';
    case PowerExpresscruiser = 'power-expresscruiser';
    case PowerFlats = 'power-flats';
    case PowerFlybridge = 'power-flybridge';
    case PowerFresh = 'power-fresh';
    case PowerHighperf = 'power-highperf';
    case PowerHouse = 'power-house';
    case PowerInflatable = 'power-inflatable';
    case PowerJet = 'power-jet';
    case PowerJon = 'power-jon';
    case PowerLobster = 'power-lobster';
    case PowerMega = 'power-mega';
    case PowerMotor = 'power-motor';
    case PowerMotorsailer = 'power-motorsailer';
    case PowerNarrow = 'power-narrow';
    case PowerOther = 'power-other';
    case PowerPassenger = 'power-passenger';
    case PowerPcatamaran = 'power-pcatamaran';
    case PowerPilot = 'power-pilot';
    case PowerPontoon = 'power-pontoon';
    case PowerPwc = 'power-pwc';
    case PowerRib = 'power-rib';
    case PowerRivercruiser = 'power-rivercruiser';
    case PowerRunabout = 'power-runabout';
    case PowerSaltfish = 'power-saltfish';
    case PowerSkiff = 'power-skiff';
    case PowerSkifish = 'power-skifish';
    case PowerSkiwake = 'power-skiwake';
    case PowerSloop = 'power-sloop';
    case PowerSportcruiser = 'power-sportcruiser';
    case PowerSportfish = 'power-sportfish';
    case PowerTender = 'power-tender';
    case PowerTrawler = 'power-trawler';
    case PowerTroller = 'power-troller';
    case PowerTug = 'power-tug';
    case PowerUnspec = 'power-unspec';
    case PowerUtil = 'power-util';
    case PowerWalk = 'power-walk';
    case SailAll = 'sail-all';
    case SailAntique = 'sail-antique';
    case SailBarge = 'sail-barge';
    case SailBeachcat = 'sail-beachcat';
    case SailCatamaran = 'sail-catamaran';
    case SailCentercockpit = 'sail-centercockpit';
    case SailCommercial = 'sail-commercial';
    case SailCruiser = 'sail-cruiser';
    case SailCutter = 'sail-cutter';
    case SailDay = 'sail-day';
    case SailDeck = 'sail-deck';
    case SailDinghy = 'sail-dinghy';
    case SailKetch = 'sail-ketch';
    case SailMotor = 'sail-motor';
    case SailMultihull = 'sail-multihull';
    case SailOther = 'sail-other';
    case SailPerformance = 'sail-performance';
    case SailPilot = 'sail-pilot';
    case SailRacer = 'sail-racer';
    case SailRacercruiser = 'sail-racercruiser';
    case SailSchooner = 'sail-schooner';
    case SailSloop = 'sail-sloop';
    case SailTrimaran = 'sail-trimaran';
    case SailUnspec = 'sail-unspec';
    case SailYawl = 'sail-yawl';
    case SmallAll = 'small-all';
    case UnpoweredDinghy = 'unpowered-dinghy';
    case UnpoweredKayak = 'unpowered-kayak';
    case UnpoweredTender = 'unpowered-tender';

    public function id(): int
    {
        static $slugToOrdinal = null;
        if ($slugToOrdinal === null) {
            $slugToOrdinal = [];
            foreach (self::cases() as $i => $case) {
                $slugToOrdinal[$case->value] = $i + 1;
            }
        }

        return $slugToOrdinal[$this->value] ?? 0;
    }

    public function label(): string
    {
        static $labels = null;
        if ($labels === null) {
            $path = base_path('app/Domain/BoatMake/Schema/boat_types.json');
            if (! is_readable($path)) {
                $labels = [];
            } else {
                $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
                $labels = is_array($decoded) ? $decoded : [];
            }
        }

        return $labels[$this->value] ?? $this->value;
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
