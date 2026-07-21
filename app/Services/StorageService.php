<?php

namespace App\Services;

use App\Exceptions\InvalidImageUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\Drivers\Imagick\Driver as ImagickDriver;
use Intervention\Image\ImageManager;

class StorageService
{
    private const ALLOWED_IMAGE_MIMES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function storeImage(UploadedFile $file, string $folder): string
    {
        $this->assertValidImageMime($file);

        $extension = $this->extensionForMime($file->getMimeType());
        $directory = trim($folder, '/');
        $filename = Str::uuid().'.'.$extension;

        Storage::disk('public')->putFileAs($directory, $file, $filename);

        return $directory.'/'.$filename;
    }

    public function deleteImage(?string $path): void
    {
        if ($path !== null && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }

    public function getUrl(?string $path): ?string
    {
        return $path === null ? null : Storage::disk('public')->url($path);
    }

    public function resizeAndStore(UploadedFile $file, string $folder, int $maxDim = 800): string
    {
        $this->assertValidImageMime($file);

        $manager = $this->resolveImageManager();
        if ($manager === null) {
            Log::warning('Image driver unavailable; storing original upload without resize.', [
                'gd_loaded' => extension_loaded('gd'),
                'imagick_loaded' => extension_loaded('imagick'),
            ]);

            return $this->storeImage($file, $folder);
        }

        $path = trim($folder, '/').'/'.Str::uuid().'.webp';
        $sourcePath = $file->getRealPath() ?: $file->getPathname();
        $encoded = $manager->read($sourcePath)
            ->scaleDown(width: $maxDim, height: $maxDim)
            ->toWebp(85);

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    public function ensureDirectoriesExist(): void
    {
        foreach (['avatars', 'products', 'company', 'temp/pdf-images'] as $directory) {
            Storage::disk('public')->makeDirectory($directory);
        }
    }

    public function resolveBrandLogoForPdf(?string $companyLogoPath = null): ?string
    {
        return $this->preparePdfImage($companyLogoPath, 'images/shreeaxar-logo.png', 400);
    }

    public function preparePdfImage(?string $imageReference, ?string $fallbackPublicPath = null, int $maxDim = 800): ?string
    {
        Storage::disk('public')->makeDirectory('temp/pdf-images');

        $sourcePath = $this->resolveLocalImagePath($imageReference);
        if ($sourcePath === null && $fallbackPublicPath !== null) {
            $candidate = public_path($fallbackPublicPath);
            $sourcePath = is_file($candidate) ? $candidate : null;
        }

        if ($sourcePath === null || ! is_file($sourcePath)) {
            return null;
        }

        return $this->convertImageToPdfPath($sourcePath, $maxDim);
    }

    public function getBase64(?string $path): ?string
    {
        if ($path === null || ! Storage::disk('public')->exists($path)) {
            return null;
        }

        return 'data:'.$this->mimeForExtension(pathinfo($path, PATHINFO_EXTENSION)).';base64,'
            .base64_encode(Storage::disk('public')->get($path));
    }

    public function getBase64FromPublic(string $relativePath): ?string
    {
        $fullPath = public_path($relativePath);

        if (! is_file($fullPath)) {
            return null;
        }

        return 'data:'.$this->mimeForExtension(pathinfo($fullPath, PATHINFO_EXTENSION)).';base64,'
            .base64_encode((string) file_get_contents($fullPath));
    }

    public function resolveBrandLogoBase64(?string $companyLogoPath = null): ?string
    {
        $logo = $this->getBase64($companyLogoPath);

        return $logo ?? $this->getBase64FromPublic('images/shreeaxar-logo.png');
    }

    public function resolveImageBase64(?string $imageReference): ?string
    {
        if ($imageReference === null || trim($imageReference) === '') {
            return null;
        }

        $imageReference = trim($imageReference);

        if (! str_starts_with($imageReference, 'http://') && ! str_starts_with($imageReference, 'https://')) {
            return $this->getBase64($imageReference);
        }

        $storageBaseUrl = rtrim(Storage::disk('public')->url(''), '/').'/';
        if (str_starts_with($imageReference, $storageBaseUrl)) {
            $relativePath = ltrim(substr($imageReference, strlen($storageBaseUrl)), '/');

            return $this->getBase64($relativePath);
        }

        if (preg_match('#/storage/(.+)$#', $imageReference, $matches) === 1) {
            return $this->getBase64($matches[1]);
        }

        return null;
    }

    private function resolveLocalImagePath(?string $imageReference): ?string
    {
        if ($imageReference === null || trim($imageReference) === '') {
            return null;
        }

        $imageReference = trim($imageReference);

        if (! str_starts_with($imageReference, 'http://') && ! str_starts_with($imageReference, 'https://')) {
            if (Storage::disk('public')->exists($imageReference)) {
                return Storage::disk('public')->path($imageReference);
            }

            if (is_file($imageReference)) {
                return $imageReference;
            }

            return null;
        }

        $storageBaseUrl = rtrim(Storage::disk('public')->url(''), '/').'/';
        if (str_starts_with($imageReference, $storageBaseUrl)) {
            $relativePath = ltrim(substr($imageReference, strlen($storageBaseUrl)), '/');
            if (Storage::disk('public')->exists($relativePath)) {
                return Storage::disk('public')->path($relativePath);
            }
        }

        if (preg_match('#/storage/(.+)$#', $imageReference, $matches) === 1) {
            $relativePath = $matches[1];
            if (Storage::disk('public')->exists($relativePath)) {
                return Storage::disk('public')->path($relativePath);
            }
        }

        return null;
    }

    private function convertImageToPdfPath(string $sourcePath, int $maxDim): ?string
    {
        $extension = strtolower(pathinfo($sourcePath, PATHINFO_EXTENSION));
        $pdfSafeExtensions = ['jpg', 'jpeg', 'png'];
        $manager = $this->resolveImageManager();

        if ($manager === null) {
            if (in_array($extension, $pdfSafeExtensions, true)) {
                return $this->normalizePathForDompdf($sourcePath);
            }

            Log::warning('Cannot convert image for PDF without GD or Imagick.', [
                'source' => $sourcePath,
            ]);

            return null;
        }

        try {
            $outputRelative = 'temp/pdf-images/'.Str::uuid().'.jpg';
            $outputPath = Storage::disk('public')->path($outputRelative);

            $encoded = $manager->read($sourcePath)
                ->scaleDown(width: $maxDim, height: $maxDim)
                ->toJpeg(85);

            file_put_contents($outputPath, (string) $encoded);

            return $this->normalizePathForDompdf($outputPath);
        } catch (\Throwable $exception) {
            Log::warning('Failed to prepare image for PDF.', [
                'source' => $sourcePath,
                'error' => $exception->getMessage(),
            ]);

            if (in_array($extension, $pdfSafeExtensions, true)) {
                return $this->normalizePathForDompdf($sourcePath);
            }

            return null;
        }
    }

    private function normalizePathForDompdf(string $path): string
    {
        $realPath = realpath($path);

        return str_replace('\\', '/', $realPath ?: $path);
    }

    private function resolveImageManager(): ?ImageManager
    {
        if (extension_loaded('gd')) {
            return new ImageManager(new GdDriver());
        }

        if (extension_loaded('imagick')) {
            return new ImageManager(new ImagickDriver());
        }

        return null;
    }

    private function mimeForExtension(?string $extension): string
    {
        return match (strtolower((string) $extension)) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            default => 'image/png',
        };
    }

    private function assertValidImageMime(UploadedFile $file): void
    {
        $mime = $file->getMimeType();

        if ($mime === null || ! in_array($mime, self::ALLOWED_IMAGE_MIMES, true)) {
            throw new InvalidImageUploadException();
        }
    }

    private function extensionForMime(string $mime): string
    {
        return match ($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => throw new InvalidImageUploadException(),
        };
    }
}
