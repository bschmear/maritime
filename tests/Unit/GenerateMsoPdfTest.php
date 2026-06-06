<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domain\MsoRecord\Support\GenerateMsoPdf;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use setasign\Fpdi\Fpdi;

class GenerateMsoPdfTest extends TestCase
{
    public function test_build_filled_pdf_uses_stored_page_sizes_in_points(): void
    {
        $sourcePath = $this->createLetterPdf();
        $fields = [[
            'type' => 'free_text',
            'page' => 1,
            'x' => 0.1,
            'y' => 0.5,
            'width' => 0.25,
            'height' => 0.04,
            'value' => 'Anchor',
            'font_size' => 10,
        ]];
        $pageSizes = [
            1 => ['width' => 612, 'height' => 792],
        ];

        $outputPath = $this->invokeBuildFilledPdf($sourcePath, $fields, $pageSizes);

        $this->assertFileExists($outputPath);

        $reader = new Fpdi('P', 'pt');
        $reader->setSourceFile($outputPath);
        $size = $reader->getTemplateSize($reader->importPage(1));

        $this->assertEqualsWithDelta(612, $size['width'], 1);
        $this->assertEqualsWithDelta(792, $size['height'], 1);

        @unlink($sourcePath);
        @unlink($outputPath);
    }

    private function createLetterPdf(): string
    {
        $pdf = new Fpdi('P', 'pt', 'Letter');
        $pdf->AddPage();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 12, 'MSO Source');
        $path = sys_get_temp_dir().'/mso-letter-'.uniqid('', true).'.pdf';
        $pdf->Output($path, 'F');

        return $path;
    }

    /**
     * @param  list<array<string, mixed>>  $fields
     * @param  array<int, array{width: float|int, height: float|int}>  $pageSizes
     */
    private function invokeBuildFilledPdf(string $sourcePath, array $fields, array $pageSizes): string
    {
        $reflection = new ReflectionClass(GenerateMsoPdf::class);
        $method = $reflection->getMethod('buildFilledPdf');
        $method->setAccessible(true);

        /** @var string $outputPath */
        $outputPath = $method->invoke(null, $sourcePath, $fields, null, $pageSizes);

        return $outputPath;
    }
}
