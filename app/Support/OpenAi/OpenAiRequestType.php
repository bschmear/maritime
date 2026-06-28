<?php

declare(strict_types=1);

namespace App\Support\OpenAi;

/**
 * Logical AI request categories for model + cost routing.
 */
final class OpenAiRequestType
{
    public const BoatSpecs = 'boat_specs';

    public const DocumentExtract = 'document_extract';

    public const MessyOcr = 'messy_ocr';

    /** @var list<string> */
    public const ALL = [
        self::BoatSpecs,
        self::DocumentExtract,
        self::MessyOcr,
    ];
}
