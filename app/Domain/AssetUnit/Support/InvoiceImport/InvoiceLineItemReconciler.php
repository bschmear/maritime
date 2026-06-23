<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

/**
 * Expands under-counted AI line_items to match invoice_lines quantities and PDF identifiers.
 */
class InvoiceLineItemReconciler
{
    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @param  list<array<string, mixed>>  $invoiceLines
     * @return list<array<string, mixed>>
     */
    public function reconcile(array $lineItems, array $invoiceLines, string $pdfText): array
    {
        if ($invoiceLines === []) {
            return $lineItems;
        }

        $identifiers = $this->parseIdentifiersFromPdf($pdfText);
        $identifierCursor = 0;
        $reconciled = [];

        foreach ($this->sortInvoiceLines($invoiceLines) as $invoiceLine) {
            $qty = (int) round((float) ($invoiceLine['quantity'] ?? 0));
            if ($qty <= 0) {
                continue;
            }

            $identifiersForLine = [];
            if ($identifiers !== []) {
                $identifiersForLine = array_slice($identifiers, $identifierCursor, $qty);
                $identifierCursor += count($identifiersForLine);
            }

            $existing = $this->lineItemsForInvoiceLine($lineItems, $invoiceLine);
            foreach ($this->reconcileInvoiceLine($existing, $invoiceLine, $identifiersForLine, $qty) as $row) {
                $reconciled[] = $row;
            }
        }

        if ($reconciled === []) {
            return $lineItems;
        }

        foreach ($reconciled as $index => &$row) {
            $row['row_index'] = $index;
        }
        unset($row);

        return $reconciled;
    }

