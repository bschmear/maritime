<?php

namespace App\Enums\Inventory;

enum BoatCategory: string
{
    case Inflatable        = 'inflatable';
    case RIB               = 'rib';
    case Dinghy            = 'dinghy';

    case Bowrider          = 'bowrider';
    case Deck              = 'deck';
    case Runabout          = 'runabout';

    case Pontoon           = 'pontoon';
    case Tritoon           = 'tritoon';

    case CenterConsole     = 'center_console';
    case DualConsole       = 'dual_console';
    case Walkaround        = 'walkaround';
    case Pilothouse        = 'pilothouse';

    case Bass              = 'bass';
    case Flats             = 'flats';
    case Bay               = 'bay';
    case Skiff             = 'skiff';

    case CuddyCabin        = 'cuddy_cabin';
    case CabinCruiser      = 'cabin_cruiser';
    case ExpressCruiser    = 'express_cruiser';

    case Trawler           = 'trawler';
    case MotorYacht        = 'motor_yacht';
    case MegaYacht         = 'mega_yacht';

    case Sailboat          = 'sailboat';
    case Catamaran         = 'catamaran';
    case Trimaran          = 'trimaran';

    case Houseboat         = 'houseboat';

    case HighPerformance   = 'high_performance';
    case Offshore          = 'offshore';

    case Jet               = 'jet';
    case PersonalWatercraft = 'personal_watercraft';

    case AluminumFishing   = 'aluminum_fishing';
    case JonBoat           = 'jon_boat';

    case Utility           = 'utility';
    case Commercial        = 'commercial';
    case Ferry             = 'ferry';
    case Tug               = 'tug';
    case Barge             = 'barge';

    case Other             = 'other';

    /**
     * Human-readable label
     */
    public function label(): string
    {
        return match ($this) {
            self::Inflatable        => 'Inflatable Boats',
            self::RIB               => 'Rigid Inflatable Boats (RIB)',
            self::Dinghy            => 'Dinghies',

            self::Bowrider          => 'Bowriders',
            self::Deck              => 'Deck Boats',
            self::Runabout          => 'Runabouts',

            self::Pontoon           => 'Pontoon Boats',
            self::Tritoon           => 'Tritoon Boats',

            self::CenterConsole     => 'Center Console Boats',
            self::DualConsole       => 'Dual Console Boats',
            self::Walkaround        => 'Walkaround Boats',
            self::Pilothouse        => 'Pilothouse Boats',

            self::Bass              => 'Bass Boats',
            self::Flats             => 'Flats Boats',
            self::Bay               => 'Bay Boats',
            self::Skiff             => 'Skiffs',

            self::CuddyCabin        => 'Cuddy Cabin Boats',
            self::CabinCruiser      => 'Cabin Cruisers',
            self::ExpressCruiser    => 'Express Cruisers',

            self::Trawler           => 'Trawlers',
            self::MotorYacht        => 'Motor Yachts',
            self::MegaYacht         => 'Mega Yachts',

            self::Sailboat          => 'Sailboats',
            self::Catamaran         => 'Catamarans',
            self::Trimaran          => 'Trimarans',

            self::Houseboat         => 'Houseboats',

            self::HighPerformance   => 'High Performance Boats',
            self::Offshore          => 'Offshore Boats',

            self::Jet               => 'Jet Boats',
            self::PersonalWatercraft => 'Personal Watercraft',

            self::AluminumFishing   => 'Aluminum Fishing Boats',
            self::JonBoat           => 'Jon Boats',

            self::Utility           => 'Utility Boats',
            self::Commercial        => 'Commercial Vessels',
            self::Ferry             => 'Ferries',
            self::Tug               => 'Tugboats',
            self::Barge             => 'Barges',

            self::Other             => 'Other',
        };
    }

    /**
     * Grouping for UI / filtering
     */
    public function group(): string
    {
        return match ($this) {
            // Recreational
            self::Bowrider,
            self::Deck,
            self::Runabout,
            self::Pontoon,
            self::Tritoon => 'Recreational',

            // Fishing
            self::CenterConsole,
            self::DualConsole,
            self::Walkaround,
            self::Pilothouse,
            self::Bass,
            self::Flats,
            self::Bay,
            self::Skiff,
            self::AluminumFishing,
            self::JonBoat => 'Fishing',

            // Cruisers & Yachts
            self::CuddyCabin,
            self::CabinCruiser,
            self::ExpressCruiser,
            self::Trawler,
            self::MotorYacht,
            self::MegaYacht => 'Cruisers & Yachts',

            // Sailing
            self::Sailboat,
            self::Catamaran,
            self::Trimaran => 'Sailing',

            // Watersports
            self::Jet,
            self::PersonalWatercraft,
            self::HighPerformance => 'Watersports',

            // Inflatable / Small Craft
            self::Inflatable,
            self::RIB,
            self::Dinghy => 'Inflatable & Small Craft',

            // Commercial
            self::Commercial,
            self::Ferry,
            self::Tug,
            self::Barge => 'Commercial',

            // Utility / Misc
            self::Utility,
            self::Houseboat,
            self::Offshore,
            self::Other => 'Other',
        };
    }

    /**
     * Options for dropdowns
     */
    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id'     => $case->value,
            'value'  => $case->value,
            'name'   => $case->label(),
            'group'  => $case->group(),
        ], self::cases());
    }

    /**
     * Grouped options (perfect for sidebar filters)
     */
    public static function groupedOptions(): array
    {
        $grouped = [];

        foreach (self::cases() as $case) {
            $grouped[$case->group()][] = [
                'value' => $case->value,
                'name'  => $case->label(),
            ];
        }

        // Optional: sort each group alphabetically
        foreach ($grouped as &$items) {
            usort($items, fn ($a, $b) => strcmp($a['name'], $b['name']));
        }

        ksort($grouped);

        return $grouped;
    }
}