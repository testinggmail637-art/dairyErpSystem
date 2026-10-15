{{--
    A button that asks for confirmation (and optionally a reason for the audit log) before submitting.
    Critical records (spec 4.4) must pass :reason="true".

    <x-confirm-form :action="route('roles.destroy', $role)" :message="__('Delete this role?')" reason />
    <x-confirm-form :action="route('bills.finalize', $bill)" method="POST" :message="__('Finalize the bill?')"
                    :label="__('Finalize')" icon="bi-lock" button-class="btn btn-primary btn-sm" confirm-button="Finalize" />
--}}
@props([
    'action', 'message', 'method' => 'DELETE', 'reason' => false, 'label' => null, 'icon' => 'bi-trash',
    'buttonClass' => 'btn btn-outline-danger btn-sm', 'confirmButton' => null,
])

<form method="POST" action="{{ $action }}" data-confirm="{{ $message }}"
      @if ($reason) data-confirm-reason @endif
      @if ($confirmButton) data-confirm-button="{{ $confirmButton }}" @endif
      {{ $attributes->class(['d-inline']) }}>
    @csrf
    @unless (strtoupper($method) === 'POST')
        @method($method)
    @endunless
    <button type="submit" class="{{ $buttonClass }}" @unless ($label) aria-label="{{ $message }}" @endunless>
        @if ($icon)<i class="bi {{ $icon }}"></i>@endif
        @if ($label)<span class="{{ $icon ? 'ms-1' : '' }}">{{ $label }}</span>@endif
    </button>
</form>
