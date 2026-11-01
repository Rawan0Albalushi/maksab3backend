{{-- Shared filter-drawer header: icon badge, title, one-line subtitle, live
     "N active" chip and the close button.

     @include('partials._filter-drawer-head', [
         'fd_title'    => translate('messages.Order filter'),
         'fd_subtitle' => translate('messages.Narrow the order list down by zone, store, status, type and date range.'),
     ])

     Sits as the first child of `.sidebar-card`, replacing the hand-rolled
     `.card-header` each list screen used to carry. Variable names are prefixed
     `fd_` because `@include` merges the including view's variables and both
     `$title` and `$subtitle` are already in use on these pages.

     The close link keeps the `filter-button-hide` class the old markup used, so
     any page still binding to it keeps working. Behaviour and styling live in
     `filter-drawer.js` / `filter-drawer.css`. --}}

@php
    $fd_subtitle = $fd_subtitle ?? null;
@endphp

<div class="card-header">
    <div class="fd-head">
        <span class="fd-head__icon"><i class="tio-filter-list"></i></span>
        <span class="fd-head__text">
            <span class="fd-head__title">{{ $fd_title }}</span>
            @if($fd_subtitle)
                <span class="fd-head__subtitle">{{ $fd_subtitle }}</span>
            @endif
        </span>
    </div>

    <span class="fd-head__count" data-fd-count
          data-fd-count-label="{{ translate('messages.Active') }}"></span>

    <a href="javascript:;" class="fd-close filter-button-hide" role="button"
       aria-label="{{ translate('messages.Close') }}">
        <i class="tio-clear"></i>
    </a>
</div>
