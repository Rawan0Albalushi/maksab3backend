{{-- Shared page header: icon badge, title, one-line description.

     @include('partials._page-head', [
         'title'    => translate('Deliveryman'),
         'subtitle' => translate('Everyone who delivers for you, and the zone each one covers.'),
         'icon'     => asset('public/assets/admin/img/outline/delivery-man.svg'),
         'count'    => $deliveryMen->total(),
         'count_id' => 'itemCount',
     ])

     `icon` takes an image url; pass `icon_class` instead for a tio glyph. Both
     render the tinted badge from `page-head.css`, which also styles the older
     hand-rolled `.page-header` blocks — so a page that already has its own
     header only needs the `<p class="page-header-desc">` line, not this.

     Pass `count` explicitly (null for no badge): `@include` merges the parent's
     variables, so an unrelated `$count` would otherwise render a stray badge.
     `count_id` keeps the id the page's JS already updates.

     `actions` names a view rendered on the trailing edge of the header row —
     the "Add new" buttons and export dropdowns list screens carry. --}}

@php
    $title      = $title ?? null;
    $subtitle   = $subtitle ?? null;
    $icon       = $icon ?? null;
    $icon_class = $icon_class ?? null;
    $count      = $count ?? null;
    $count_id   = $count_id ?? null;
    $actions    = $actions ?? null;
@endphp

<div class="page-header{{ $actions ? ' d-flex justify-content-between align-items-center flex-wrap gap-2' : '' }}">
    <div>
        <h1 class="page-header-title">
            @if($icon)
                <span class="page-header-icon"><img src="{{ $icon }}" alt=""></span>
            @elseif($icon_class)
                <i class="{{ $icon_class }}"></i>
            @endif
            <span>
                {{ $title }}
                @if(!is_null($count))
                    <span class="badge badge-soft-dark ml-2" @if($count_id) id="{{ $count_id }}" @endif>{{ $count }}</span>
                @endif
            </span>
        </h1>
        @if($subtitle)
            <p class="page-header-desc">{{ $subtitle }}</p>
        @endif
    </div>

    @if($actions)
        <div class="page-header-actions">@include($actions)</div>
    @endif
</div>
