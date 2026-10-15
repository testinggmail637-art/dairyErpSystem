<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\AuditLogIndexRequest;
use App\Models\Activity;
use App\Models\User;
use App\Support\CurrentFarm;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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

        $base = Activity::query()->where(fn (Builder $q) => $q
            ->where('farm_id', $currentFarm->id())
            ->orWhereNull('farm_id'));

        $activities = (clone $base)
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
            ->paginate(50)
            ->withQueryString();

        return view('settings.audit-logs.index', [
            'activities' => $activities,
            'filters' => $filters,
            'users' => User::query()
                ->whereIn('id', (clone $base)->where('causer_type', (new User)->getMorphClass())->select('causer_id'))
                ->orderBy('name')->pluck('name', 'id'),
            'models' => (clone $base)->whereNotNull('subject_type')->distinct()->orderBy('subject_type')
                ->pluck('subject_type')->mapWithKeys(fn (string $type) => [$type => audit_model_label($type)]),
        ]);
    }
}
