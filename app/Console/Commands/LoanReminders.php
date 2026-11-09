<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\User;
use App\Notifications\LoanReminderNotification;
use App\Services\LoanOverviewService;
use App\Support\CurrentFarm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Daily loan reminders (spec 7.4A) to the farm's active users with loans.view; each farm once a day. */
class LoanReminders extends Command
{
    protected $signature = 'loans:reminders {--farm= : farm id}';

    protected $description = 'Notify users about loan instalments, card bills, interest debits and renewals';

    public function handle(CurrentFarm $currentFarm): int
    {
        $farms = Farm::query()->active()->when($this->option('farm'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($farms as $farm) {
            if (! Cache::add("loans:reminded:{$farm->id}:".today()->toDateString(), true, now()->addDays(2))) {
                continue;
            }
            $currentFarm->set($farm);
            $lines = app(LoanOverviewService::class)->reminders();
            $users = $lines ? User::permission('loans.view')->active()->whereHas('farms', fn ($q) => $q->whereKey($farm->id))->get() : collect();

            foreach ($lines as $line) {
                Notification::send($users, new LoanReminderNotification($line['message'], $line['loan']->id));
            }
            $this->info("{$farm->name}: ".count($lines)." loan reminders to {$users->count()} users.");
        }

        return self::SUCCESS;
    }
}
