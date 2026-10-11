@props(['label' => null, 'for' => null, 'error' => null, 'help' => null, 'required' => false])

{{-- Wraps a form control with a label, help text and the validation error for $error. --}}
<div {{ $attributes }}>
    @if ($label)
        <label @if ($for) for="{{ $for }}" @endif class="form-label">
            {{ $label }}
            @if ($required)
                <span class="text-danger">*</span>
            @endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="form-error">{{ $message }}</p>
        @enderror
    @endif

    @if ($help)
        <p class="form-help">{{ $help }}</p>
    @endif
</div>
