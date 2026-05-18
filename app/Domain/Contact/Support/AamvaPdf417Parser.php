<?php

declare(strict_types=1);

namespace App\Domain\Contact\Support;

/**
 * Best-effort parser for US/Canadian-style AAMVA PDF417 payloads from driver licenses.
 * Field layout varies by jurisdiction; this extracts common elements when present.
 */
final class AamvaPdf417Parser
{
    /**
     * Three-letter data element IDs used as split boundaries (subset of AAMVA D20).
     *
     * @var list<string>
     */
    private const ELEMENT_IDS = [
        'DCA', 'DCB', 'DCD', 'DCE', 'DCF', 'DCG', 'DCH', 'DCI', 'DCJ', 'DCK', 'DCL', 'DCM', 'DCN', 'DCO', 'DCP', 'DCQ', 'DCR',
        'DCS', 'DCT', 'DCU', 'DDA', 'DDB', 'DDC', 'DDD', 'DDE', 'DDF', 'DDG', 'DDH', 'DDI', 'DDJ', 'DDK', 'DDL', 'DDM', 'DDN',
        'DAA', 'DAB', 'DAC', 'DAD', 'DAE', 'DAF', 'DAG', 'DAH', 'DAI', 'DAJ', 'DAK', 'DAL', 'DAM', 'DAN', 'DAO', 'DAP', 'DAQ',
        'DAR', 'DAS', 'DAT', 'DAU', 'DAV', 'DAW', 'DAX', 'DAY', 'DAZ',
        'DBA', 'DBB', 'DBC', 'DBD', 'DBE',
        'PAA', 'PAB', 'PAC', 'PAD', 'PAE',
    ];

    /** @var non-empty-string|null */
    private static ?string $elementBoundaryRegex = null;

    /**
     * @return array{
     *     fields: array<string, string>,
     *     contact: array<string, mixed>,
     *     extracted_rows: list<array{label: string, value: string}>
     * }
     */
    public static function parse(string $raw): array
    {
        $normalized = str_replace(["\r\n", "\r"], "\n", $raw);
        $normalized = preg_replace('/\x00+/', '', $normalized) ?? $normalized;
        $normalized = trim($normalized);

        $fields = self::splitFields($normalized);

        $first = $fields['DAC'] ?? '';
        $last = $fields['DCS'] ?? '';
        $lines = [];
        if ($fields['DAQ'] ?? '') {
            $lines[] = 'Driver license #: '.$fields['DAQ'];
        }
        if ($dob = self::formatAamvaDate($fields['DBB'] ?? null)) {
            $lines[] = 'DOB (from scan): '.$dob;
        }
        if ($exp = self::formatAamvaDate($fields['DBA'] ?? null)) {
            $lines[] = 'ID expiration (from scan): '.$exp;
        }

        $address = [
            'label' => 'Mailing',
            'is_primary' => true,
            'address_line_1' => $fields['DAG'] ?? null,
            'address_line_2' => null,
            'city' => $fields['DAI'] ?? null,
            'state' => isset($fields['DAJ']) ? strtoupper(substr($fields['DAJ'], 0, 2)) : null,
            'postal_code' => $fields['DAK'] ?? null,
            'country' => self::guessCountry($fields),
        ];

        $hasAddress = array_filter([
            $address['address_line_1'],
            $address['city'],
            $address['state'],
            $address['postal_code'],
        ]);

        $contact = [
            'first_name' => self::titleCaseName($first),
            'last_name' => self::titleCaseName($last),
            'type' => 'person',
            'notes' => $lines !== [] ? implode("\n", $lines) : null,
            'addresses' => $hasAddress !== [] ? [$address] : [],
        ];

        $rows = self::buildDisplayRows($fields);

        return [
            'fields' => $fields,
            'contact' => $contact,
            'extracted_rows' => $rows,
        ];
    }

