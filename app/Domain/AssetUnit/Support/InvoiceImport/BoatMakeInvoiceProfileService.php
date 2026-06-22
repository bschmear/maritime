<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use App\Domain\BoatMake\Models\BoatMake;
use App\Domain\BoatMake\Models\BoatMakeInvoiceImportProfile;

class BoatMakeInvoiceProfileService
{
    public function instructionsFor(BoatMake $brand): ?string
    {
        $profile = $brand->invoiceImportProfile;
        if ($profile?->ai_instructions) {
            $text = trim($profile->ai_instructions);

            return $text !== '' ? $text : null;
        }

        return null;
    }

    public function saveInstructions(BoatMake $brand, ?string $instructions): BoatMakeInvoiceImportProfile
    {
        $text = trim((string) $instructions);

        return BoatMakeInvoiceImportProfile::query()->updateOrCreate(
            ['boat_make_id' => $brand->id],
            ['ai_instructions' => $text !== '' ? $text : null],
        );
    }
}
