<?php

declare(strict_types=1);

namespace App\Domain\BoatShow\Support;

use App\Domain\BoatShow\Models\BoatShow;
use App\Domain\Document\Models\Document;
use Illuminate\Support\Facades\Storage;

final class BoatShowDocumentUrl
{
    public static function logoUrlForShow(BoatShow $show): ?string
    {
        $logoId = $show->logo;

        return is_numeric($logoId) && (int) $logoId > 0
            ? self::documentUrl((int) $logoId)
            : null;
    }

    public static function documentUrl(int $documentId): ?string
    {
        $document = Document::query()->find($documentId);
        if ($document === null || ! is_string($document->file) || trim($document->file) === '') {
            return null;
        }

        $cdnUrl = config('filesystems.disks.s3.cdn_url');
        if (is_string($cdnUrl) && trim($cdnUrl) !== '') {
            return rtrim($cdnUrl, '/').'/'.ltrim($document->file, '/');
        }

        return Storage::disk('s3')->temporaryUrl(
            $document->file,
            now()->addDays(7),
        );
    }
}
