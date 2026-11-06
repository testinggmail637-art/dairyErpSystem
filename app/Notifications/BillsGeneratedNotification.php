<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** Bell notification when bulk bill generation has finished. */
class BillsGeneratedNotification extends Notification
{
    public function __construct(
        private readonly int $created,
        private readonly int $skipped,
        private readonly string $from,
        private readonly string $to,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'message' => __(':count draft bills made for :from – :to', [
                'count' => $this->created, 'from' => fmt_date($this->from), 'to' => fmt_date($this->to),
            ]).($this->skipped ? ' '.__('(:n skipped)', ['n' => $this->skipped]) : '').'. '.__('Review and finalize them.'),
            'url' => route('bills.index', ['status' => 'draft'], false),
            'icon' => 'bi-receipt',
        ];
    }
}
