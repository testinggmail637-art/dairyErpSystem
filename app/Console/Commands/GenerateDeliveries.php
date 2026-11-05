<?php

namespace App\Console\Commands;

use App\Models\Farm;
use App\Services\DeliverySheetService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Delivery sheet for a date (spec Section 6): pending rows from active, not-paused subscriptions. Idempotent.
 * --scheduled (every 5 min from the scheduler): today only, each farm once, after its delivery_sheet_generate_time.
 */
class GenerateDeliveries extends Command
{
    protected $signature = 'deliveries:generate {date? : YYYY-MM-DD, default today} {--farm= : farm id} {--scheduled}';

    protected $description = 'Create pending deliveries from subscriptions for a date';

    public function handle(DeliverySheetService $sheets): int
    {
        $scheduled = (bool) $this->option('scheduled');
        $date = $scheduled || ! $this->argument('date') ? now()->toDateString() : $this->argument('date');

        if (! $scheduled && Carbon::canBeCreatedFromFormat($date, 'Y-m-d') === false) {
            $this->error('Date must be YYYY-MM-DD.');

            return self::FAILURE;
        }

        $farms = Farm::query()->active()->when($this->option('farm'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($farms as $farm) {
            if ($scheduled && (now()->format('H:i') < $farm->setting('delivery_sheet_generate_time')
                || ! Cache::add("deliveries:generated:{$farm->id}:{$date}", true, now()->addDays(2)))) {
                continue;
            }

            $count = $sheets->generate($farm, $date);
            $this->info("{$farm->name}: {$count} new deliveries for {$date}.");
        }

        return self::SUCCESS;
    }
}
