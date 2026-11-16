@extends('layouts.app')

@section('title', __('Salary runs'))

@section('content')
<div class="card mb-3">
    <div class="card-body">
        <form method="POST" action="{{ route('salary-runs.store') }}" class="row g-2 align-items-end">
            @csrf
            <div class="col-8 col-md-3">
                <label for="month" class="form-label">{{ __('Month') }}</label>
                <input type="month" id="month" name="month" value="{{ old('month', today()->format('Y-m')) }}" max="{{ today()->format('Y-m') }}"
                       class="form-control @error('month') is-invalid @enderror" required>
                @error('month')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-4 col-md-3">
                <button type="submit" class="btn btn-primary w-100"><i class="bi bi-plus-lg me-1"></i>{{ __('New salary run') }}</button>
            </div>
            <div class="col-12 col-md-6 small text-body-secondary">
                {{ __('Creates a draft with a slip for every staff member employed that month, worked out from attendance.') }}
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div id="runs-results" data-ajax-results>
    @fragment('results')
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead>
                <tr>
                    <th>{{ __('Month') }}</th>
                    <th>{{ __('Run no.') }}</th>
                    <th class="text-end">{{ __('Staff') }}</th>
                    <th class="text-end">{{ __('Earned') }}</th>
                    <th class="text-end">{{ __('Net payable') }}</th>
                    <th>{{ __('Status') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($runs as $run)
                    <tr>
                        <td><a href="{{ route('salary-runs.show', $run) }}" class="fw-semibold">{{ $run->label() }}</a></td>
                        <td>{{ $run->run_no }}</td>
                        <td class="text-end">{{ $run->slips_count }}</td>
                        <td class="text-end"><x-money :value="$run->earned_total ?? 0" /></td>
                        <td class="text-end fw-semibold"><x-money :value="$run->net_total ?? 0" /></td>
                        <td><span class="badge {{ $run->isFinalized() ? 'text-bg-success' : 'text-bg-warning' }}">{{ $run->status->label() }}</span></td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="text-center text-body-secondary py-5">
                            <i class="bi bi-wallet2 fs-2 d-block mb-2"></i>{{ __('No salary runs yet. Choose a month above to start one.') }}
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if ($runs->hasPages())<div class="card-footer">{{ $runs->links() }}</div>@endif
    @endfragment
    </div>
</div>
@endsection
