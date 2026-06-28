<?php

declare(strict_types=1);

namespace App\Domain\AssetUnit\Support\InvoiceImport;

use Illuminate\Http\UploadedFile;
use RuntimeException;
use Smalot\PdfParser\Parser;

class InvoicePdfTextExtractor
{
    public function __construct(
        private readonly Parser $parser = new Parser,
    ) {}

    public function extractFromUpload(UploadedFile $file): string
    {
        $path = $file->getRealPath();
        if ($path === false) {
            throw new RuntimeException('Unable to read uploaded PDF.');
        }

        return $this->extractFromPath($path);
    }

    public function extractFromPath(string $path): string
    {
        try {
            $pdf = $this->parser->parseFile($path);
            $text = trim((string) $pdf->getText());
        } catch (\Throwable $e) {
            throw new RuntimeException('Failed to parse PDF: '.$e->getMessage(), 0, $e);
        }

        if ($text === '') {
            throw new RuntimeException('No text could be extracted from this PDF. Try a text-based invoice PDF.');
        }

        return $text;
    }

    public function isTextSufficient(string $text): bool
    {
        return mb_strlen(preg_replace('/\s+/', '', $text) ?? '') >= (int) config('openai_models.invoice_min_text_length', config('invoice_import.min_text_length', 80));
    }
}
