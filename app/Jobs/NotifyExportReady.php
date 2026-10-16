<?php

namespace App\Jobs;

use App\Models\User;
use App\Notifications\ExportReadyNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Runs after a queued export has written its file: tells the user in the bell, with a download link. */
class NotifyExportReady implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly User $user,
        public readonly string $fileName,
        public readonly string $title,
    ) {}

    public function handle(): void
    {
        $this->user->notify(new ExportReadyNotification(
            $this->title,
            route('exports.download', ['file' => $this->fileName], false),
        ));
    }
}
