<?php

declare(strict_types=1);

namespace App\Services\Integrations;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use SplFileInfo;
use ZipArchive;

final class WordPressPluginZipBuilder
{
    public const PLUGIN_SLUG = 'helmful-sync';

    public const ZIP_FILENAME = 'helmful-sync.zip';

    public function sourcePath(): string
    {
        return base_path('wordpress-plugin/'.self::PLUGIN_SLUG);
    }

    public function version(): string
    {
        $mainFile = $this->sourcePath().'/'.self::PLUGIN_SLUG.'.php';
        if (! is_file($mainFile)) {
            return '1.0.0';
        }

        $contents = (string) file_get_contents($mainFile);
        if (preg_match('/^\s*\*\s*Version:\s*(.+)$/mi', $contents, $matches)) {
            return trim($matches[1]);
        }

        return '1.0.0';
    }

    /**
     * Build a WordPress-installable zip and return the absolute path to the temp file.
     */
    public function build(): string
    {
        $source = $this->sourcePath();
        if (! is_dir($source)) {
            throw new RuntimeException('WordPress plugin source is not available.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'helmful-sync-');
        if ($zipPath === false) {
            throw new RuntimeException('Could not create a temporary zip file.');
        }

        @unlink($zipPath);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not open zip archive for writing.');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST,
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            $absolute = $file->getPathname();
            $relative = substr($absolute, strlen($source) + 1);
            $zipPathInside = self::PLUGIN_SLUG.'/'.$relative;

            if ($file->isDir()) {
                $zip->addEmptyDir($zipPathInside);

                continue;
            }

            $zip->addFile($absolute, $zipPathInside);
        }

        $zip->close();

        return $zipPath;
    }
}
