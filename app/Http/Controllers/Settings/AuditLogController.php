<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AuditLogIndexRequest;
use App\Models\Activity;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Settings → Audit Log (spec 4.4): who changed what, old → new, and why.
 * Shows entries of the current farm plus farm-independent records (users, roles).
 */
class AuditLogController extends Controller
{
    public function index(AuditLogIndexRequest $request, CurrentFarm $currentFarm): View
    {
        $filters = $request->validated();
        $timezone = config('app.timezone');

        $activities = Activity::query()
            // MySQL/MariaDB often pick the wrong index for "farm_id = ? OR farm_id IS NULL" plus a filter and then read
            // every row (16 s on 200k rows instead of 0.2 s). Name the index that matches the most selective filter.
            ->from(DB::raw('activity_log USE INDEX ('.$this->indexFor($filters).')'))
            ->where(fn (Builder $q) => $q->where('farm_id', $currentFarm->id())->orWhereNull('farm_id'))
            ->with(['causer', 'subject'])
            ->when($filters['user'] ?? null, fn (Builder $q, $id) => $q
                ->where('causer_type', (new User)->getMorphClass())->where('causer_id', $id))
            ->when($filters['model'] ?? null, fn (Builder $q, $type) => $q->where('subject_type', $type))
            ->when($filters['event'] ?? null, fn (Builder $q, $event) => $q->where('event', $event))
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q
                ->where('created_at', '>=', Carbon::parse($from, $timezone)->startOfDay()->utc()))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q
                ->where('created_at', '<=', Carbon::parse($to, $timezone)->endOfDay()->utc()))
            ->latest('id')
            ->simplePaginate(50) // no COUNT(*) over the ever-growing log; Previous / Next is enough here
            ->withQueryString();

        return view('settings.audit-logs.index', [
            'activities' => $activities,
            'filters' => $filters,
            // Filter lists come from small tables / a fixed list — never from scanning the (very large) activity log.
            'users' => User::query()
                ->whereHas('farms', fn (Builder $q) => $q->whereKey($currentFarm->id()))
                ->orderBy('name')->pluck('name', 'id'),
            'models' => collect(Activity::AUDITED_MODELS)
                ->mapWithKeys(fn (string $class) => [(new $class)->getMorphClass() => audit_model_label($class)])
                ->sort(),
        ]);
    }

    /**
     * Index for the most selective filter in use (indexes from the 2026_10_15_190000 migration).
     *
     * @param  array<string, mixed>  $filters
     */
    private function indexFor(array $filters): string
    {
        return match (true) {
            filled($filters['user'] ?? null) => 'activity_log_farm_causer_index',
            filled($filters['model'] ?? null) => 'activity_log_farm_subject_index',
            filled($filters['from'] ?? null), filled($filters['to'] ?? null) => 'activity_log_farm_id_created_at_index',
            filled($filters['event'] ?? null) => 'activity_log_farm_event_index',
            default => 'activity_log_farm_id_id_index',
        };
    }
}
