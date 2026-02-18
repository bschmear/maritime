<?php

namespace App\Http\Controllers\Concerns;

use App\Domain\Document\Models\Document;
use Illuminate\Support\Facades\Storage;

trait HasImageSupport
{
    /**
     * Get validated image URLs for a record based on schema definition.
     */
    protected function getImageUrls($record, $fieldsSchema)
    {
        $urls = [];
        $cdnUrl = config('filesystems.disks.s3.cdn_url');

        foreach ($fieldsSchema as $fieldKey => $fieldDef) {
            if (isset($fieldDef['type']) && $fieldDef['type'] === 'image') {
                $documentId = $record->{$fieldKey};
                if ($documentId) {
                    $document = Document::find($documentId);
                    if ($document && $document->file) {
                        if ($cdnUrl) {
                            $urls[$fieldKey] = rtrim($cdnUrl, '/') . '/' . $document->file;
                        } else {
                            $urls[$fieldKey] = Storage::disk('s3')->temporaryUrl(
                                $document->file,
                                now()->addDays(7)
                            );
                        }
                    }
                }
            }
        }
        return $urls;
    }
}
