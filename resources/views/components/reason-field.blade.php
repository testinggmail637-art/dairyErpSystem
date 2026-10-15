{{-- Required "reason" for edits/deletes of audit-logged records (spec 4.4). Usage: <x-reason-field /> --}}
@props(['label' => __('Reason for this change'), 'placeholder' => __('e.g. Wrong phone number entered earlier')])

<div {{ $attributes->merge(['class' => 'mb-3']) }}>
    <label for="reason" class="form-label">{{ $label }} <span class="text-danger">*</span></label>
    <textarea id="reason" name="reason" rows="2" minlength="3" maxlength="500" required
              class="form-control @error('reason') is-invalid @enderror" placeholder="{{ $placeholder }}">{{ old('reason') }}</textarea>
    @error('reason')<div class="invalid-feedback">{{ $message }}</div>@enderror
    <div class="form-text"><i class="bi bi-journal-text me-1"></i>{{ __('Saved in the audit log with your name and the old and new values.') }}</div>
</div>
