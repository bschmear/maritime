<?php

namespace App\Actions;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\File;
use Intervention\Image\Laravel\Facades\Image;
use Illuminate\Support\Str;
use Symfony\Component\Mime\MimeTypes;
use Exception;

class PublicStorage
{
    public function store(UploadedFile $file, string $directory, ?int $resizeWidth = null, $existingFile = null, $crop = false, $deleteOld = true, bool $isPrivate = false): array
    {
        $mime = $file->getMimeType(); // e.g., image/jpeg
        $extensions = MimeTypes::getDefault()->getExtensions($mime);
        $extension = $extensions[0] ?? 'jpg'; // fallback to jpg

        $filename = Str::uuid() . '.' . $extension;
        $prefix = $isPrivate ? 'private' : 'public';
        $key = "{$prefix}/{$directory}/{$filename}";

        if (Str::startsWith($file->getMimeType(), 'image/') && $resizeWidth) {
            // Resize the image if needed using Intervention Image
            $image = Image::read($file);

            $width = $image->width();
            $height = $image->height();

            $dominantDimension = max($width, $height);
            $scaleRatio = $resizeWidth / $dominantDimension;
            $newWidth = round($width * $scaleRatio);
            $newHeight = round($height * $scaleRatio);

            // Resize image
            $resizedImage = $image->resize($newWidth, $newHeight);

            // Optionally crop to square
            if ($crop) {
                $image = $resizedImage->crop(
                    width: $resizeWidth,
                    height: $resizeWidth,
                    position: 'center',
                );
            } else {
                $image = $resizedImage;
            }

            $tempDir = storage_path('app/temp');
            File::ensureDirectoryExists($tempDir);

            $tempPath = $tempDir . '/' . basename($key);
            $image->save($tempPath);

            $filePath = new \Illuminate\Http\File($tempPath);

            // Set Cache-Control header to cache the image for 1 week
            $cacheControl = 'public, max-age=604800';

            // Upload the image to S3 with Cache-Control header
            try {
                $uploadedImage = Storage::disk('s3')->putFileAs(
                    "{$prefix}/{$directory}",
                    $filePath,
                    $filename,
                    [
                        'CacheControl' => $cacheControl
                    ]
                );
            } catch (Exception $e) {
                dd($e);
            }

            // If upload was successful, delete the existing file from S3
            if ($uploadedImage) {
                if ($existingFile && Storage::disk('s3')->exists($existingFile)) {
                    if ($deleteOld) {
                        Storage::disk('s3')->delete($existingFile);
                    }
                }
            }

            // Clean up temporary local file
            unlink($tempPath);

        } else {
            Storage::disk('s3')->put($key, file_get_contents($file), $isPrivate ? 'private' : 'public');
        }

        return [
            'key' => $key,
            'file_name' => $filename,
            'file_extension' => $extension,
            'file_size' => $file->getSize(), // Use original file size as approximation or valid for non-resized
            'mime_type' => $mime,
            'display_name' => $file->getClientOriginalName(),
        ];
    }


    public function storeBlog(UploadedFile $file, string $directory, ?int $resizeWidth = null, $existingFile = null, $crop = false, $deleteOld = true): string
    {
        // Generate a unique filename for the new image
        $mime = $file->getMimeType(); // e.g., image/jpeg
        $extensions = MimeTypes::getDefault()->getExtensions($mime);
        $extension = $extensions[0] ?? 'jpg';

        $filename = Str::uuid() . '.' . $extension;
        $key = "public/{$directory}/{$filename}";

        if (Str::startsWith($file->getMimeType(), 'image/') && $resizeWidth) {
            // Resize the image if needed using Intervention Image
            $image = Image::read($file);

            $resizedImage = $image->scale(width: 800);

            $image = $resizedImage->crop(
                width: 800,
                height: 450,
                position: 'center',
            );

            $tempDir = storage_path('app/temp');
            File::ensureDirectoryExists($tempDir);

            $tempPath = $tempDir . '/' . basename($key);
            $image->save($tempPath);

            $filePath = new \Illuminate\Http\File($tempPath);

            $cacheControl = 'public, max-age=604800';

            // Upload the image to S3 with Cache-Control header
            $uploadedImage = Storage::disk('s3')->putFileAs(
                "public/{$directory}",
                $filePath,
                $filename,
                [
                    'CacheControl' => $cacheControl
                ]
            );

            // If upload was successful, delete the existing file from S3
            if ($uploadedImage) {
                if ($existingFile && Storage::disk('s3')->exists($existingFile)) {
                    if ($deleteOld) {
                        Storage::disk('s3')->delete($existingFile);
                    }
                }
            }

            // Clean up temporary local file
            unlink($tempPath);
        } else {
            // If it's not an image or no resizing is needed, upload the file as is
            Storage::disk('s3')->put($key, file_get_contents($file), 'public');
        }

        return $key;
    }

    public function url(string $key): string
    {
        return Storage::disk('s3')->url($key);
    }

    private function generateFileName(UploadedFile $file, bool $randomName = false): string
    {
        $timestamp = time();
        $mime = $file->getMimeType(); // e.g., image/jpeg
        $extensions = MimeTypes::getDefault()->getExtensions($mime);
        $extension = $extensions[0] ?? 'jpg';

        if ($randomName) {
            $randomString = Str::random(6);
            return $randomString . '-' . $timestamp . '.' . $extension;
        }

        $originalName = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        return $originalName . '-' . $timestamp . '.' . $extension;
    }
}
