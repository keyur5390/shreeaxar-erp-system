<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class StorageService
{
    public function storeImage(UploadedFile $file, string $folder): string
    {
        return $file->store($folder, 'public');
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

        return 'data:image/webp;base64,'.base64_encode(Storage::disk('public')->get($path));
    }
}
