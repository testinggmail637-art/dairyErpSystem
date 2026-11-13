<?php

namespace App\Services;

use App\Enums\AnimalStatus;
use App\Enums\BillStatus;
use App\Models\AnimalVaccination;
use App\Models\Bill;
use App\Models\FeedItem;
use App\Support\CurrentFarm;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Daily reminders of the current farm (spec Phase 5): one summary line per kind — vaccinations due / overdue, expected
 * calvings, feed at/below reorder level, yesterday's milk drop alerts, overdue customer bills — each for one permission.
 */
class DailyReminderService
{
    private const NAMES_SHOWN = 3;

    public function __construct(
        private readonly CurrentFarm $currentFarm,
        private readonly BreedingService $breeding,
        private readonly FeedService $feed,
        private readonly MilkDropAlertService $milkDrops,
    ) {}

    /** @return list<array{permission: string, message: string, url: string, icon: string}> */
    public function lines(): array
    {
        return array_values(array_filter([
            $this->vaccinations(),
            $this->calvings(),
            $this->feed(),
            $this->milkDrops(),
            $this->overdueBills(),
        ]));
    }

    /** Latest dose of each animal + vaccine whose next date is overdue or within the reminder days; active animals only. */
    private function vaccinations(): ?array
    {
        $until = today()->addDays($this->days('vaccination_reminder_days', 7));
        $rows = AnimalVaccination::query()
            ->whereNotNull('next_due_on')->where('next_due_on', '<=', $until->toDateString())
            ->whereNotExists(fn ($later) => $later->from('animal_vaccinations as l')
                ->whereColumn('l.animal_id', 'animal_vaccinations.animal_id')->whereColumn('l.vaccine_type_id', 'animal_vaccinations.vaccine_type_id')
                ->whereColumn('l.given_on', '>', 'animal_vaccinations.given_on'))
            ->whereHas('animal', fn ($q) => $q->where('status', AnimalStatus::Active->value))
            ->with(['animal:id,tag_no', 'vaccineType:id,name'])
            ->orderBy('next_due_on')
            ->get(['id', 'animal_id', 'vaccine_type_id', 'next_due_on']);

        if ($rows->isEmpty()) {
            return null;
        }
        $overdue = $rows->filter(fn ($v) => $v->next_due_on->lt(today()))->count();

        return $this->line('health.view', 'bi-shield-plus', route('health.vaccinations.index', ['due' => '30'], false),
            __(':count vaccinations due by :date (:overdue overdue): :names.', [
                'count' => $rows->count(), 'date' => fmt_date($until), 'overdue' => $overdue,
                'names' => $this->names($rows->map(fn ($v) => $v->animal->tag_no.' '.$v->vaccineType->name)),
            ]));
    }

    /** Latest service / positive check with an expected calving from 30 days ago (not yet recorded) to the reminder days ahead. */
    private function calvings(): ?array
    {
        $rows = $this->breeding->expectedCalvings(today()->subDays(30)->toDateString(),
            today()->addDays($this->days('calving_reminder_days', 15))->toDateString())
            ->join('animals as a', 'a.id', '=', 'e.animal_id')->whereNull('a.deleted_at')
            ->where('a.status', AnimalStatus::Active->value)
            ->addSelect('a.tag_no')->get();

        if ($rows->isEmpty()) {
            return null;
        }

        return $this->line('breeding.manage', 'bi-heart-pulse', route('breeding.index', [], false),
            __(':count expected calvings: :names.', ['count' => $rows->count(),
                'names' => $this->names($rows->map(fn ($r) => $r->tag_no.' '.fmt_date($r->expected_calving_date)))]));
    }

    /** Active feed items with a reorder level whose stock is at or below it (same rule as the feed stock screen). */
    private function feed(): ?array
    {
        $items = FeedItem::query()->where('is_active', true)->where('reorder_level', '>', 0)->orderBy('name')
            ->get(['id', 'name', 'unit', 'reorder_level']);
        $stock = $this->feed->stock($items->modelKeys());
        $low = $items->filter(fn ($i) => Money::compare($stock[$i->id] ?? '0', $i->reorder_level) <= 0);

        if ($low->isEmpty()) {
            return null;
        }

        return $this->line('feed.view', 'bi-box-seam', route('feed.index', [], false),
            __(':count feed items at or below reorder level: :names.', ['count' => $low->count(),
                'names' => $this->names($low->map(fn ($i) => $i->name.' '.fmt_qty($stock[$i->id] ?? '0').' '.$i->unit->value))]));
    }

    /** Yesterday's drops — the 07:00 run comes before today's milking is entered. */
    private function milkDrops(): ?array
    {
        $date = today()->subDay();
        $alerts = $this->milkDrops->alerts($date->toDateString());

        if ($alerts->isEmpty()) {
            return null;
        }

        return $this->line('milk_production.view', 'bi-graph-down-arrow', route('milk-alerts.index', ['date' => $date->toDateString()], false),
            __(':count milk drop alerts on :date: :names. Check health — this is a signal, not a diagnosis.', [
                'count' => $alerts->count(), 'date' => fmt_date($date),
                'names' => $this->names($alerts->map(fn ($a) => $a->animal->tag_no.' '.$a->shift->label().' −'.$a->drop.'%')),
            ]));
    }

    /** Finalized bills past their due date with money still unpaid (Σ allocations < total), counted in SQL. */
    private function overdueBills(): ?array
    {
        $bills = Bill::query()->toBase()
            ->where('status', BillStatus::Finalized->value)->where('due_date', '<', today()->toDateString())
            ->leftJoinSub(DB::table('payment_allocations')->groupBy('bill_id')->selectRaw('bill_id, SUM(amount) AS paid'),
                'p', 'p.bill_id', '=', 'bills.id')
            ->whereRaw('bills.total > COALESCE(p.paid, 0)')
            ->selectRaw('COUNT(*) AS cnt, COALESCE(SUM(bills.total - COALESCE(p.paid, 0)), 0) AS due')
            ->first();

        if ((int) $bills->cnt === 0) {
            return null;
        }

        return $this->line('bills.view', 'bi-receipt', route('bills.index', ['status' => BillStatus::Finalized->value], false),
            __(':count customer bills are overdue — :amount unpaid.', ['count' => $bills->cnt, 'amount' => inr((string) $bills->due)]));
    }

    private function days(string $key, int $default): int
    {
        return (int) ($this->currentFarm->farm()?->setting($key, $default) ?? $default);
    }

    /** @param  Collection<int, string>  $names */
    private function names(Collection $names): string
    {
        $more = $names->count() - self::NAMES_SHOWN;

        return $names->take(self::NAMES_SHOWN)->implode(', ').($more > 0 ? ' '.__('and :n more', ['n' => $more]) : '');
    }

    /** @return array{permission: string, message: string, url: string, icon: string} */
    private function line(string $permission, string $icon, string $url, string $message): array
    {
        return compact('permission', 'message', 'url', 'icon');
    }
}
