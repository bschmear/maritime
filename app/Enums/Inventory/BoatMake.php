<?php

namespace App\Enums\Inventory;

enum BoatMake: string
{
    case ABInflatables                 = 'ab-inflatables';
    case Achilles                      = 'achilles';
    case Airship                       = 'airship';
    case Allmand                       = 'allmand';
    case Apex                          = 'apex';
    case Aquaform                      = 'aquaform';
    case Aquarius                      = 'aquarius';
    case Aquascan                      = 'aquascan';
    case Aqvaboats                     = 'aqvaboats';
    case ArcticBlue                    = 'arctic-blue';
    case Avon                          = 'avon';
    case BWA                           = 'bwa';
    case Brig                          = 'brig';
    case Capelli                       = 'capelli';
    case Caribe                        = 'caribe';
    case Custom                        = 'custom';
    case DGS                           = 'dgs';
    case DNA                           = 'dna';
    case EastMarine                    = 'east-marine';
    case Flexboat                      = 'flexboat';
    case Gala                          = 'gala';
    case Geniuss                       = 'geniuss';
    case Grand                         = 'grand';
    case GrandInflatables              = 'grand-inflatables';
    case HBI                           = 'hbi';
    case Highfield                     = 'highfield';
    case Inmar                         = 'inmar';
    case Mercury                       = 'mercury';
    case MercuryInflatables            = 'mercury-inflatables';
    case Nautica                       = 'nautica';
    case NorthAtlanticInflatables      = 'north-atlantic-inflatables';
    case Northstar                     = 'northstar';
    case Novamarine                    = 'novamarine';
    case Novurania                     = 'novurania';
    case OceanCraftMarine              = 'ocean-craft-marine';
    case Onda                          = 'onda';
    case Other                         = 'other';
    case PanameraYacht                 = 'panamera-yacht';
    case Protector                     = 'protector';
    case Ranger                        = 'ranger';
    case Ranieri                       = 'ranieri';
    case Rendova                       = 'rendova';
    case Ribco                         = 'ribco';
    case Ribcraft                      = 'ribcraft';
    case Ribjet                        = 'ribjet';
    case Roughneck                     = 'roughneck';
    case SACS                          = 'sacs';
    case SPXRIB                        = 'spx-rib';
    case Salpa                         = 'salpa';
    case Saturn                        = 'saturn';
    case SeaEagle                      = 'sea-eagle';
    case SeaProp                       = 'sea-prop';
    case SeaWater                      = 'sea-water';
    case SkipperBSK                    = 'skipper-bsk';
    case Stryker                       = 'stryker';
    case SuperRib                      = 'superrib';
    case TigerMarine                   = 'tiger-marine';
    case TideCraft                     = 'tide-craft';
    case TwoBar                        = '2-bar';
    case WalkerBay                     = 'walker-bay';
    case WestMarine                    = 'west-marine';
    case Willard                       = 'willard';
    case WilliamsJetTenders            = 'williams-jet-tenders';
    case Zar                           = 'zar';
    case ZarFormenti                   = 'zar-formenti';
    case ZarMini                       = 'zar-mini';
    case Zodiac                        = 'zodiac';

    public function id(): int
    {
        return array_search($this, self::cases(), true) + 1;
    }

    public function label(): string
    {
        return ucwords(str_replace('-', ' ', $this->value));
    }

    /**
     * A make can belong to one or more categories.
     */
    public function categories(): array
    {
        return match ($this) {

            // Jet-only brands
            self::Mercury,
                => [BoatCategory::Jet],

            // Jet + Inflatable
            self::WilliamsJetTenders,
                => [BoatCategory::Inflatable, BoatCategory::Jet],

            // Inflatable (default)
            default
                => [BoatCategory::Inflatable],
        };
    }

    public function supportsCategory(BoatCategory $category): bool
    {
        return in_array($category, $this->categories(), true);
    }

    public function isInflatable(): bool
    {
        return $this->supportsCategory(BoatCategory::Inflatable);
    }

    public function isJet(): bool
    {
        return $this->supportsCategory(BoatCategory::Jet);
    }

    public static function byCategory(BoatCategory $category): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $case) => $case->supportsCategory($category)
        ));
    }

    public static function options(): array
    {
        return array_map(fn (self $case) => [
            'id'         => $case->id(),
            'value'      => $case->value,
            'name'       => $case->label(),
            'categories' => array_map(
                fn (BoatCategory $cat) => $cat->value,
                $case->categories()
            ),
        ], self::cases());
    }
}
