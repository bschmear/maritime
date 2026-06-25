<?php

use App\Domain\AssetOption\Actions\CreateAssetOption;
use App\Domain\AssetOption\Actions\UpdateAssetOption;
use App\Domain\AssetOption\Models\AssetOption;
use App\Domain\AssetOptionCategory\Models\AssetOptionCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Boat options from docs/Example_Data/Boat_Options_Master_List_with_Dropdowns.csv
 *
 * Related choices (Garmin models, VHF packages, etc.) are one option with input_type
 * `select` (pick one). Standalone add-ons remain toggles.
 *
 * php artisan tenants:seed-data boat-options --tenant=2
 */
return new class extends Seeder
{
    private const HOURLY_RATE = 195;

    /**
     * Pick-one groups — each member becomes a value on a single select option.
     *
     * @var list<array{slug: string, name: string, category: string, members: list<string>}>
     */
    private const GROUPS = [
        [
            'slug' => 'garmin-chartplotter',
            'name' => 'Garmin Chartplotter',
            'category' => 'Electrical & Electronics',
            'members' => [
                'Garmin 64SV 010-02681-00',
                'Garmin 74SV 010-02685-01',
                'Garmin 743 XSV  010-02365-61',
                'Garmin 943 XSV  010-02366-61',
                'Garmin 12 XSV 010-02367-61',
                'Garmin 15 XSV Wide Screen 010-03855-01',
            ],
        ],
        [
            'slug' => 'battery-charger',
            'name' => 'Battery Charger',
            'category' => 'Electrical & Electronics',
            'members' => [
                'Battery Charger 1 Bank 5A',
                'Battery Charger 2 Bank 20A',
                'Battery Charger 3 Bank 20A',
            ],
        ],
        [
            'slug' => 'vhf',
            'name' => 'VHF',
            'category' => 'Electrical & Electronics',
            'members' => [
                'VHF',
                'VHF w/ black box remote mic',
            ],
        ],
        [
            'slug' => 'stereo-package',
            'name' => 'Stereo Package',
            'category' => 'Electrical & Electronics',
            'members' => [
                'Stereo (2 speakers) fusion',
                'Fusion Stereo w/ 2 JL speakers',
                'Fusion Stereo w/ 4 JL speakers amp and subwoofer',
                'Fusion Stereo w/ 4 speakers',
            ],
        ],
        [
            'slug' => 'underwater-lights',
            'name' => 'Underwater Lights',
            'category' => 'Electrical & Electronics',
            'members' => [
                'Underwater Lights (small)',
                'Underwater Lights (Large)',
            ],
        ],
        [
            'slug' => 'engine-upgrade',
            'name' => 'Engine Upgrade',
            'category' => 'Engines & Performance',
            'members' => [
                'Suzuki Upgrade',
                'Yamaha 40 Upgrade',
            ],
        ],
        [
            'slug' => 't-top',
            'name' => 'T-Top',
            'category' => 'Appearance & Comfort',
            'members' => [
                'Custom Extended T-Top-Medline 9',
                'T-top 24 VST',
            ],
        ],
        [
            'slug' => 'registration',
            'name' => 'Registration',
            'category' => 'Miscellaneous',
            'members' => [
                'Registration Vinyl',
                'Registration Hypalon',
            ],
        ],
    ];

    public function run(): void
    {
        $csvPath = base_path('docs/Example_Data/Boat_Options_Master_List_with_Dropdowns.csv');
        if (! is_readable($csvPath)) {
            $this->command?->error('CSV not found: '.$csvPath);

            return;
        }

        $rows = $this->parseCsv($csvPath);
        $rowsByName = [];
        foreach ($rows as $row) {
            $rowsByName[$this->normalizeName($row['name'])] = $row;
        }

        $groupedMemberNames = [];
        foreach (self::GROUPS as $group) {
            foreach ($group['members'] as $member) {
                $groupedMemberNames[$this->normalizeName($member)] = true;
            }
        }

        $create = app(CreateAssetOption::class);
        $update = app(UpdateAssetOption::class);
        $categories = [];
        $optionIds = [];
        $created = 0;
        $updated = 0;
        $removed = 0;

        foreach (self::GROUPS as $group) {
            $values = [];
            foreach ($group['members'] as $index => $memberName) {
                $row = $rowsByName[$this->normalizeName($memberName)] ?? null;
                if ($row === null) {
                    $this->command?->warn("Group {$group['name']}: missing CSV row for \"{$memberName}\"");

                    continue;
                }

                $values[] = [
                    'label' => $row['name'],
                    'value' => Str::slug($row['name']) ?: 'value-'.$index,
                    'cost' => $row['cost'],
                    'price' => $row['price'],
                    'sort_order' => $index * 10,
                ];
            }

            if ($values === []) {
                continue;
            }

            $payload = [
                'name' => $group['name'],
                'slug' => $group['slug'],
                'category_id' => $this->categoryId($categories, $group['category']),
                'input_type' => 'select',
                'is_required' => false,
                'allow_multiple' => false,
                'min_select' => 0,
                'max_select' => 1,
                'active' => true,
                'values' => $values,
            ];

            [$option, $wasCreated] = $this->upsertOption($create, $update, $payload);
            if ($option === null) {
                $this->command?->warn('Skipped group: '.$group['name']);

                continue;
            }

            $optionIds[] = (int) $option->id;
            $wasCreated ? $created++ : $updated++;
        }

        foreach ($rows as $row) {
            if (isset($groupedMemberNames[$this->normalizeName($row['name'])])) {
                continue;
            }

            if ($this->isNonPricedPlaceholder($row)) {
                continue;
            }

            $slug = Str::slug($row['name']) ?: 'option';
            $payload = [
                'name' => $row['name'],
                'slug' => $slug,
                'category_id' => $this->categoryId($categories, $row['category']),
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

            [$option, $wasCreated] = $this->upsertOption($create, $update, $payload);
            if ($option === null) {
                $this->command?->warn('Skipped: '.$row['name']);

                continue;
            }

            $optionIds[] = (int) $option->id;
            $wasCreated ? $created++ : $updated++;
        }

        foreach (array_keys($groupedMemberNames) as $normalizedMember) {
            $row = $rowsByName[$normalizedMember] ?? null;
            if ($row === null) {
                continue;
            }

            $legacySlug = Str::slug($row['name']);
            if ($legacySlug === '') {
                continue;
            }

            $legacy = AssetOption::query()->where('slug', $legacySlug)->first();
            if ($legacy !== null && ! in_array((int) $legacy->id, $optionIds, true)) {
                $legacy->delete();
                $removed++;
            }
        }

        foreach ($optionIds as $optionId) {
            AssetOption::query()->whereKey($optionId)->update(['is_global' => true]);
        }

        $this->command?->info(sprintf(
            'Boat options: %d created, %d updated, %d legacy toggles removed, %d marked as global.',
            $created,
            $updated,
            $removed,
            count($optionIds)
        ));
    }

    /**
     * @return list<array{name: string, category: string, cost: ?float, price: ?float}>
     */
    private function parseCsv(string $path): array
    {
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        $header = fgetcsv($handle);
        $rows = [];
        $lastCategory = 'Miscellaneous';

        while (($cells = fgetcsv($handle)) !== false) {
            $name = trim((string) ($cells[1] ?? ''));
            if ($name === '' || str_contains(strtolower($name), 'hourly rate')) {
                continue;
            }

            $category = trim((string) ($cells[0] ?? ''));
            if ($category !== '') {
                $lastCategory = $category;
            }

            $equipment = $this->parseMoney($cells[2] ?? null);
            $install = $this->parseMoney($cells[3] ?? null);
            $laborHours = $this->parseNumber($cells[4] ?? null);
            $listPrice = $this->parseMoney($cells[9] ?? null);

            $cost = null;
            if ($equipment !== null || $install !== null || $laborHours !== null) {
                $cost = round(
                    ($equipment ?? 0) + ($install ?? 0) + (($laborHours ?? 0) * self::HOURLY_RATE),
                    2
                );
            }

            $price = $listPrice ?? $cost;

            $rows[] = [
                'name' => $name,
                'category' => $lastCategory,
                'cost' => $cost,
                'price' => $price,
            ];
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @param  array<string, int>  $categories
     */
    private function categoryId(array &$categories, string $name): int
    {
        if (! isset($categories[$name])) {
            $categories[$name] = AssetOptionCategory::firstOrCreateByName($name)->id;
        }

        return $categories[$name];
    }

    /**
     * @return array{0: ?AssetOption, 1: bool}
     */
    private function upsertOption(CreateAssetOption $create, UpdateAssetOption $update, array $payload): array
    {
        $existing = AssetOption::query()->where('slug', $payload['slug'])->first();

        if ($existing === null) {
            $result = $create($payload);
            if (! ($result['success'] ?? false) || ! isset($result['record'])) {
                return [null, false];
            }

            return [$result['record'], true];
        }

        $result = $update($existing->id, $payload);
        if (! ($result['success'] ?? false) || ! isset($result['record'])) {
            return [null, false];
        }

        return [$result['record'], false];
    }

    private function normalizeName(string $name): string
    {
        return strtolower(preg_replace('/\s+/', ' ', trim($name)) ?? trim($name));
    }

    private function parseMoney(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '' || strcasecmp($s, 'tbd') === 0 || strcasecmp($s, 'standard') === 0) {
            return null;
        }

        $s = str_replace(['$', ',', ' '], '', $s);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return round((float) $s, 2);
    }

    private function parseNumber(mixed $value): ?float
    {
        if ($value === null) {
            return null;
        }

        $s = trim((string) $value);
        if ($s === '' || ! is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    /**
     * @param  array{name: string, category: string, cost: ?float, price: ?float}  $row
     */
    private function isNonPricedPlaceholder(array $row): bool
    {
        return strcasecmp(trim($row['name']), 'Engine') === 0;
    }
};
