@extends('layouts.app')

@section('title', __('Notifications'))

@section('page-actions')
    @if ($notifications->contains(fn ($n) => $n->read_at === null))
        <form method="POST" action="{{ route('notifications.read-all') }}">
            @csrf
            <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-check2-all me-1"></i>{{ __('Mark all read') }}</button>
        </form>
    @endif
@endsection

@section('content')
<div class="card">
    <div class="list-group list-group-flush">
        @forelse ($notifications as $notification)
            <a href="{{ route('notifications.open', $notification->id) }}"
               class="list-group-item list-group-item-action d-flex gap-3 py-3 {{ $notification->read_at ? '' : 'fw-semibold' }}">
                <i class="bi {{ $notification->data['icon'] ?? 'bi-info-circle' }} text-primary fs-5"></i>
                <div class="flex-grow-1">
                    {{ $notification->data['message'] ?? '' }}
                    <small class="d-block text-body-secondary fw-normal">{{ fmt_datetime($notification->created_at) }}</small>
                </div>
                @unless ($notification->read_at)
                    <span class="badge text-bg-primary align-self-center">{{ __('New') }}</span>
                @endunless
            </a>
        @empty
            <div class="text-center text-body-secondary py-5">
                <i class="bi bi-bell-slash fs-2 d-block mb-2"></i>
                {{ __('You have no notifications.') }}
            </div>
        @endforelse
    </div>
</div>

<div class="mt-3">{{ $notifications->links() }}</div>
@endsection