    /**
     * @return list<array{type: 'hin'|'serial', value: string}>
     */
    public function parseIdentifiersFromPdf(string $pdfText): array
    {
        $identifiers = [];

        if (preg_match_all('/\(([A-Z0-9][A-Z0-9,\s]{8,})\)/', $pdfText, $parenMatches)) {
            foreach ($parenMatches[1] as $group) {
                foreach (preg_split('/\s*,\s*/', $group) as $token) {
                    $token = strtoupper(trim($token));
                    if ($token !== '' && $this->looksLikeUnitIdentifier($token)) {
                        $identifiers[] = ['type' => 'hin', 'value' => $token];
                    }
                }
            }
        }

        if (preg_match_all('/\bserial\s*#?\s*([A-Z0-9][A-Z0-9-]{3,})\b/i', $pdfText, $serialMatches)) {
            foreach ($serialMatches[1] as $serial) {
                $serial = strtoupper(trim($serial));
                if ($serial !== '') {
                    $identifiers[] = ['type' => 'serial', 'value' => $serial];
                }
            }
        }

        $seen = [];
        $unique = [];
        foreach ($identifiers as $entry) {
            $key = $entry['type'].':'.$entry['value'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $unique[] = $entry;
        }

        return $unique;
    }

    protected function looksLikeUnitIdentifier(string $token): bool
    {
        return (bool) preg_match('/^[A-Z0-9]{8,}$/', $token);
    }

    /**
     * @param  list<array<string, mixed>>  $invoiceLines
     * @return list<array<string, mixed>>
     */
    protected function sortInvoiceLines(array $invoiceLines): array
    {
        $sorted = $invoiceLines;
        usort($sorted, fn (array $a, array $b) => ($a['source_line_index'] ?? 0) <=> ($b['source_line_index'] ?? 0));

        return $sorted;
    }

    /**
     * @param  list<array<string, mixed>>  $lineItems
     * @return list<array<string, mixed>>
     */
    protected function lineItemsForInvoiceLine(array $lineItems, array $invoiceLine): array
    {
        $sourceIndex = (int) ($invoiceLine['source_line_index'] ?? -1);
        $itemCode = $this->normalizeItemCode($invoiceLine['item_code'] ?? null);

        $matches = array_values(array_filter(
            $lineItems,
            function (array $row) use ($sourceIndex, $itemCode): bool {
                if ($sourceIndex >= 0 && (int) ($row['source_line_index'] ?? -1) === $sourceIndex) {
                    return true;
                }

                if ($itemCode === null) {
                    return false;
                }

                return $this->normalizeItemCode($row['item_code'] ?? null) === $itemCode;
            },
        ));

        if ($matches !== []) {
            return $matches;
        }

        $description = $this->normalizeText($invoiceLine['description'] ?? null);
        if ($description === null) {
            return [];
        }

        return array_values(array_filter(
            $lineItems,
            fn (array $row) => $this->normalizeText($row['description'] ?? null) === $description,
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $existing
     * @param  list<array{type: 'hin'|'serial', value: string}>  $identifiers
     * @return list<array<string, mixed>>
     */
    protected function reconcileInvoiceLine(array $existing, array $invoiceLine, array $identifiers, int $qty): array
    {
        $template = $existing[0] ?? $this->templateFromInvoiceLine($invoiceLine);

        if ($identifiers !== []) {
            $byIdentifier = [];
            $withoutIdentifier = [];

            foreach ($existing as $row) {
                $identifier = $this->rowIdentifier($row);
                if ($identifier !== null) {
                    $byIdentifier[$identifier] = $row;
                } else {
                    $withoutIdentifier[] = $row;
                }
            }

            $out = [];
            foreach ($identifiers as $entry) {
                $value = $entry['value'];
                if (isset($byIdentifier[$value])) {
                    $out[] = $byIdentifier[$value];

                    continue;
                }

                $clone = $template;
                $this->applyIdentifierToRow($clone, $entry);
                $out[] = $clone;
            }

            while (count($out) < $qty && $withoutIdentifier !== []) {
                $out[] = array_shift($withoutIdentifier);
            }

            while (count($out) < $qty) {
                $clone = $template;
                $clone['hin'] = null;
                $clone['serial_number'] = null;
                $out[] = $clone;
            }

            return array_slice($out, 0, $qty);
        }

        $out = $existing;
        while (count($out) < $qty) {
            $clone = $template;
            $clone['hin'] = null;
            $clone['serial_number'] = null;
            $out[] = $clone;
        }

        return array_slice($out, 0, $qty);
    }

    /**
     * @return array<string, mixed>
     */
    protected function templateFromInvoiceLine(array $invoiceLine): array
    {
        return [
            'source_line_index' => (int) ($invoiceLine['source_line_index'] ?? 0),
            'item_code' => $invoiceLine['item_code'] ?? null,
            'description' => $invoiceLine['description'] ?? null,
            'extracted_model' => null,
            'extracted_variant' => null,
            'unit_price' => round((float) ($invoiceLine['unit_price'] ?? 0), 2),
            'hin' => null,
            'serial_number' => null,
            'asset_id' => null,
            'asset_variant_id' => null,
            'mapping_confidence' => 0.0,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    protected function rowIdentifier(array $row): ?string
    {
        $hin = strtoupper(trim((string) ($row['hin'] ?? '')));
        if ($hin !== '') {
            return $hin;
        }

        $serial = strtoupper(trim((string) ($row['serial_number'] ?? '')));
        if ($serial !== '') {
            return $serial;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array{type: 'hin'|'serial', value: string}  $entry
     */
    protected function applyIdentifierToRow(array &$row, array $entry): void
    {
        if ($entry['type'] === 'serial') {
            $row['serial_number'] = $entry['value'];
            $row['hin'] = null;

            return;
        }

        $row['hin'] = $entry['value'];
        $row['serial_number'] = null;
    }

    protected function normalizeItemCode(mixed $code): ?string
    {
        if ($code === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', strtoupper(trim((string) $code)));

        return $normalized !== '' ? $normalized : null;
    }

    protected function normalizeText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = preg_replace('/\s+/', ' ', strtoupper(trim((string) $value)));

        return $normalized !== '' ? $normalized : null;
    }
}
