@extends('layouts.admin.app')

@section('title', translate('App settings'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/app-settings.css') }}">
@endpush

@section('content')
    @php
        /*
         * One definition list instead of five copies of the same version-control
         * card. The field names are derived from each app's suffix, which is the
         * only thing that actually differs between them.
         */
        $apps = [
            [
                'type' => 'user_app',
                'suffix' => '',
                'anchor' => 'app-user',
                'icon' => 'tio-user-outlined',
                'title' => translate('User app'),
                'summary' => translate('Minimum version and store links for the customer app.'),
                'version_hint' => translate('Customers on an older version are asked to update before they can keep using the app.'),
                'url_hint' => translate('Where customers are sent to install or update the app.'),
                'foot_note' => translate('This link also feeds the app download buttons on your landing pages.'),
                'show' => true,
            ],
            [
                'type' => 'store_app',
                'suffix' => '_store',
                'anchor' => 'app-store',
                'icon' => 'tio-shop-outlined',
                'title' => translate('Store app'),
                'summary' => translate('Minimum version and store links for the vendor app.'),
                'version_hint' => translate('Vendors on an older version are asked to update before they can keep using the app.'),
                'url_hint' => translate('Where vendors are sent to install or update the app.'),
                'foot_note' => translate('Saving also updates the app download links stored for your landing pages.'),
                'show' => true,
            ],
            [
                'type' => 'deliveryman_app',
                'suffix' => '_deliveryman',
                'anchor' => 'app-deliveryman',
                'icon' => 'tio-bike',
                'title' => translate('Deliveryman app'),
                'summary' => translate('Minimum version and store links for the deliveryman app.'),
                'version_hint' => translate('Deliverymen on an older version are asked to update before they can keep using the app.'),
                'url_hint' => translate('Where deliverymen are sent to install or update the app.'),
                'foot_note' => translate('Saving also updates the app download links stored for your landing pages.'),
                'show' => true,
            ],
            [
                'type' => 'rider_app',
                'suffix' => '_rider',
                'anchor' => 'app-rider',
                'icon' => 'tio-motocycle',
                'title' => translate('Rider app'),
                'summary' => translate('Minimum version and store links for the ride share driver app.'),
                'version_hint' => translate('Riders on an older version are asked to update before they can keep using the app.'),
                'url_hint' => translate('Where riders are sent to install or update the app.'),
                'foot_note' => translate('Saving also updates the app download links stored for your landing pages.'),
                'show' => addon_published_status('RideShare'),
            ],
            [
                'type' => 'serviceman_app',
                'suffix' => '_serviceman',
                'anchor' => 'app-serviceman',
                'icon' => 'tio-tools',
                'title' => translate('Serviceman app'),
                'summary' => translate('Minimum version and store links for the service provider app.'),
                'version_hint' => translate('Servicemen on an older version are asked to update before they can keep using the app.'),
                'url_hint' => translate('Where servicemen are sent to install or update the app.'),
                'foot_note' => translate('Saving also updates the app download links stored for your landing pages.'),
                'show' => addon_published_status('Service'),
            ],
        ];

        $apps = array_values(array_filter($apps, fn ($app) => $app['show']));

        $business_setting_keys = ['language'];

        foreach ($apps as $app) {
            $business_setting_keys[] = 'app_minimum_version_android' . $app['suffix'];
            $business_setting_keys[] = 'app_url_android' . $app['suffix'];
            $business_setting_keys[] = 'app_minimum_version_ios' . $app['suffix'];
            $business_setting_keys[] = 'app_url_ios' . $app['suffix'];
        }

        $business_settings = collect(\App\CentralLogics\Helpers::get_business_settings_many($business_setting_keys));

        $language = $business_settings->get('language');

        $apps = array_map(function ($app) use ($business_settings) {
            /* Platform and store names stay untranslated: they are proper nouns,
               translate() rejects the bare platform tokens by design, and its
               fallback would render 'iOS' as 'IOS'. */
            $platforms = [
                [
                    'name' => 'Android',
                    'store' => 'Google Play',
                    'logo' => 'andriod.png',
                    'version_field' => 'app_minimum_version_android' . $app['suffix'],
                    'url_field' => 'app_url_android' . $app['suffix'],
                    'url_placeholder' => 'https://play.google.com/store/apps/details?id=',
                ],
                [
                    'name' => 'iOS',
                    'store' => 'App Store',
                    'logo' => 'ios.png',
                    'version_field' => 'app_minimum_version_ios' . $app['suffix'],
                    'url_field' => 'app_url_ios' . $app['suffix'],
                    'url_placeholder' => 'https://apps.apple.com/app/id',
                ],
            ];

            $filled = 0;

            foreach ($platforms as $index => $platform) {
                $version = $business_settings->get($platform['version_field']);
                $url = $business_settings->get($platform['url_field']);
                $set = (int) filled($version) + (int) filled($url);

                $platforms[$index]['version'] = $version;
                $platforms[$index]['url'] = $url;
                $platforms[$index]['state'] = $set === 2 ? 'on' : ($set === 1 ? 'warn' : 'off');

                $filled += $set;
            }

            $app['platforms'] = $platforms;
            $app['state'] = $filled === 4 ? 'on' : ($filled > 0 ? 'warn' : 'off');

            return $app;
        }, $apps);

        $state_labels = [
            'on' => translate('Configured'),
            'warn' => translate('Partly configured'),
            'off' => translate('Not configured'),
        ];

        $platform_labels = [
            'on' => translate('Ready'),
            'warn' => translate('Incomplete'),
            'off' => translate('Empty'),
        ];

        $configured_count = count(array_filter($apps, fn ($app) => $app['state'] === 'on'));

        $app_settings = \App\Models\DataSetting::withoutGlobalScope('translate')->with('translations')
            ->where('type', 'app_settings')
            ->whereIn('key', ['download_user_app_section_status', 'download_user_app_title'])
            ->get()
            ->keyBy('key');

        $download_user_app_section_status = $app_settings->get('download_user_app_section_status');
        $download_user_app_title = $app_settings->get('download_user_app_title');
        $download_section_on = (bool) $download_user_app_section_status?->value;
        $download_user_app_title_value = $download_user_app_title?->getRawOriginal('value') ?? '';
    @endphp

    <div class="content container-fluid tps aps">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon">
                    <img src="{{ asset('public/assets/admin/img/outline/setting.svg') }}" alt="">
                </span>
                <span class="tps-head__text">
                    <h1>{{ translate('App settings') }}</h1>
                    <p>{{ translate('Control the minimum version each mobile app may run, and where users download it.') }}</p>
                </span>
            </div>

            <div class="aps-head__aside">
                <span class="aps-count">
                    <strong>{{ $configured_count }}/{{ count($apps) }}</strong>
                    <span>{{ translate('Apps fully configured') }}</span>
                </span>
                <button type="button" class="tps-help" data-toggle="modal" data-target="#app-settings-help-modal">
                    <i class="tio-help-outlined"></i>
                    <span>{{ translate('How it works') }}</span>
                </button>
            </div>
        </div>

        <nav class="tps-nav" aria-label="{{ translate('App settings sections') }}">
            <div class="tps-nav__scroll">
                <a class="tps-nav__item" href="#app-download-section">
                    <i class="tio-download-to"></i>
                    <span>{{ translate('Download section') }}</span>
                    @if ($download_section_on)
                        <span class="tps-nav__dot"></span>
                    @endif
                </a>
                @foreach ($apps as $app)
                    <a class="tps-nav__item" href="#{{ $app['anchor'] }}">
                        <i class="{{ $app['icon'] }}"></i>
                        <span>{{ $app['title'] }}</span>
                        <span class="tps-nav__dot {{ $app['state'] === 'on' ? '' : ($app['state'] === 'warn' ? 'tps-nav__dot--warn' : 'tps-nav__dot--off') }}"></span>
                    </a>
                @endforeach
            </div>
        </nav>

        <div class="tps-note tps-note--info mb-3">
            <i class="tio-info-outined"></i>
            <div>
                {{ translate('Anyone running a version below the minimum is blocked until they update, so raise it only once the new build is live on the store.') }}
            </div>
        </div>

        <form action="{{ route('admin.business-settings.app-settings-update') }}" method="post">
            @csrf
            <input type="hidden" name="type" value="download_section">

            <div class="tps-card mb-3" id="app-download-section">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-download-to"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{ translate('User App Download Section') }}</h2>
                        <p class="tps-card__subtitle">
                            {{ translate('The download block customers see inside the app, with the heading shown above the store buttons.') }}
                        </p>
                    </div>
                    <div class="tps-card__aside">
                        <span class="tps-pill {{ $download_section_on ? 'tps-pill--on' : 'tps-pill--off' }}"
                              id="download_section_pill"
                              data-label-on="{{ translate('Shown') }}"
                              data-label-off="{{ translate('Hidden') }}">
                            {{ $download_section_on ? translate('Shown') : translate('Hidden') }}
                        </span>
                        <label class="toggle-switch toggle-switch-sm p-0 m-0" for="CheckboxStatus">
                            <input type="checkbox"
                                   class="toggle-switch-input dynamic-checkbox-toggle"
                                   id="CheckboxStatus"
                                   data-id="CheckboxStatus"
                                   data-type="toggle"
                                   data-image-on="{{ asset('/public/assets/admin/img/status-ons.png') }}"
                                   data-image-off="{{ asset('/public/assets/admin/img/off-danger.png') }}"
                                   data-title-on="{{ translate('Do you want to turn on this section?') }}"
                                   data-title-off="{{ translate('Do you want to turn off this section?') }}"
                                   data-text-on="<p>{{ translate('If you turn on, this section will be shown in the app.') }}</p>"
                                   data-text-off="<p>{{ translate('If you turn off, this section will not be shown in the app.') }}</p>"
                                   name="download_user_app_section_status"
                                   aria-label="{{ translate('User App Download Section') }}"
                                   value="1"
                                   {{ $download_section_on ? 'checked' : '' }}>
                            <span class="toggle-switch-label text p-0">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="tps-card__body">
                    <div class="aps-langbar">
                        @if ($language)
                            <ul class="nav nav-tabs">
                                <li class="nav-item">
                                    <a class="nav-link lang_link active" href="#" id="default-link">
                                        {{ translate('Default') }}
                                    </a>
                                </li>
                                @foreach (json_decode($language) as $lang)
                                    <li class="nav-item">
                                        <a class="nav-link lang_link" href="#" id="{{ $lang }}-link">
                                            {{ \App\CentralLogics\Helpers::get_language_name($lang) . ' (' . strtoupper($lang) . ')' }}
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        <div class="aps-langbar__body">
                            <div class="lang_form default-form">
                                <div class="tps-field">
                                    <label class="tps-field__label" for="download_user_app_title">
                                        {{ translate('Title') }}
                                        <span class="tps-req">*</span>
                                    </label>
                                    <input id="download_user_app_title"
                                           type="text"
                                           maxlength="60"
                                           name="download_user_app_title[]"
                                           class="form-control"
                                           value="{{ $download_user_app_title_value }}"
                                           placeholder="{{ translate('Enter title') }}">
                                    <div class="aps-field__foot">
                                        <span class="tps-field__hint">
                                            {{ translate('Heading shown above the store buttons. Keep it short.') }}
                                        </span>
                                        <span class="aps-counter text-counting">{{ strlen($download_user_app_title_value) }}/60</span>
                                    </div>
                                </div>
                            </div>
                            <input type="hidden" name="lang[]" value="default">

                            @if ($language)
                                @foreach (json_decode($language) as $lang)
                                    @php
                                        $download_user_app_title_translate = '';
                                        if (isset($download_user_app_title->translations) && count($download_user_app_title->translations)) {
                                            foreach ($download_user_app_title->translations as $translation) {
                                                if ($translation->locale == $lang && $translation->key == 'download_user_app_title') {
                                                    $download_user_app_title_translate = $translation->value;
                                                }
                                            }
                                        }
                                    @endphp
                                    <div class="lang_form d-none" id="{{ $lang }}-form1">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="download_user_app_title_{{ $lang }}">
                                                {{ translate('Title') }}
                                                <span class="tps-opt">({{ strtoupper($lang) }})</span>
                                            </label>
                                            <input id="download_user_app_title_{{ $lang }}"
                                                   type="text"
                                                   maxlength="60"
                                                   name="download_user_app_title[]"
                                                   class="form-control"
                                                   value="{{ $download_user_app_title_translate }}"
                                                   placeholder="{{ translate('Enter title') }}">
                                            <div class="aps-field__foot">
                                                <span class="tps-field__hint">
                                                    {{ translate('Leave empty to fall back to the default title.') }}
                                                </span>
                                                <span class="aps-counter text-counting">{{ strlen($download_user_app_title_translate) }}/60</span>
                                            </div>
                                        </div>
                                    </div>
                                    <input type="hidden" name="lang[]" value="{{ $lang }}">
                                @endforeach
                            @endif
                        </div>
                    </div>
                </div>

                <div class="tps-card__foot">
                    <span class="tps-foot-note">
                        {{ translate('Flipping the switch only stages the change — press save to apply it.') }}
                    </span>
                    <button type="reset" class="btn btn--reset">
                        <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                    </button>
                    <button type="submit" class="btn btn--primary call-demo">
                        <i class="tio-save"></i> {{ translate('messages.Save') }}
                    </button>
                </div>
            </div>
        </form>

        @foreach ($apps as $app)
            <form action="{{ route('admin.business-settings.app-settings-update') }}" method="post">
                @csrf
                <input type="hidden" name="type" value="{{ $app['type'] }}">

                <div class="tps-card mb-3" id="{{ $app['anchor'] }}">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="{{ $app['icon'] }}"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ $app['title'] }}</h2>
                            <p class="tps-card__subtitle">{{ $app['summary'] }}</p>
                        </div>
                        <div class="tps-card__aside">
                            <span class="tps-pill tps-pill--{{ $app['state'] }}">{{ $state_labels[$app['state']] }}</span>
                        </div>
                    </div>

                    <div class="tps-card__body">
                        <div class="aps-grid">
                            @foreach ($app['platforms'] as $platform)
                                <div class="aps-plat">
                                    <div class="aps-plat__head">
                                        <span class="aps-plat__logo">
                                            <img src="{{ asset('public/assets/admin/img/' . $platform['logo']) }}" alt="">
                                        </span>
                                        <span class="aps-plat__titles">
                                            <span class="aps-plat__name">{{ $platform['name'] }}</span>
                                            <span class="aps-plat__store">{{ $platform['store'] }}</span>
                                        </span>
                                        <span class="tps-pill tps-pill--{{ $platform['state'] }}">{{ $platform_labels[$platform['state']] }}</span>
                                    </div>

                                    <div class="aps-plat__body">
                                        <div class="tps-field">
                                            <label class="tps-field__label" for="{{ $platform['version_field'] }}">
                                                {{ translate('Minimum app version') }}
                                            </label>
                                            <input id="{{ $platform['version_field'] }}"
                                                   name="{{ $platform['version_field'] }}"
                                                   type="number"
                                                   step="0.001"
                                                   min="0"
                                                   class="form-control"
                                                   placeholder="1.0"
                                                   value="{{ $platform['version'] ?? '' }}">
                                            <span class="tps-field__hint">{{ $app['version_hint'] }}</span>
                                        </div>

                                        <div class="tps-field">
                                            <label class="tps-field__label" for="{{ $platform['url_field'] }}">
                                                {{ translate('Download URL') }}
                                            </label>
                                            <div class="tps-input-wrap">
                                                <input id="{{ $platform['url_field'] }}"
                                                       name="{{ $platform['url_field'] }}"
                                                       type="url"
                                                       class="form-control"
                                                       placeholder="{{ $platform['url_placeholder'] }}"
                                                       value="{{ $platform['url'] ?? '' }}"
                                                       data-link-validation
                                                       data-link-validation-message="{{ translate('Enter a complete web address for the store page.') }}">
                                                <a class="tps-input-action aps-open-link {{ filled($platform['url']) ? '' : 'is-hidden' }}"
                                                   href="{{ $platform['url'] ?? '#' }}"
                                                   target="_blank"
                                                   rel="noopener"
                                                   data-for="{{ $platform['url_field'] }}"
                                                   data-toggle="tooltip"
                                                   aria-label="{{ translate('Open store page') }}"
                                                   title="{{ translate('Open store page') }}">
                                                    <i class="tio-open-in-new"></i>
                                                </a>
                                            </div>
                                            <span class="tps-field__hint">{{ $app['url_hint'] }}</span>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="tps-card__foot">
                        <span class="tps-foot-note">{{ $app['foot_note'] }}</span>
                        <button type="reset" class="btn btn--reset">
                            <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                        </button>
                        <button type="submit" class="btn btn--primary call-demo">
                            <i class="tio-save"></i> {{ translate('messages.Save') }}
                        </button>
                    </div>
                </div>
            </form>
        @endforeach
    </div>

    <div class="modal fade" id="app-settings-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            {{-- The modal sits outside the page's .tps wrapper, so it carries the
                 root class itself or .tps-note has no tokens to draw with. --}}
            <div class="modal-content tps">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('How app version control works') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Publish the new build on Google Play or the App Store and wait until it is live.') }}</li>
                        <li>{{ translate('Copy the store page link into the download URL for that platform.') }}</li>
                        <li>{{ translate('Set the minimum version to the oldest build you still want to support.') }}</li>
                        <li>{{ translate('Press save. Apps below the minimum show the update screen and send users to the URL.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('Setting a minimum version higher than the build on the store locks everyone out of the app.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    <script>
        "use strict";

        $(function () {
            /* Keep the trailing "open store page" action pointed at what is typed,
               and out of the way while the field holds nothing usable. */
            $('.aps .aps-open-link').each(function () {
                const $link = $(this);
                const $input = $('#' + $link.data('for'));

                if (!$input.length) {
                    return;
                }

                const sync = function () {
                    const value = $input.val().trim();
                    const usable = /^https?:\/\/\S+\.\S+/i.test(value);

                    $link.attr('href', usable ? value : '#').toggleClass('is-hidden', !usable);
                };

                $input.on('input change', sync);
                sync();
            });

            /* Mark the section the reader is in. The page is six cards long, so
               the nav is worth keeping honest. Position is measured rather than
               observed: an IntersectionObserver band leaves nothing marked
               whenever no card happens to sit inside it, and on this page that
               is the whole of the first screen. */
            const navItems = document.querySelectorAll('.aps .tps-nav__item');
            const cards = [];
            const itemFor = {};

            navItems.forEach(function (item) {
                const card = document.querySelector(item.getAttribute('href'));

                if (card) {
                    cards.push(card);
                    itemFor[card.id] = item;
                }
            });

            if (cards.length) {
                const markCurrent = function () {
                    let current = cards[0];

                    cards.forEach(function (card) {
                        if (card.getBoundingClientRect().top <= 160) {
                            current = card;
                        }
                    });

                    navItems.forEach(function (item) {
                        item.classList.remove('is-active');
                    });
                    itemFor[current.id].classList.add('is-active');
                };

                let queued = false;

                /* Capture phase: scroll does not bubble, and the panel shell may
                   scroll an inner element rather than the window. */
                document.addEventListener('scroll', function () {
                    if (queued) {
                        return;
                    }

                    queued = true;
                    window.requestAnimationFrame(function () {
                        queued = false;
                        markCurrent();
                    });
                }, { passive: true, capture: true });

                markCurrent();
            }

            /* The download-section switch only stages its change, so the pill has to
               follow the checkbox rather than the saved value. */
            const $pill = $('#download_section_pill');

            $('#CheckboxStatus').on('change', function () {
                const on = this.checked;

                $pill.text(on ? $pill.data('label-on') : $pill.data('label-off'))
                    .toggleClass('tps-pill--on', on)
                    .toggleClass('tps-pill--off', !on);
            });
        });
    </script>
@endpush
