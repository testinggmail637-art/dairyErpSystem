<?php

namespace App\Support;

use App\Jobs\NotifyExportReady;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel exports for every list/report (performance rule: big jobs in the background).
 * Up to QUEUE_ABOVE rows the file downloads at once; larger exports are written by a queued job into
 * storage/app/private/exports/{user_id}/ and the user gets a bell notification with the download link.
 */
final class Exporter
{
    /** Default; config('dairy.exports.queue_above') overrides it. */
    public const QUEUE_ABOVE = 5000;

    public const DISK = 'local';

    public static function download(object $export, int $rows, string $baseName, string $title, User $user): BinaryFileResponse|RedirectResponse
    {
        $fileName = $baseName.'-'.now()->format('Ymd-His').'.xlsx';

        if ($rows <= (int) config('dairy.exports.queue_above', self::QUEUE_ABOVE)) {
            return Excel::download($export, $fileName);
        }

        $fileName = Str::beforeLast($fileName, '.xlsx').'-'.Str::lower(Str::random(6)).'.xlsx';

        Excel::queue($export, self::path($user, $fileName), self::DISK)
            ->chain([new NotifyExportReady($user, $fileName, $title)]);

        return back()->with('info', __(':rows rows is a big file — it is being prepared. You will get a notification with the download link in a minute.', ['rows' => Format::number((string) $rows, 0)]));
    }

    /** Where a user's queued export files live (each user can only download their own). */
    public static function path(User $user, string $fileName): string
    {
        return 'exports/'.$user->getKey().'/'.basename($fileName);
    }
}
