<?php

declare(strict_types=1);

namespace App\Support\OpenAi;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class OpenAiModelResolver
{
    public static function resolve(string $requestType): string
    {
        if (! in_array($requestType, OpenAiRequestType::ALL, true)) {
            throw new InvalidArgumentException("Unknown OpenAI request type [{$requestType}].");
        }

        return (string) config("openai_models.{$requestType}", 'gpt-5-mini');
    }

    /**
     * Pick document_extract vs messy_ocr from PDF text quality.
     */
    public static function forInvoicePdfText(string $pdfText): string
    {
        if (! self::isPdfTextSufficient($pdfText)) {
            Log::info('OpenAI invoice import: using messy_ocr model (insufficient PDF text).', [
                'text_length' => mb_strlen(preg_replace('/\s+/', '', $pdfText) ?? ''),
                'model' => self::resolve(OpenAiRequestType::MessyOcr),
            ]);

            return self::resolve(OpenAiRequestType::MessyOcr);
        }

        return self::resolve(OpenAiRequestType::DocumentExtract);
    }

    public static function isPdfTextSufficient(string $text): bool
    {
        $min = (int) config('openai_models.invoice_min_text_length', 80);

        return mb_strlen(preg_replace('/\s+/', '', $text) ?? '') >= $min;
    }

    /**
     * GPT-5 / reasoning models reject explicit temperature values other than the default.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public static function sanitizeChatPayload(array $payload): array
    {
        if (! self::modelSupportsCustomTemperature((string) ($payload['model'] ?? ''))) {
            unset($payload['temperature']);
        }

        return $payload;
    }

    public static function modelSupportsCustomTemperature(string $model): bool
    {
        $model = strtolower(trim($model));
        if ($model === '') {
            return true;
        }

        return ! preg_match('/^(gpt-5|o\d)/', $model);
    }
}
