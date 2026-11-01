{{--
    Breadcrumb trail — included once by layouts/admin/app and layouts/vendor/app,
    never per page. The trail is derived by BreadcrumbService from the v2 sidebar
    hierarchy (config/navigation-map.php, generated) plus the hand-written parts
    in config/breadcrumbs.php. See CLAUDE.md §10f.

    The leaf comes from the page's own `@section('title')`: with `@extends`, the
    child view's sections are captured before the layout renders, so yieldContent
    is populated by the time this include runs.
--}}
@php
    // strip_tags + decode: a handful of titles arrive pre-escaped, and Blade
    // escapes the crumb again on the way out.
    $bcx_trail = app(\App\Services\System\BreadcrumbService::class)
        ->trail(trim(html_entity_decode(strip_tags($__env->yieldContent('title')), ENT_QUOTES)));
@endphp

@if (count($bcx_trail) > 1)
    <nav class="bcx container-fluid" aria-label="{{ translate('Breadcrumb') }}">
        <div class="bcx__scroll">
            <ol class="bcx__list">
                @foreach ($bcx_trail as $bcx_index => $bcx_crumb)
                    <li class="bcx__item">
                        @if ($bcx_crumb['current'])
                            <span class="bcx__current" aria-current="page">{{ $bcx_crumb['label'] }}</span>
                        @elseif ($bcx_crumb['url'])
                            <a class="bcx__link" href="{{ $bcx_crumb['url'] }}">
                                @if ($bcx_index === 0)
                                    <i class="tio-home-outlined bcx__home" aria-hidden="true"></i>
                                @endif
                                <span>{{ $bcx_crumb['label'] }}</span>
                            </a>
                        @elseif ($bcx_crumb['panel'])
                            {{-- A section is a sidebar panel rather than a page, so this
                                 reopens that panel. breadcrumb.js downgrades it to plain
                                 text when the panel is not on screen (v1 chrome). --}}
                            <button type="button" class="bcx__step" data-bc-panel="{{ $bcx_crumb['panel'] }}"
                                title="{{ translate('Open this section in the sidebar') }}">
                                {{ $bcx_crumb['label'] }}
                            </button>
                        @else
                            {{-- A grouping with nowhere to go: plain text, not a
                                 control that looks clickable and is not. --}}
                            <span class="bcx__text">{{ $bcx_crumb['label'] }}</span>
                        @endif
                    </li>
                @endforeach
            </ol>
        </div>
    </nav>
@endif