    /**
     * @return array<string, string>
     */
    private static function splitFields(string $s): array
    {
        $regex = self::elementBoundarySplitRegex();
        if (preg_match_all($regex, $s, $m, PREG_SET_ORDER)) {
            $out = [];
            foreach ($m as $row) {
                $code = $row[1] ?? '';
                $val = trim($row[2] ?? '');
                if ($code !== '' && $val !== '') {
                    $out[$code] = $val;
                }
            }

            if ($out !== []) {
                return $out;
            }
        }

        return self::splitFieldsLineBased($s);
    }

    /**
     * @return non-empty-string
     */
    private static function elementBoundarySplitRegex(): string
    {
        if (self::$elementBoundaryRegex !== null) {
            return self::$elementBoundaryRegex;
        }

        $alt = implode('|', array_map(static fn (string $id): string => preg_quote($id, '/'), self::ELEMENT_IDS));
        self::$elementBoundaryRegex = '/('.$alt.')(.+?)(?='.$alt.'|$)/s';

        return self::$elementBoundaryRegex;
    }

    /**
     * Some jurisdictions use newline-terminated "CODEvalue" lines.
     *
     * @return array<string, string>
     */
    private static function splitFieldsLineBased(string $s): array
    {
        $out = [];
        foreach (preg_split("/\n+/", $s) ?: [] as $line) {
            $line = trim($line);
            if (strlen($line) < 4) {
                continue;
            }
            $code = substr($line, 0, 3);
            if (! ctype_upper($code) || ! in_array($code, self::ELEMENT_IDS, true)) {
                continue;
            }
            $val = trim(substr($line, 3));
            if ($val !== '') {
                $out[$code] = $val;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $fields
     * @return list<array{label: string, value: string}>
     */
    private static function buildDisplayRows(array $fields): array
    {
        $map = [
            'DCS' => 'Last name',
            'DAC' => 'First name',
            'DAD' => 'Middle name',
            'DAG' => 'Street address',
            'DAI' => 'City',
            'DAJ' => 'State / province',
            'DAK' => 'Postal code',
            'DAQ' => 'License / customer ID',
            'DBB' => 'Date of birth',
            'DBA' => 'Document expiration',
            'DBD' => 'Document issue date',
            'DBC' => 'Sex',
            'DAY' => 'Eye color',
            'DAU' => 'Height',
        ];

        $rows = [];
        foreach ($map as $code => $label) {
            if (! isset($fields[$code])) {
                continue;
            }
            $v = $fields[$code];
            if (in_array($code, ['DBB', 'DBA', 'DBD'], true)) {
                $v = self::formatAamvaDate($v) ?? $v;
            }
            $rows[] = ['label' => $label, 'value' => $v];
        }

        return $rows;
    }

    private static function guessCountry(array $fields): ?string
    {
        $c = strtoupper(substr($fields['DCG'] ?? '', 0, 3));
        if ($c === 'CAN' || $c === 'CDN') {
            return 'CA';
        }
        if ($c === 'USA' || $c === 'US') {
            return 'US';
        }

        $state = strtoupper(substr($fields['DAJ'] ?? '', 0, 2));
        if (strlen($state) === 2 && ctype_alpha($state)) {
            $ca = ['AB', 'BC', 'MB', 'NB', 'NL', 'NS', 'NT', 'NU', 'ON', 'PE', 'QC', 'SK', 'YT'];
            if (in_array($state, $ca, true)) {
                return 'CA';
            }

            return 'US';
        }

        return null;
    }

    private static function formatAamvaDate(?string $v): ?string
    {
        if ($v === null || $v === '') {
            return null;
        }
        $v = preg_replace('/\s+/', '', $v) ?? $v;

        if (preg_match('/^(\d{2})(\d{2})(\d{4})$/', $v, $m)) {
            return $m[1].'/'.$m[2].'/'.$m[3];
        }
        if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $v, $m)) {
            return $m[2].'/'.$m[3].'/'.$m[1];
        }

        return null;
    }

    private static function titleCaseName(string $s): string
    {
        $s = trim($s);
        if ($s === '') {
            return '';
        }
        $lower = strtolower($s);

        return mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');
    }
}
