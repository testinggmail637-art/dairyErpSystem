<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Animal photos (spec Section 6: public disk): animals/{farm_id}/{uuid}.jpg scaled down to 1200 px, plus a 200 px
 * thumbnail {uuid}_thumb.jpg for lists — a phone photo of several MB becomes ~150 KB and ~10 KB.
 */
final class AnimalPhotoStorage
{
    public const DISK = 'public';

    public const MAX_SIDE = 1200;

    public const THUMB_SIDE = 200;

    public static function store(UploadedFile $file, int $farmId): string
    {
        $path = sprintf('animals/%d/%s.jpg', $farmId, Str::uuid());
        $manager = ImageManager::gd();

        $image = $manager->read($file->getRealPath());
        if ($image->width() > self::MAX_SIDE || $image->height() > self::MAX_SIDE) {
            $image->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
        }
        Storage::disk(self::DISK)->put($path, (string) $image->toJpeg(82));

        $thumb = $manager->read($file->getRealPath())->cover(self::THUMB_SIDE, self::THUMB_SIDE);
        Storage::disk(self::DISK)->put(self::thumbPath($path), (string) $thumb->toJpeg(80));

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete([$path, self::thumbPath($path)]);
        }
    }

    public static function url(?string $path, bool $thumb = false): ?string
    {
        return $path ? Storage::disk(self::DISK)->url($thumb ? self::thumbPath($path) : $path) : null;
    }

    public static function thumbPath(string $path): string
    {
        return preg_replace('/\.jpg$/', '_thumb.jpg', $path) ?? $path;
    }
}
