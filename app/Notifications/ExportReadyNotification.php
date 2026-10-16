<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Bell notification with a download link when a queued (large) export file is ready. */
class ExportReadyNotification extends Notification
{
    public function __construct(private readonly string $title, private readonly string $url) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':title is ready — click to download.', ['title' => $this->title]),
            'url' => $this->url,
            'icon' => 'bi-file-earmark-spreadsheet',
        ];
    }
}
