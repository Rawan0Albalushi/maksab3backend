@php($stars_value = round(((float) ($rating ?? 0)) * 2) / 2)

<span class="rvw-stars{{ isset($stars_size) ? ' rvw-stars--'.$stars_size : '' }}" role="img"
      aria-label="{{ $stars_value }}/5 {{ translate('messages.Rating') }}">
    @for($star = 1; $star <= 5; $star++)
        <i class="{{ $stars_value >= $star ? 'tio-star' : ($stars_value >= $star - 0.5 ? 'tio-star-half' : 'tio-star-outlined') }}"></i>
    @endfor
</span>
