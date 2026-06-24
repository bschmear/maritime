<?php

use App\Domain\AssetOption\Actions\CreateAssetOption;
use App\Domain\AssetOption\Actions\SyncAssetOptionAssignments;
use App\Domain\AssetOption\Actions\UpdateAssetOption;
use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOption\Models\AssetOptionCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * One-off boat options seeder — delete when done.
 * Assigns each option to all active brands (all models).
 *
 * php artisan tenants:seed-data boat-options --tenant=2
 */
return new class extends Seeder
{
    public function run(): void
    {
        $rows = array (
  0 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Engine',
    'cost' => NULL,
    'price' => NULL,
  ),
  1 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Suzuki Upgrade',
    'cost' => NULL,
    'price' => 1500.0,
  ),
  2 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Yamaha 40 Upgrade',
    'cost' => NULL,
    'price' => 1500.0,
  ),
  3 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Doel Fin',
    'cost' => NULL,
    'price' => 190.0,
  ),
  4 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Stainless Prop',
    'cost' => NULL,
    'price' => NULL,
  ),
  5 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Spare Prop',
    'cost' => NULL,
    'price' => NULL,
  ),
  6 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Optional Fuel Tank',
    'cost' => NULL,
    'price' => NULL,
  ),
  7 => 
  array (
    'category' => 'Engines & Performance',
    'name' => 'Full Tank of Fuel',
    'cost' => NULL,
    'price' => NULL,
  ),
  8 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Garmin 64SV 010-02681-00',
    'cost' => 1564.0,
    'price' => 1564.0,
  ),
  9 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Garmin 74SV 010-02685-01',
    'cost' => 1764.0,
    'price' => 1800.0,
  ),
  10 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Garmin 743 XSV  010-02365-61',
    'cost' => 1924.0,
    'price' => 1924.0,
  ),
  11 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Garmin 943 XSV  010-02366-61',
    'cost' => 2164.0,
    'price' => 2800.0,
  ),
  12 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Garmin 12 XSV 010-02367-61',
    'cost' => 3364.0,
    'price' => 4000.0,
  ),
  13 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Garmin 15 XSV Wide Screen 010-03855-01',
    'cost' => 4679.0,
    'price' => 4679.0,
  ),
  14 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Battery Charger 1 Bank 5A',
    'cost' => NULL,
    'price' => 350.0,
  ),
  15 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Battery Charger 2 Bank 20A',
    'cost' => NULL,
    'price' => 480.0,
  ),
  16 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Battery Charger 3 Bank 20A',
    'cost' => NULL,
    'price' => 560.0,
  ),
  17 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'External Battery Switch',
    'cost' => NULL,
    'price' => NULL,
  ),
  18 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Dual Battery',
    'cost' => NULL,
    'price' => NULL,
  ),
  19 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'USB',
    'cost' => NULL,
    'price' => NULL,
  ),
  20 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Cigarette Lighter',
    'cost' => NULL,
    'price' => NULL,
  ),
  21 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Wireless Phone Charger',
    'cost' => NULL,
    'price' => 430.0,
  ),
  22 => 
  array (
    'category' => 'Electronics',
    'name' => 'AIS',
    'cost' => NULL,
    'price' => NULL,
  ),
  23 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'VHF',
    'cost' => NULL,
    'price' => 950.0,
  ),
  24 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'VHF w/ black box remote mic',
    'cost' => NULL,
    'price' => 1200.0,
  ),
  25 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Stereo (2 speakers) fusion',
    'cost' => NULL,
    'price' => 1150.0,
  ),
  26 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Fusion Stereo w/ 2 JL speakers',
    'cost' => NULL,
    'price' => 1400.0,
  ),
  27 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Fusion Stereo w/ 4 JL speakers amp and subwoofer',
    'cost' => NULL,
    'price' => 4900.0,
  ),
  28 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Fusion Stereo w/ 4 speakers',
    'cost' => NULL,
    'price' => 2400.0,
  ),
  29 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Subwoofer',
    'cost' => NULL,
    'price' => NULL,
  ),
  30 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Transducer',
    'cost' => NULL,
    'price' => 350.0,
  ),
  31 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Cockpit Lights (4)',
    'cost' => NULL,
    'price' => 450.0,
  ),
  32 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Underwater Lights (small)',
    'cost' => NULL,
    'price' => 1300.0,
  ),
  33 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Underwater Lights (Large)',
    'cost' => NULL,
    'price' => 1800.0,
  ),
  34 => 
  array (
    'category' => 'Electrical & Electronics',
    'name' => 'Nav Lights',
    'cost' => NULL,
    'price' => NULL,
  ),
  35 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Carbon Trim Package',
    'cost' => NULL,
    'price' => NULL,
  ),
  36 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Sea Dek Floor/Steps',
    'cost' => NULL,
    'price' => NULL,
  ),
  37 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Diamond Stitch',
    'cost' => NULL,
    'price' => NULL,
  ),
  38 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Sun Pad',
    'cost' => 793.0,
    'price' => 793.0,
  ),
  39 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Bench Seat',
    'cost' => NULL,
    'price' => NULL,
  ),
  40 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => '4th Seat',
    'cost' => NULL,
    'price' => NULL,
  ),
  41 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Custom Cover',
    'cost' => NULL,
    'price' => NULL,
  ),
  42 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Bimini Top',
    'cost' => NULL,
    'price' => 1900.0,
  ),
  43 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Sunshade',
    'cost' => NULL,
    'price' => NULL,
  ),
  44 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Custom Extended T-Top-Medline 9',
    'cost' => NULL,
    'price' => NULL,
  ),
  45 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'T-top 24 VST',
    'cost' => NULL,
    'price' => NULL,
  ),
  46 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Privacy Screen- Revo 22',
    'cost' => NULL,
    'price' => NULL,
  ),
  47 => 
  array (
    'category' => 'Appearance & Comfort',
    'name' => 'Table',
    'cost' => NULL,
    'price' => NULL,
  ),
  48 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Lift Harness',
    'cost' => NULL,
    'price' => 750.0,
  ),
  49 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Chocks Installed w/ Hardware',
    'cost' => NULL,
    'price' => 5400.0,
  ),
  50 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Safety Kit',
    'cost' => NULL,
    'price' => 290.0,
  ),
  51 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Glove Box',
    'cost' => NULL,
    'price' => NULL,
  ),
  52 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Rod Holders',
    'cost' => NULL,
    'price' => NULL,
  ),
  53 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Insulated Coolers',
    'cost' => NULL,
    'price' => NULL,
  ),
  54 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Coolers',
    'cost' => NULL,
    'price' => NULL,
  ),
  55 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Spare trailer tire',
    'cost' => NULL,
    'price' => NULL,
  ),
  56 => 
  array (
    'category' => 'Convenience & Safety',
    'name' => 'Boarding Pole',
    'cost' => NULL,
    'price' => NULL,
  ),
  57 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Turbo Swing',
    'cost' => NULL,
    'price' => 1200.0,
  ),
  58 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Ski Pole',
    'cost' => NULL,
    'price' => NULL,
  ),
  59 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Fresh Water Shower',
    'cost' => NULL,
    'price' => NULL,
  ),
  60 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Armstrong Ladder',
    'cost' => NULL,
    'price' => 1600.0,
  ),
  61 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Folding Anchor',
    'cost' => NULL,
    'price' => NULL,
  ),
  62 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Tow Harness',
    'cost' => NULL,
    'price' => NULL,
  ),
  63 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Tow Bridal',
    'cost' => NULL,
    'price' => NULL,
  ),
  64 => 
  array (
    'category' => 'Recreation & Accessories',
    'name' => 'Membership',
    'cost' => NULL,
    'price' => NULL,
  ),
  65 => 
  array (
    'category' => 'Controls & Handling',
    'name' => 'Gauge Package',
    'cost' => NULL,
    'price' => NULL,
  ),
  66 => 
  array (
    'category' => 'Controls & Handling',
    'name' => 'Hydraulic Steering',
    'cost' => NULL,
    'price' => NULL,
  ),
  67 => 
  array (
    'category' => 'Controls & Handling',
    'name' => 'Joystick',
    'cost' => NULL,
    'price' => NULL,
  ),
  68 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Toilet/Head',
    'cost' => NULL,
    'price' => NULL,
  ),
  69 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Registration Vinyl',
    'cost' => NULL,
    'price' => 375.0,
  ),
  70 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Registration Hypalon',
    'cost' => NULL,
    'price' => NULL,
  ),
  71 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Air Pump w/ adapter gauge',
    'cost' => NULL,
    'price' => NULL,
  ),
  72 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Extended Warranty',
    'cost' => NULL,
    'price' => NULL,
  ),
  73 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Bilge Pump',
    'cost' => NULL,
    'price' => NULL,
  ),
  74 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Arch',
    'cost' => NULL,
    'price' => NULL,
  ),
  75 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Bow eye',
    'cost' => NULL,
    'price' => NULL,
  ),
  76 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Keel guard',
    'cost' => NULL,
    'price' => NULL,
  ),
  77 => 
  array (
    'category' => 'Miscellaneous',
    'name' => 'Personal Locator Beacon (PLB)',
    'cost' => NULL,
    'price' => NULL,
  ),
);

        $create = app(CreateAssetOption::class);
        $update = app(UpdateAssetOption::class);
        $sync = app(SyncAssetOptionAssignments::class);
        $categories = [];
        $created = 0;
        $updated = 0;
        $optionIds = [];

        foreach ($rows as $row) {
            $categoryName = $row['category'];
            if (! isset($categories[$categoryName])) {
                $categories[$categoryName] = AssetOptionCategory::firstOrCreateByName(
                    $categoryName,
                    count($categories) * 10
                )->id;
            }

            $slug = Str::slug($row['name']) ?: 'option';
            $existing = AssetOption::query()->where('slug', $slug)->first();

            $payload = [
                'name' => $row['name'],
                'slug' => $slug,
                'category_id' => $categories[$categoryName],
                'input_type' => 'toggle',
                'is_required' => false,
                'allow_multiple' => false,
                'active' => true,
                'values' => [[
                    'label' => 'On',
                    'value' => 'on',
                    'cost' => $row['cost'],
                    'price' => $row['price'],
                    'sort_order' => 0,
                ]],
            ];

            if ($existing === null) {
                $result = $create($payload);
                if (! ($result['success'] ?? false) || ! isset($result['record'])) {
                    $this->command?->warn('Skipped: '.$row['name']);

                    continue;
                }
                $option = $result['record'];
                $created++;
            } else {
                $result = $update($existing->id, $payload);
                if (! ($result['success'] ?? false) || ! isset($result['record'])) {
                    $this->command?->warn('Skipped: '.$row['name']);

                    continue;
                }
                $option = $result['record'];
                $updated++;
            }

            $optionIds[] = (int) $option->id;
        }

        foreach ($optionIds as $optionId) {
            $sync($optionId, true, []);
        }

        $this->command?->info(sprintf(
            'Boat options: %d created, %d updated, %d assigned to all active brands (all models).',
            $created,
            $updated,
            count($optionIds)
        ));
    }
};
