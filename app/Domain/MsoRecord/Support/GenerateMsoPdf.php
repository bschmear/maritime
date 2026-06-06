<?php

declare(strict_types=1);

namespace App\Domain\MsoRecord\Support;

use App\Domain\Document\Actions\CreateDocument;
use App\Domain\Document\Models\Document;
use App\Domain\MsoRecord\Models\MsoRecord;
use App\Domain\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Fpdi;

final class GenerateMsoPdf
{
    public static function handle(MsoRecord $record, ?User $assignedUser = null): Document
    {
        $source = $record->sourceDocument;
        if (! $source?->file || ! Storage::disk('s3')->exists($source->file)) {
            throw new RuntimeException('Source MSO document is missing.');
        }

        $details = MsoRecordDetails::normalize($record->details);
        $assignedUserId = $details['assigned_user_id'] ?? $record->created_by_id;
        $user = $assignedUser ?? ($assignedUserId ? User::query()->find((int) $assignedUserId) : null);
        $fields = MsoValueResolver::hydrateFieldValues(
            MsoRecordDetails::fields($record->details),
            $record,
            $user,
        );

        $sourcePath = self::localTempCopy($source->file, 'mso-source-');
        $outputPath = self::buildFilledPdf($sourcePath, $fields, $user);

        try {
            $filename = 'mso-filled-'.$record->id.'-'.time().'.pdf';
            $uploadedFile = new UploadedFile(
                $outputPath,
                $filename,
                'application/pdf',
                null,
                true,
            );

            $result = (new CreateDocument)([
                'file' => $uploadedFile,
                'display_name' => 'MSO #'.$record->id.' (filled)',
                'description' => 'Generated MSO for deal line item '.$record->transaction_line_item_id,
                'created_by_id' => current_tenant_user_id(),
            ]);

            if (! ($result['success'] ?? false) || ! $result['record']) {
                throw new RuntimeException($result['message'] ?? 'Failed to store generated MSO PDF.');
            }

            /** @var Document $document */
            $document = $result['record'];

            $record->output_document_id = $document->id;
            $record->save();

            return $document;
        } finally {
            @unlink($sourcePath);
            @unlink($outputPath);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     */
    private static function buildFilledPdf(string $sourcePath, array $fields, ?User $user): string
    {
        $pdf = new Fpdi;
        $pageCount = $pdf->setSourceFile($sourcePath);

        $fieldsByPage = collect($fields)->groupBy(fn (array $field) => (int) ($field['page'] ?? 1));

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['orientation'] ?? 'P';
            $width = (float) $size['width'];
            $height = (float) $size['height'];

            $pdf->AddPage($orientation, [$width, $height]);
            $pdf->useTemplate($templateId);

            foreach ($fieldsByPage->get($pageNo, collect()) as $field) {
                self::renderField($pdf, $field, $width, $height, $user);
            }
        }

        $outputPath = sys_get_temp_dir().'/'.Str::uuid().'.pdf';
        $pdf->Output($outputPath, 'F');

        return $outputPath;
    }

    /**
     * @param  array<string, mixed>  $field
     */
    private static function renderField(Fpdi $pdf, array $field, float $pageWidth, float $pageHeight, ?User $user): void
    {
        $x = (float) ($field['x'] ?? 0) * $pageWidth;
        $y = (float) ($field['y'] ?? 0) * $pageHeight;
        $boxWidth = max(1.0, (float) ($field['width'] ?? 0.2) * $pageWidth);
        $boxHeight = max(1.0, (float) ($field['height'] ?? 0.04) * $pageHeight);
        $fontSize = (int) ($field['font_size'] ?? 10);
        $type = (string) ($field['type'] ?? 'free_text');
        $value = (string) ($field['value'] ?? '');

        if ($type === 'user_signature') {
            $imagePath = MsoValueResolver::signatureImagePath($user);
            if ($imagePath && Storage::disk('s3')->exists($imagePath)) {
                $localImage = self::localTempCopy($imagePath, 'mso-sig-');
                try {
                    $pdf->Image($localImage, $x, $y, $boxWidth, $boxHeight);
                } finally {
                    @unlink($localImage);
                }

                return;
            }
        }

        if ($value === '') {
            return;
        }

        $pdf->SetFont('Arial', '', $fontSize);
        $pdf->SetXY($x, $y);
        $pdf->MultiCell($boxWidth, max(4.0, $boxHeight / 2), $value, 0, 'L');
    }

    private static function localTempCopy(string $s3Path, string $prefix): string
    {
        $extension = pathinfo($s3Path, PATHINFO_EXTENSION) ?: 'bin';
        $localPath = sys_get_temp_dir().'/'.$prefix.Str::uuid().'.'.$extension;
        file_put_contents($localPath, Storage::disk('s3')->get($s3Path));

        return $localPath;
    }
}
