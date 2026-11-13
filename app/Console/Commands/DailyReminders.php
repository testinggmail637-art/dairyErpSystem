<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Models\User;
use App\Notifications\DailyReminderNotification;
use App\Services\DailyReminderService;
use App\Support\CurrentFarm;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;

/** Daily herd / feed / sales reminders (spec Phase 5) to the farm's active users with each line's permission; once a day per farm. */
class DailyReminders extends Command
{
    protected $signature = 'reminders:daily {--farm= : farm id}';

    protected $description = 'Notify users about due vaccinations, expected calvings, low feed, milk drops and overdue bills';

    public function handle(CurrentFarm $currentFarm): int
    {
        $farms = Farm::query()->active()->when($this->option('farm'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($farms as $farm) {
            if (! Cache::add("reminders:daily:{$farm->id}:".today()->toDateString(), true, now()->addDays(2))) {
                continue;
            }
            $currentFarm->set($farm);
            $lines = app(DailyReminderService::class)->lines();

            foreach ($lines as $line) {
                $users = User::permission($line['permission'])->active()->whereHas('farms', fn ($q) => $q->whereKey($farm->id))->get();
                Notification::send($users, new DailyReminderNotification($line['message'], $line['url'], $line['icon']));
            }
            $this->info("{$farm->name}: ".count($lines).' reminders.');
        }

        return self::SUCCESS;
    }
}
