<?php

namespace App\Services;

use App\Exceptions\InvalidImageUploadException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
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

        $path = trim($folder, '/').'/'.Str::uuid().'.webp';
        $manager = new ImageManager(new Driver());
        $encoded = $manager->read($file->getRealPath())
            ->scaleDown(width: $maxDim, height: $maxDim)
            ->toWebp(85);

        Storage::disk('public')->put($path, (string) $encoded);

        return $path;
    }

    public function ensureDirectoriesExist(): void
    {
        foreach (['avatars', 'products', 'company'] as $directory) {
            Storage::disk('public')->makeDirectory($directory);
        }
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
