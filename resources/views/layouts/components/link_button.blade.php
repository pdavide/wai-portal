<a href="{{ $link }}" role="button" class="btn btn-{{ $type ?? 'primary' }}{{ empty($icon) ? '' : ' btn-icon' }}{{ isset($size) ? " btn-{$size}" : ''}} {{ isset($disabled) && $disabled ? " disabled" : ''}}">
    <span>{{ ucfirst($slot) }}</span>
    @isset($icon)
    <svg class="icon icon-{{ $iconColor ?? 'white' }} ml-1">
        <use xlink:href="{{ asset('svg/sprite.svg') }}#{{ $icon }}"></use>
    </svg>
    @endisset
</a>
