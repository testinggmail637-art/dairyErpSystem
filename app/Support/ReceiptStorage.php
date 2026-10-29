<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;

/**
 * Receipts / bills attached to records (spec 7.1): private disk, receipts/{farm_id}/{Y}/{m}/{uuid}.{ext}.
 * Images wider or taller than 1600 px are scaled down; PDFs are stored as they are. Served only through
 * authorized routes, never by a public URL.
 */
final class ReceiptStorage
{
    public const DISK = 'local';

    public const MAX_SIDE = 1600;

    public static function store(UploadedFile $file, int $farmId): string
    {
        // Extension from the file's content (what validation checked), never from the name the browser sent.
        $extension = match ((string) $file->extension()) {
            'jpg', 'jpeg' => 'jpg',
            'png' => 'png',
            default => 'pdf',
        };
        $path = sprintf('receipts/%d/%s/%s.%s', $farmId, now()->format('Y/m'), Str::uuid(), $extension);

        if (in_array($extension, ['jpg', 'png'], true)) {
            $image = ImageManager::gd()->read($file->getRealPath());

            if ($image->width() > self::MAX_SIDE || $image->height() > self::MAX_SIDE) {
                $image->scaleDown(self::MAX_SIDE, self::MAX_SIDE);
            }

            Storage::disk(self::DISK)->put($path, (string) ($extension === 'png' ? $image->toPng() : $image->toJpeg(85)));

            return $path;
        }

        Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public static function delete(?string $path): void
    {
        if ($path) {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
