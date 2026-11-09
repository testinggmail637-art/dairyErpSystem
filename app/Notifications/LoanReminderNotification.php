<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Bell notification from loans:reminders — an instalment / card bill / interest debit / renewal coming up. */
class LoanReminderNotification extends Notification
{
    public function __construct(private readonly string $message, private readonly int $loanId) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return ['message' => $this->message, 'url' => route('loans.show', $this->loanId, false), 'icon' => 'bi-bank'];
    }
}
