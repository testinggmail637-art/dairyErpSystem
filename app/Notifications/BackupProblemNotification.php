<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/**
 * In-app (bell) alert to owners when the daily backup fails, cleanup fails or the latest backup is too old.
 * Data keys follow the bell convention: message, url, icon (see DECISIONS #15).
 */
class BackupProblemNotification extends Notification
{
    public function __construct(private readonly string $message) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * @return array<string, string|null>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => $this->message,
            'url' => null,
            'icon' => 'bi-exclamation-octagon',
        ];
    }
}
