<?php

declare(strict_types=1);

namespace App\Domain\BoatShowEvent\Support;

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;

final class BoatShowQrCode
{
    /**
     * PNG data URI suitable for {@code <img src="...">}.
     */
    public static function dataUriForUrl(string $url, int $size = 240): string
    {
        $result = Builder::create()
            ->writer(new PngWriter)
            ->data($url)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(ErrorCorrectionLevel::Medium)
            ->size($size)
            ->margin(8)
            ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
            ->build();

        return $result->getDataUri();
    }
}
