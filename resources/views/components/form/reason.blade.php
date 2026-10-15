{{-- Required "reason" for edits/deletes of audit-logged records (spec 4.4). Usage: <x-form.reason /> --}}
@props(['label' => __('Reason for this change'), 'placeholder' => __('e.g. Wrong phone number entered earlier')])

<x-form.textarea name="reason" :label="$label" rows="2" minlength="3" maxlength="500" required
                 :placeholder="$placeholder" :help="__('Saved in the audit log with your name and the old and new values.')"
                 {{ $attributes }} />
