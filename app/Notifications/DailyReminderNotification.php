<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Bell notification from reminders:daily — vaccinations, calvings, low feed, milk drops or overdue bills. */
class DailyReminderNotification extends Notification
{
    public function __construct(private readonly string $message, private readonly string $url, private readonly string $icon) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => $this->url, 'icon' => $this->icon];
    }
}
