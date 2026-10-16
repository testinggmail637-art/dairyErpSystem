<?php

namespace App\Http\Controllers\Account;

use App\Http\Controllers\Controller;
use App\Support\Exporter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Download a queued export file. Each user can only download files made for them (exports/{user_id}/). */
class ExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $file): StreamedResponse
    {
        $path = Exporter::path($request->user(), $file);

        abort_unless(Storage::disk(Exporter::DISK)->exists($path), 404, __('This export has expired. Please export again.'));

        return Storage::disk(Exporter::DISK)->download($path, basename($path));
    }
}
