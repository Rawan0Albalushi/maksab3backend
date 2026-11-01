@extends('layouts.admin.app')

@section('title', translate('Marketing Tools'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/marketing-analytics.css') }}">
@endpush

@section('analytics_Script')
    active
@endsection

@section('content')
    @php
        /*
         * The tool list comes from AnalyticScriptController::dataArray(); everything
         * derived from the saved rows is resolved once here so the card markup below
         * stays a single loop.
         */
        $analyticsTools = array_map(function ($tool) use ($analyticsData) {
            $saved = $analyticsData[$tool['key']] ?? null;

            $tool['script_id'] = $saved?->script_id ?? '';
            $tool['is_configured'] = trim((string) $tool['script_id']) !== '';
            $tool['is_on'] = (bool) $saved?->is_active;

            /*
             * analyticStatus() refuses to switch a tool on while it has no ID, but a
             * saved tool can be left active and then have its ID cleared — locking on
             * is_configured alone would strand it with no way to switch it back off.
             */
            $tool['is_locked'] = ! $tool['is_configured'] && ! $tool['is_on'];

            return $tool;
        }, $analyticsTools);

        $live_tools = array_values(array_filter($analyticsTools, fn ($tool) => $tool['is_on']));

        $sections = [
            'analytics' => [
                'title' => translate('Analytics & tag management'),
                'desc' => translate('Measure how visitors find and move through your storefront.'),
            ],
            'pixel' => [
                'title' => translate('Advertising pixels'),
                'desc' => translate('Track conversions and build retargeting audiences for your ad campaigns.'),
            ],
        ];
    @endphp

    <div class="content container-fluid tps mkt">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-chart-bar-4"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('Marketing Tools') }}</h1>
                    <p>{{ translate('Connect analytics and advertising pixels so activity on your storefront is tracked.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#getInformationModal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <div class="mkt-summary">
            <div class="mkt-summary__count">
                <strong>{{ count($live_tools) }}<span class="mkt-summary__total">/{{ count($analyticsTools) }}</span></strong>
                <span>{{ translate('tools switched on') }}</span>
            </div>
            <div class="mkt-summary__list">
                @forelse ($live_tools as $tool)
                    <span class="mkt-chip">
                        <img src="{{ asset('public/assets/admin/img/svg/' . $tool['icon']) }}" alt=""
                             loading="lazy">
                        {{ $tool['title'] }}
                    </span>
                @empty
                    <span class="mkt-summary__empty">
                        {{ translate('No tool is switched on yet — save an ID below, then flip its switch.') }}
                    </span>
                @endforelse
            </div>
        </div>

        <div class="tps-note tps-note--info mb-3">
            <i class="tio-info"></i>
            <div>
                {{ translate('A tool loads only with an ID saved and its switch on. Changes take effect straight away.') }}
            </div>
        </div>

        @foreach ($sections as $slug => $section)
            <div class="mkt-section">
                <div class="mkt-section__head">
                    <h2 class="mkt-section__title">{{ $section['title'] }}</h2>
                    <p class="mkt-section__desc">{{ $section['desc'] }}</p>
                </div>

                <div class="row g-3">
                    @foreach ($analyticsTools as $tool)
                        @continue($tool['group'] !== $slug)

                        @php
                            $title = $tool['title'];
                            $input_id = $tool['key'] . '_script_id';
                            $toggle_id = $tool['key'] . '-status';
                        @endphp

                        <div class="col-xl-6">
                            <div class="tps-card mkt-card {{ $tool['is_on'] ? 'is-on' : '' }}">
                                <div class="tps-card__head mkt-card__head">
                                    <span class="tps-card__brand mkt-brand">
                                        <img src="{{ asset('public/assets/admin/img/svg/' . $tool['icon']) }}"
                                             alt="" loading="lazy">
                                    </span>

                                    <div class="tps-card__titles">
                                        <div class="mkt-title-row">
                                            <h3 class="tps-card__title">{{ $title }}</h3>
                                            @if (! $tool['is_configured'])
                                                <span class="tps-pill tps-pill--warn">{{ translate('Not configured') }}</span>
                                            @elseif ($tool['is_on'])
                                                <span class="tps-pill tps-pill--on">{{ translate('Active') }}</span>
                                            @else
                                                <span class="tps-pill tps-pill--off">{{ translate('Turned off') }}</span>
                                            @endif
                                        </div>
                                        <p class="tps-card__subtitle">{{ translate($tool['summary']) }}</p>
                                    </div>

                                    <div class="tps-card__aside">
                                        <label class="toggle-switch toggle-switch-sm m-0 p-0 {{ $tool['is_locked'] ? 'mkt-switch--locked' : '' }}"
                                               @if ($tool['is_locked']) title="{{ translate('Save an ID first, then the switch can be turned on.') }}" @endif>
                                            <input type="checkbox"
                                                   id="{{ $toggle_id }}"
                                                   data-id="{{ $toggle_id }}"
                                                   data-type="status"
                                                   data-image-on="{{ asset('public/assets/admin/img/svg/' . $tool['icon']) }}"
                                                   data-image-off="{{ asset('public/assets/admin/img/svg/' . $tool['icon']) }}"
                                                   data-title-on="<strong>{{ translate('Turn on this tool?') }}</strong>"
                                                   data-title-off="<strong>{{ translate('Turn off this tool?') }}</strong>"
                                                   data-text-on="<p>{{ translate('Your storefront starts loading this tool right away.') }}</p>"
                                                   data-text-off="<p>{{ translate('Your storefront stops loading this tool right away.') }}</p>"
                                                   class="status toggle-switch-input dynamic-checkbox"
                                                   value="1"
                                                   {{ $tool['is_on'] ? 'checked' : '' }}
                                                   {{ $tool['is_locked'] ? 'disabled' : '' }}>
                                            <span class="toggle-switch-label text p-0">
                                                <span class="toggle-switch-indicator"></span>
                                            </span>
                                        </label>
                                    </div>
                                </div>

                                <form class="mkt-card__form"
                                      action="{{ route('admin.business-settings.marketing.analyticUpdate') }}" method="post">
                                    @csrf
                                    <input type="hidden" name="type" value="{{ $tool['key'] }}">

                                    <div class="tps-card__body">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="{{ $input_id }}">
                                                {{ translate($tool['label']) }}
                                            </label>
                                            {{-- No required attribute: clearing the field is how a stale ID is removed. --}}
                                            <div class="tps-input-wrap">
                                                <textarea id="{{ $input_id }}" name="script_id" rows="1"
                                                          class="form-control mkt-input" spellcheck="false"
                                                          autocomplete="off" autocapitalize="off"
                                                          placeholder="{{ $tool['placeholder'] }}">{{ $tool['script_id'] }}</textarea>
                                                <button type="button" class="tps-input-action tps-copy"
                                                        data-target="#{{ $input_id }}"
                                                        aria-label="{{ translate('messages.Copy') }}">
                                                    <i class="tio-copy"></i>
                                                </button>
                                            </div>
                                            <small class="tps-field__hint">{{ translate($tool['hint']) }}</small>
                                        </div>
                                    </div>

                                    <div class="tps-card__foot">
                                        <a class="mkt-guide" data-toggle="modal" href="#{{ $tool['modal'] }}">
                                            <i class="tio-help-outlined"></i>
                                            {{ translate('How it works') }}
                                        </a>
                                        <button type="{{ getDemoModeFormButton(type: 'button') }}"
                                                class="btn btn--primary {{ getDemoModeFormButton(type: 'class') }}">
                                            <i class="tio-save"></i> {{ translate('messages.Save') }}
                                        </button>
                                    </div>
                                </form>

                                <form action="{{ route('admin.business-settings.marketing.analyticStatus') }}"
                                      id="{{ $toggle_id }}_form" method="get">
                                    <input type="hidden" name="type" value="{{ $tool['key'] }}">
                                </form>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    @includeif('admin-views.business-settings.analytics._information-modal')
    @includeif('admin-views.business-settings.analytics._google-analytics-modal')
    @includeif('admin-views.business-settings.analytics._google-tag-manager-modal')
    @includeif('admin-views.business-settings.analytics._linkedin-insight-modal')
    @includeif('admin-views.business-settings.analytics._facebook-meta-pixel-modal')
    @includeif('admin-views.business-settings.analytics._pinterest-tag-modal')
    @includeif('admin-views.business-settings.analytics._snapchat-tag-modal')
    @includeif('admin-views.business-settings.analytics._tiktok-tag-modal')
    @includeif('admin-views.business-settings.analytics._twitter-modal')
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
