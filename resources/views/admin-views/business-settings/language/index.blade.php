@extends('layouts.admin.app')

@section('title', translate('messages.Language'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/view-pages/language-settings.css') }}">
@endpush

@php
    $env_mode = getEnvMode();
    /* Inlined rather than <img src>, so the glyph picks up currentColor on
       hover/focus. Same mark the translate screen uses. */
    $translate_icon = '<svg width="14" height="14" viewBox="0 0 14 14" fill="currentColor" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M14 4.083v1.167a.583.583 0 0 1-1.167 0V4.083c0-.643-.523-1.166-1.166-1.166h-1.184l.729.762a.583.583 0 0 1-.837.808L9.081 3.145a1.167 1.167 0 0 1 .007-1.632L10.371.179a.583.583 0 1 1 .841.808l-.733.763h1.188A2.333 2.333 0 0 1 14 4.083ZM3.629 9.512a.583.583 0 0 0-.841.81l.729.762H2.333a1.167 1.167 0 0 1-1.166-1.167V8.751a.583.583 0 0 0-1.167 0v1.166a2.333 2.333 0 0 0 2.333 2.334h1.187l-.732.762a.583.583 0 1 0 .841.808l1.283-1.335a1.167 1.167 0 0 0 .007-1.632L3.629 9.512ZM7 4.667A2.333 2.333 0 0 1 4.667 7H2.333A2.333 2.333 0 0 1 0 4.667V2.333A2.333 2.333 0 0 1 2.333 0h2.334A2.333 2.333 0 0 1 7 2.333v2.334Zm-1.458-2.558a.359.359 0 0 0-.36-.359H3.866v-.224a.359.359 0 0 0-.359-.359h-.012a.359.359 0 0 0-.36.359v.224H1.818a.359.359 0 0 0-.36.36v.012c0 .199.161.36.36.36h2.445c-.065.562-.282 1.255-.76 1.792a3.2 3.2 0 0 1-.404-.583.36.36 0 0 0-.68.19c.131.251.292.492.484.712a2.62 2.62 0 0 1-1.153.37.363.363 0 0 0-.331.362v.012c0 .213.184.378.396.358a3.6 3.6 0 0 0 1.652-.596 3.66 3.66 0 0 0 1.638.596.363.363 0 0 0 .397-.358v-.012a.363.363 0 0 0-.324-.358 2.63 2.63 0 0 1-1.154-.372c.578-.662.866-1.511.937-2.255h.184c.199 0 .36-.161.36-.36v-.012ZM14 9.333v2.334A2.333 2.333 0 0 1 11.667 14H9.333A2.333 2.333 0 0 1 7 11.667V9.333A2.333 2.333 0 0 1 9.333 7h2.334A2.333 2.333 0 0 1 14 9.333Zm-1.864 3.001-.795-3.47a.9.9 0 0 0-.491-.595.9.9 0 0 0-1.199.594l-.824 3.496a.36.36 0 0 0 .397.499.4.4 0 0 0 .397-.315l.16-.677h1.405l.155.675a.4.4 0 0 0 .398.292h.001a.36.36 0 0 0 .396-.499Zm-1.644-3.351c-.022 0-.041.015-.046.037l-.473 2.005h1.025l-.459-2.005c-.005-.022-.025-.037-.047-.037Z"/></svg>';
@endphp

@section('content')
    <div class="content container-fluid lang-set">

        <div class="lang-set-head">
            <div>
                <h1 class="lang-set-title">{{ translate('messages.Language') }}</h1>
                <p class="lang-set-subtitle">
                    {{ translate('messages.Choose which languages your admin panel, website and apps are available in, then edit the wording for each one.') }}
                </p>
            </div>
        </div>

        <div class="lang-set-overview">
            <div class="lang-set-stat lang-set-stat--total">
                <span class="lang-set-stat-icon"><i class="tio-globe"></i></span>
                <span class="lang-set-stat-text">
                    <span class="lang-set-stat-label">{{ translate('messages.Total languages') }}</span>
                    <span class="lang-set-stat-value">{{ $summary['total'] }}</span>
                </span>
            </div>
            <div class="lang-set-stat lang-set-stat--active">
                <span class="lang-set-stat-icon"><i class="tio-checkmark-circle-outlined"></i></span>
                <span class="lang-set-stat-text">
                    <span class="lang-set-stat-label">{{ translate('messages.Active') }}</span>
                    <span class="lang-set-stat-value">{{ $summary['active'] }}</span>
                </span>
            </div>
            <div class="lang-set-stat lang-set-stat--inactive">
                <span class="lang-set-stat-icon"><i class="tio-hidden-outlined"></i></span>
                <span class="lang-set-stat-text">
                    <span class="lang-set-stat-label">{{ translate('messages.Inactive') }}</span>
                    <span class="lang-set-stat-value">{{ $summary['inactive'] }}</span>
                </span>
            </div>
            <div class="lang-set-stat lang-set-stat--default">
                <span class="lang-set-stat-icon"><i class="tio-star"></i></span>
                <span class="lang-set-stat-text">
                    <span class="lang-set-stat-label">{{ translate('messages.Default language') }}</span>
                    <span class="lang-set-stat-value lang-set-stat-value--text">
                        {{ $summary['default']['name'] ?? translate('messages.Not set') }}
                    </span>
                </span>
            </div>
        </div>

        <div class="lang-set-note">
            <i class="tio-info-outined"></i>
            <span>
                {{ translate('messages.A new language starts from the English phrases. Open it and translate the keys before switching it on for customers.') }}
            </span>
        </div>

        <div class="lang-set-card">
            <div class="lang-set-card-head">
                <h2 class="lang-set-card-title">{{ translate('messages.Add New Language') }}</h2>
                <p class="lang-set-card-note">
                    {{ translate('messages.Setup new languages in your system, Website & apps to make order from versatile customers.') }}
                </p>
            </div>

            <form action="{{ route('admin.business-settings.language.add-new') }}" method="post" class="lang-set-form">
                @csrf
                <div class="lang-set-field">
                    <label for="country-code" class="lang-set-label">{{ translate('messages.Language') }}</label>
                    <select id="country-code" name="code" class="form-control custom-select js-select2-custom">
                        @foreach(\App\CentralLogics\Helpers::getLanguages() as $key => $value)
                            <option value="{{ $key }}">{{ $value }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="lang-set-field">
                    <span class="lang-set-label">{{ translate('messages.direction') }}</span>
                    <div class="lang-set-seg">
                        <label class="lang-set-seg-option">
                            <input type="radio" value="ltr" name="direction" checked>
                            <span>{{ translate('messages.Left to Right') }} <small>LTR</small></span>
                        </label>
                        <label class="lang-set-seg-option">
                            <input type="radio" value="rtl" name="direction">
                            <span>{{ translate('messages.Right to Left') }} <small>RTL</small></span>
                        </label>
                    </div>
                </div>

                <div class="lang-set-form-actions">
                    <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                    <button type="submit" class="btn btn--primary lang-set-submit">
                        <i class="tio-add"></i>
                        {{ translate('messages.Add Language') }}
                    </button>
                </div>
            </form>
        </div>

        <div class="card card-body">
            <div class="lang-set-toolbar">
                <h2 class="lang-set-toolbar-title">
                    {{ translate('messages.Language List') }}
                    <span class="lang-set-count">{{ $summary['total'] }}</span>
                </h2>

                <div class="lang-set-filters" role="group" aria-label="{{ translate('messages.Filter by status') }}">
                    <button type="button" class="lang-set-filter is-active" data-filter="all" aria-pressed="true">
                        {{ translate('All') }}
                        <span class="lang-set-filter-num">{{ $summary['total'] }}</span>
                    </button>
                    <button type="button" class="lang-set-filter" data-filter="active" aria-pressed="false">
                        {{ translate('messages.Active') }}
                        <span class="lang-set-filter-num">{{ $summary['active'] }}</span>
                    </button>
                    <button type="button" class="lang-set-filter" data-filter="inactive" aria-pressed="false">
                        {{ translate('messages.Inactive') }}
                        <span class="lang-set-filter-num">{{ $summary['inactive'] }}</span>
                    </button>
                </div>

                <div class="lang-set-search">
                    <i class="tio-search lang-set-search-icon"></i>
                    <label class="sr-only" for="lang-set-search">{{ translate('messages.Search Language') }}</label>
                    <input type="search" id="lang-set-search" class="form-control"
                           placeholder="{{ translate('messages.Search Language') }}" autocomplete="off">
                </div>
            </div>

            <div class="lang-set-grid" id="lang-set-grid">
                @foreach($languages as $language)
                    @php
                        /* The first two rows are the seeded demo languages; the demo
                           build blocks every write against them. */
                        $demo_locked = $env_mode === 'demo' && ($language['index'] === 0 || $language['index'] === 1);
                        $progress = $language['progress'];
                        $can_default = !$language['default'];
                        $can_edit = $language['code'] !== 'en';
                        $can_delete = $language['code'] !== 'en' && !$language['default'];
                    @endphp

                    <article class="lang-set-item {{ $language['default'] ? 'is-default' : '' }} {{ $language['status'] ? '' : 'is-off' }}"
                             data-status="{{ $language['status'] ? 'active' : 'inactive' }}"
                             data-search="{{ Str::lower($language['name'] . ' ' . $language['native'] . ' ' . $language['code']) }}">

                        <div class="lang-set-item-head">
                            <span class="lang-set-item-code" aria-hidden="true">{{ $language['code'] }}</span>
                            <div class="lang-set-item-id">
                                <h3 class="lang-set-item-name" title="{{ $language['name'] }}">{{ $language['name'] }}</h3>
                                @if($language['native'])
                                    {{-- No dir=: an RTL dir here right-aligns the native name
                                         away from the title it belongs to. The bidi algorithm
                                         already shapes the string correctly on its own, and
                                         the CSS isolates it from the LTR chrome around it. --}}
                                    <span class="lang-set-item-native">{{ $language['native'] }}</span>
                                @endif
                            </div>
                        </div>

                        <div class="lang-set-chips">
                            @if($language['default'])
                                <span class="lang-set-chip lang-set-chip--default">{{ translate('Default') }}</span>
                            @endif
                            @if($language['status'])
                                <span class="lang-set-chip lang-set-chip--active">
                                    <span class="lang-set-chip-dot"></span>{{ translate('messages.Active') }}
                                </span>
                            @else
                                <span class="lang-set-chip">
                                    <span class="lang-set-chip-dot"></span>{{ translate('messages.Inactive') }}
                                </span>
                            @endif
                            <span class="lang-set-chip">{{ $language['direction'] === 'rtl' ? 'RTL' : 'LTR' }}</span>
                        </div>

                        @if($progress)
                            <div class="lang-set-progress">
                                <div class="lang-set-progress-top">
                                    <span>{{ translate('messages.Translated') }}</span>
                                    <span class="lang-set-progress-pct">{{ $progress['percentage'] }}%</span>
                                </div>
                                <div class="lang-set-progress-track"
                                     role="progressbar"
                                     aria-valuenow="{{ $progress['percentage'] }}"
                                     aria-valuemin="0"
                                     aria-valuemax="100"
                                     aria-label="{{ translate('messages.Translated') }} — {{ $language['name'] }}">
                                    <span class="lang-set-progress-fill" style="width: {{ $progress['percentage'] }}%"></span>
                                </div>
                                <p class="lang-set-progress-meta">
                                    {{-- bdi dir=ltr: in RTL chrome the bidi algorithm reorders
                                         two digit runs around a neutral "/", so 48 / 10,800
                                         rendered as 10,800 / 48 — the opposite claim. A
                                         numeric fraction reads left-to-right in RTL
                                         typography anyway, so pin it. --}}
                                    <bdi dir="ltr"><b>{{ number_format($progress['translated']) }}</b> /
                                        <b>{{ number_format($progress['total']) }}</b></bdi>
                                    {{ translate('messages.keys') }}
                                    @if($progress['pending'] > 0)
                                        · <bdi>{{ number_format($progress['pending']) }} {{ translate('Pending') }}</bdi>
                                    @endif
                                </p>
                            </div>
                        @else
                            <p class="lang-set-progress-missing">
                                <i class="tio-warning"></i>
                                {{ translate('No data found') }}
                            </p>
                        @endif

                        <div class="lang-set-item-spacer"></div>

                        <div class="lang-set-item-foot">
                            {{-- No for= attribute: the input is a descendant, so the
                                 implicit association already applies and an explicit
                                 one can make the click forward twice. --}}
                            <label class="lang-set-toggle {{ $language['default'] ? 'is-locked' : '' }}">
                                <span class="toggle-switch toggle-switch-sm">
                                    @if($language['default'])
                                        {{-- The default language is what the panel falls back to,
                                             so it can never be switched off from here. --}}
                                        <input type="checkbox" class="toggle-switch-input update-lang-status"
                                               id="lang-status-{{ $language['code'] }}"
                                               {{ $language['status'] ? 'checked' : '' }} disabled>
                                    @else
                                        <input type="checkbox" class="toggle-switch-input status-update"
                                               id="lang-status-{{ $language['code'] }}"
                                               data-url="{{ route('admin.business-settings.language.update-status') }}"
                                               data-id="{{ $language['code'] }}"
                                               {{ $language['status'] ? 'checked' : '' }}>
                                    @endif
                                    <span class="toggle-switch-label">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </span>
                                <span class="lang-set-toggle-text {{ $language['status'] ? 'is-on' : '' }}">
                                    {{ $language['status'] ? translate('messages.Active') : translate('messages.Inactive') }}
                                </span>
                            </label>

                            <a class="lang-set-action {{ $demo_locked ? 'call-demo-lang' : '' }}"
                               data-key="{{ $language['index'] }}"
                               data-env-mode="{{ $env_mode }}"
                               href="{{ $demo_locked ? 'javascript:' : route('admin.business-settings.language.translate', [$language['code']]) }}">
                                {!! $translate_icon !!}
                                {{ translate('messages.Translate') }}
                            </a>

                            @if($can_default || $can_edit || $can_delete)
                                <div class="dropdown">
                                    <button type="button" class="lang-set-action lang-set-action--icon" data-toggle="dropdown"
                                            aria-expanded="false" aria-label="{{ translate('messages.Action') }}">
                                        <i class="tio-more-vertical"></i>
                                    </button>
                                    <ul class="dropdown-menu dropdown-menu-right" dir="ltr">
                                        @if($can_default)
                                            <a href="{{ route('admin.business-settings.language.update-default-status', ['code' => $language['code']]) }}"
                                               class="dropdown-item cursor-pointer">
                                                <i class="tio-checkmark-circle-outlined"></i>
                                                {{ translate('messages.Mark As Default') }}
                                            </a>
                                        @endif
                                        @if($can_edit)
                                            <a class="dropdown-item cursor-pointer call-demo-lang offcanvas-trigger"
                                               data-key="{{ $language['index'] }}"
                                               data-env-mode="{{ $env_mode }}"
                                               data-target="{{ $demo_locked ? '' : '#lang-offcanvas-update-' . $language['code'] }}">
                                                <i class="tio-edit"></i>
                                                {{ translate('Edit') }}
                                            </a>
                                        @endif
                                        @if($can_delete)
                                            <a class="dropdown-item dropdown-item--danger cursor-pointer call-demo-lang {{ $demo_locked ? '' : 'delete' }}"
                                               data-key="{{ $language['index'] }}"
                                               data-env-mode="{{ $env_mode }}"
                                               id="{{ $demo_locked ? 'javascript:' : route('admin.business-settings.language.delete', [$language['code']]) }}">
                                                <i class="tio-delete-outlined"></i>
                                                {{ translate('messages.Delete') }}
                                            </a>
                                        @endif
                                    </ul>
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            </div>

            <div class="lang-set-empty" id="lang-set-empty">
                <img src="{{ asset('public/assets/admin/svg/illustrations/sorry.svg') }}" alt="">
                <h5>{{ translate('No data found') }}</h5>
                <p>{{ translate('messages.No language matches the current search or filter.') }}</p>
            </div>
        </div>
    </div>

    @foreach($languages as $language)
        @continue($language['code'] === 'en')
        <div id="lang-offcanvas-update-{{ $language['code'] }}"
             class="custom-offcanvas lang-set-offcanvas d-flex flex-column justify-content-between">
            <form action="{{ route('admin.business-settings.language.update') }}" method="post">
                @csrf
                <input type="hidden" name="code" value="{{ $language['code'] }}">
                <input type="hidden" name="old_code" value="{{ $language['code'] }}">

                <div>
                    <div class="custom-offcanvas-header bg--secondary d-flex justify-content-between align-items-center px-3 py-3">
                        <div class="py-1">
                            <h3 class="mb-0">{{ translate('messages.Edit Language') }}</h3>
                        </div>
                        <button type="button" class="btn-close w-25px h-25px border bg-white rounded-circle d-center text-dark offcanvas-close fz-15px p-0"
                                aria-label="{{ translate('messages.Close') }}">
                            &times;
                        </button>
                    </div>
                    <div class="custom-offcanvas-body custom-offcanvas-body-100">
                        <div class="lang-set-panel">
                            <div class="lang-set-field">
                                <label class="lang-set-label" for="lang-name-{{ $language['code'] }}">
                                    {{ translate('messages.Language') }}
                                </label>
                                <input readonly type="text" class="form-control" id="lang-name-{{ $language['code'] }}"
                                       value="{{ \App\CentralLogics\Helpers::get_language_name($language['code']) }}">
                            </div>
                            <div class="lang-set-field">
                                <span class="lang-set-label">{{ translate('messages.direction') }}</span>
                                <div class="lang-set-seg">
                                    <label class="lang-set-seg-option">
                                        <input type="radio" value="ltr" name="direction"
                                               {{ $language['direction'] === 'ltr' ? 'checked' : '' }}>
                                        <span>{{ translate('messages.Left to Right') }} <small>LTR</small></span>
                                    </label>
                                    <label class="lang-set-seg-option">
                                        <input type="radio" value="rtl" name="direction"
                                               {{ $language['direction'] === 'rtl' ? 'checked' : '' }}>
                                        <span>{{ translate('messages.Right to Left') }} <small>RTL</small></span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="offcanvas-footer d-flex gap-3 justify-content-center align-items-center bg-white bottom-0 mt-auto p-3">
                        <button type="reset" class="btn btn--reset w-100"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                        <button type="submit" class="btn btn--primary w-100"><i class="tio-save"></i> {{ translate('Update') }}</button>
                    </div>
                </div>
            </form>
        </div>
    @endforeach
    <div id="offcanvasOverlay" class="offcanvas-overlay"></div>

    <div class="modal fade" id="delete-modal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-body text-center p-6">
                    <button type="button" class="close position-absolute top-0 right-0 m-3" data-dismiss="modal"
                            aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                    <div class="mb-3">
                        <img src="{{ asset('public/assets/admin/img/modal/delete.png') }}" alt="" class="w-12">
                    </div>
                    <h4 class="modal-title mb-2" id="deleteModalLabel">{{ translate('Want to delete this language?') }}</h4>
                    <p class="mb-4">{{ translate('messages.Deleting a language will remove all associated content. This action cannot be undone.') }}</p>
                    <div class="d-flex justify-content-center gap-3">
                        <a href="" id="delete-link" class="btn btn-danger"><i class="tio-delete-outlined"></i> {{ translate('Yes, delete') }}</a>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="tio-clear-circle-outlined"></i> {{ translate('messages.No, Cancel') }}</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script type="application/json" id="lang-set-strings">
        {{-- json_encode(), not @json(): the directive explodes its expression on
             "," to find the flags argument, so an inline array literal with
             several entries compiles to broken PHP. --}}
        {!! json_encode([
            'index_url' => route('admin.business-settings.language.index'),
            'demo_blocked' => translate('messages.Update option is disabled for demo!'),
            'default_locked' => translate('Default language cannot be updated! To update change the default language first!'),
            'status_updated' => translate('Updated successfully'),
            'request_failed' => translate('messages.Something went wrong. Please try again.'),
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}
    </script>
@endsection

@push('script_2')
    <script src="{{ asset('public/assets/admin/js/view-pages/language-settings.js') }}"></script>
@endpush
