{{-- Collection rows with edit / delete (policy) or the lock; shared by the entry screen and the list. --}}
<div class="table-responsive">
    <table class="table table-sm table-hover align-middle mb-0">
        <thead>
            <tr>
                @if ($showDate)<th>{{ __('Date') }}</th><th>{{ __('Shift') }}</th>@endif
                <th>{{ __('Farmer') }}</th>
                <th>{{ __('Milk') }}</th>
                <th class="text-end">{{ __('Litres') }}</th>
                <th class="text-end">{{ __('Fat') }}</th>
                <th class="text-end">{{ __('SNF') }}</th>
                <th class="text-end">{{ __('Rate') }}</th>
                <th class="text-end">{{ __('Amount') }}</th>
                <th>{{ __('Status') }}</th>
                <th class="text-end">{{ __('Actions') }}</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($entries as $entry)
                <tr @class(['table-danger' => ! $entry->isAccepted()])>
                    @if ($showDate)
                        <td class="text-nowrap">{{ fmt_date($entry->collection_date) }}</td>
                        <td>{{ $entry->shift->label() }}</td>
                    @endif
                    <td><span class="font-monospace small">{{ $entry->farmer->code }}</span> {{ $entry->farmer->name }}</td>
                    <td>{{ $entry->milk_type->label() }}</td>
                    <td class="text-end">{{ fmt_qty($entry->quantity_litre) }}</td>
                    <td class="text-end">{{ $entry->fat ?? '—' }}</td>
                    <td class="text-end">{{ $entry->snf ?? '—' }}</td>
                    <td class="text-end">{{ inr($entry->rate_per_litre) }}</td>
                    <td class="text-end fw-semibold">{{ inr($entry->amount) }}</td>
                    <td>
                        @if ($entry->isAccepted())
                            <span class="badge text-bg-success">{{ __('Accepted') }}</span>
                        @else
                            <span class="badge text-bg-danger">{{ __('Rejected') }}</span>
                            <div class="small text-body-secondary">{{ $entry->rejection_reason }}</div>
                        @endif
                    </td>
                    <td class="text-end text-nowrap">
                        @if ($entry->farmer_payment_id)
                            <i class="bi bi-lock-fill text-body-secondary" title="{{ __('Included in a farmer payment statement.') }}"
                               aria-label="{{ __('Included in a farmer payment statement.') }}"></i>
                        @else
                            @can('update', $entry)
                                <a href="{{ route('collections.edit', $entry) }}" class="btn btn-outline-primary btn-sm" aria-label="{{ __('Edit') }}"><i class="bi bi-pencil"></i></a>
                                <x-confirm-form :action="route('collections.destroy', $entry)" reason
                                                :message="__('Delete :qty L of :code on :date (:shift)?', ['qty' => fmt_qty($entry->quantity_litre), 'code' => $entry->farmer->code, 'date' => fmt_date($entry->collection_date), 'shift' => mb_strtolower($entry->shift->label())])" />
                            @endcan
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="{{ $showDate ? 11 : 9 }}" class="text-center text-body-secondary py-4">{{ __('No milk collections here.') }}</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
