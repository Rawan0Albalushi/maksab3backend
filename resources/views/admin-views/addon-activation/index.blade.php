@extends('layouts.admin.app')

@section('title', translate('Add-on activation'))

@push('css_or_js')
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/addon-activation.css') }}">
@endpush

@section('content')
    @php
        /*
         * One query instead of the three inline lookups this page used to run,
         * and one definition list instead of three copies of the same card.
         */
        $addons = [
            [
                'addon_name' => 'vendor_app',
                'key' => 'addon_activation_vendor_app',
                'software_id' => 'MzY3NzIxNzM=',
                'icon' => 'tio-shop-outlined',
                'image' => 'seller-app',
                'title' => translate('Vendor app'),
                'summary' => translate('With this app your vendor will manage their business through mobile app'),
            ],
            [
                'addon_name' => 'deliveryman_app',
                'key' => 'addon_activation_delivery_man_app',
                'software_id' => 'MzY3NzIxNDg=',
                'icon' => 'tio-bike',
                'image' => 'dm-app',
                'title' => translate('Deliveryman app'),
                'summary' => translate('With this app your deliverymen will manage their orders through mobile app'),
            ],
            [
                'addon_name' => 'react_web',
                'key' => 'addon_activation_react',
                'software_id' => 'NDUzNzAzNTE=',
                'icon' => 'tio-monitor',
                'image' => 'user-app',
                'title' => translate('messages.React User Website'),
                'summary' => translate('With this react website your customers will experience your system in a more attractive and seamless way'),
            ],
        ];

        $saved_values = \App\Models\BusinessSetting::whereIn('key', array_column($addons, 'key'))->pluck('value', 'key');

        $addons = array_map(function ($addon) use ($saved_values) {
            $value = json_decode($saved_values[$addon['key']] ?? '', true);
            $value = is_array($value) ? $value : [];

            $addon['username'] = $value['username'] ?? '';
            $addon['purchase_key'] = $value['purchase_key'] ?? '';
            $addon['is_on'] = (int) ($value['activation_status'] ?? 0) === 1;
            $addon['is_configured'] = $addon['username'] !== '' && $addon['purchase_key'] !== '';

            return $addon;
        }, $addons);

        $active_count = count(array_filter($addons, fn ($addon) => $addon['is_on']));
    @endphp

    <div class="content container-fluid tps adn">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-puzzle"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('Add-on activation') }}</h1>
                    <p>{{ translate('Activate the add-ons you purchased with your CodeCanyon licence.') }}</p>
                </span>
            </div>

            <button type="button" class="tps-help" data-toggle="modal" data-target="#addon-help-modal">
                <i class="tio-help-outlined"></i>
                <span>{{ translate('How it works') }}</span>
            </button>
        </div>

        <div class="adn-summary">
            <div class="adn-summary__count">
                <strong>{{ $active_count }}<span class="adn-summary__total">/{{ count($addons) }}</span></strong>
                <span>{{ translate('add-ons activated') }}</span>
            </div>
            <div class="adn-summary__domain">
                <p class="tps-group__label">{{ translate('Licensed domain') }}</p>
                <div class="tps-readonly">
                    <span class="tps-readonly__value" id="addon_current_domain">{{ $domain }}</span>
                    <button type="button" class="tps-readonly__copy tps-copy" data-target="#addon_current_domain">
                        <i class="tio-copy"></i> {{ translate('messages.Copy') }}
                    </button>
                </div>
            </div>
        </div>

        <div class="tps-note tps-note--info mb-3">
            <i class="tio-info"></i>
            <div>
                {{ translate('Each purchase code activates one domain. Flipping a switch only stages it — press Save.') }}
            </div>
        </div>

        <div class="d-flex flex-column gap-3">
            @foreach ($addons as $addon)
                @php
                    $panel_id = $addon['key'] . '_panel';
                    $toggle_id = $addon['key'] . '_status';
                    $expanded = ! $addon['is_configured'];
                @endphp

                <div class="tps-card adn-card {{ $addon['is_on'] ? 'is-on' : '' }}">
                    <form action="{{ route('admin.business-settings.addon-activation.activation') }}" method="post">
                        @csrf
                        <input type="hidden" name="addon_name" value="{{ $addon['addon_name'] }}">
                        <input type="hidden" name="software_type" value="addon">
                        <input type="hidden" name="software_id" value="{{ $addon['software_id'] }}">
                        <input type="hidden" name="key" value="{{ $addon['key'] }}">

                        <div class="tps-card__head adn-card__head is-clickable">
                            <span class="tps-card__brand adn-brand"><i class="{{ $addon['icon'] }}"></i></span>

                            <div class="tps-card__titles">
                                <div class="adn-title-row">
                                    <h2 class="tps-card__title">{{ $addon['title'] }}</h2>
                                    @if ($addon['is_on'])
                                        <span class="tps-pill tps-pill--on">{{ translate('Active') }}</span>
                                    @elseif ($addon['is_configured'])
                                        <span class="tps-pill tps-pill--off">{{ translate('Turned off') }}</span>
                                    @else
                                        <span class="tps-pill tps-pill--warn">{{ translate('Not configured') }}</span>
                                    @endif
                                </div>
                                <p class="tps-card__subtitle">{{ $addon['summary'] }}</p>
                            </div>

                            <div class="tps-card__aside">
                                <button type="button" class="adn-disclose" aria-controls="{{ $panel_id }}"
                                        aria-expanded="{{ $expanded ? 'true' : 'false' }}"
                                        data-panel="#{{ $panel_id }}">
                                    <span>{{ translate('Licence') }}</span>
                                    <i class="tio-chevron-down"></i>
                                </button>

                                <label class="toggle-switch toggle-switch-sm m-0 p-0">
                                    <input type="checkbox"
                                           id="{{ $toggle_id }}"
                                           data-id="{{ $toggle_id }}"
                                           data-type="toggle"
                                           data-image-on="{{ asset('public/assets/admin/img/modal/' . $addon['image'] . '-on.png') }}"
                                           data-image-off="{{ asset('public/assets/admin/img/modal/' . $addon['image'] . '-off.png') }}"
                                           data-title-on="<strong>{{ translate('Turn on this add-on?') }}</strong>"
                                           data-title-off="<strong>{{ translate('Turn off this add-on?') }}</strong>"
                                           data-text-on="<p>{{ translate('Your licence will be checked against this domain when you save.') }}</p>"
                                           data-text-off="<p>{{ translate('The add-on stops working on this domain once you save.') }}</p>"
                                           class="status toggle-switch-input dynamic-checkbox-toggle"
                                           name="status" value="1" {{ $addon['is_on'] ? 'checked' : '' }}>
                                    <span class="toggle-switch-label text p-0">
                                        <span class="toggle-switch-indicator"></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <div class="adn-panel" id="{{ $panel_id }}" @if ($expanded) style="display: block;" @endif>
                            <div class="tps-card__body adn-card__body">
                                <div class="adn-fields">
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label class="tps-field__label" for="{{ $addon['key'] }}_username">
                                                    {{ translate('messages.CodeCanyon Username') }} <span class="tps-req">*</span>
                                                </label>
                                                <div class="tps-input-wrap">
                                                    <input type="text" id="{{ $addon['key'] }}_username" name="username"
                                                           class="form-control" autocomplete="off" required
                                                           placeholder="{{ translate('Example') }}: envato_buyer"
                                                           value="{{ showDemoModeInputValue(value: $addon['username']) }}">
                                                    <button type="button" class="tps-input-action tps-copy"
                                                            data-target="#{{ $addon['key'] }}_username"
                                                            aria-label="{{ translate('messages.Copy') }}">
                                                        <i class="tio-copy"></i>
                                                    </button>
                                                </div>
                                                <small class="tps-field__hint">
                                                    {{ translate('The Envato account name the add-on was purchased with.') }}
                                                </small>
                                            </div>
                                        </div>

                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label class="tps-field__label" for="{{ $addon['key'] }}_purchase_key">
                                                    {{ translate('messages.CodeCanyon Purchase Code') }} <span class="tps-req">*</span>
                                                </label>
                                                <div class="tps-input-wrap has-two-actions">
                                                    <input type="password" id="{{ $addon['key'] }}_purchase_key" name="purchase_key"
                                                           class="form-control" autocomplete="off" required
                                                           placeholder="••••••••-••••-••••-••••-••••••••••••"
                                                           value="{{ showDemoModeInputValue(value: $addon['purchase_key']) }}">
                                                    <button type="button" class="tps-input-action tps-copy"
                                                            data-target="#{{ $addon['key'] }}_purchase_key"
                                                            aria-label="{{ translate('messages.Copy') }}">
                                                        <i class="tio-copy"></i>
                                                    </button>
                                                    <button type="button" class="tps-input-action tps-toggle-secret"
                                                            data-target="#{{ $addon['key'] }}_purchase_key"
                                                            aria-label="{{ translate('Show value') }}">
                                                        <i class="tio-visible"></i>
                                                    </button>
                                                </div>
                                                <small class="tps-field__hint">
                                                    {{ translate('Found under Downloads → Licence certificate on your Envato account.') }}
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="tps-card__foot">
                                <span class="tps-foot-note">
                                    {{ translate('Saving re-checks the licence with the activation server.') }}
                                </span>
                                <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('messages.Reset') }}</button>
                                <button type="{{ getDemoModeFormButton(type: 'button') }}"
                                        class="btn btn--primary {{ getDemoModeFormButton(type: 'class') }}">
                                    <i class="tio-save"></i> {{ translate('messages.Save') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>

    <div class="modal fade" id="addon-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('Activating an add-on') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Sign in to Envato and open Downloads for the add-on you purchased.') }}</li>
                        <li>{{ translate('Download the licence certificate and copy the item purchase code from it.') }}</li>
                        <li>{{ translate('Paste your Envato username and that purchase code into the add-on below.') }}</li>
                        <li>{{ translate('Switch the add-on on and press Save — the licence is checked against the domain shown above.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--warn">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('A purchase code is tied to one domain. Release the licence before moving the site.') }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')

    <script>
        "use strict";

        /*
         * Own accordion rather than the global .view-btn helper in admin.js: the
         * panel holds the Save button, so it also has to open by itself when the
         * switch is flipped, otherwise the staged change has nowhere to be saved.
         */
        function adnTogglePanel($trigger, forceOpen) {
            const $panel = $($trigger.data('panel'));

            if (!$panel.length) {
                return;
            }

            const open = typeof forceOpen === 'boolean' ? forceOpen : !$panel.is(':visible');

            $trigger.attr('aria-expanded', open ? 'true' : 'false');
            open ? $panel.stop(true, true).slideDown(220) : $panel.stop(true, true).slideUp(220);
        }

        $(document).on('click', '.adn .adn-disclose', function (event) {
            event.preventDefault();
            event.stopPropagation();
            adnTogglePanel($(this));
        });

        /* The whole header row opens the panel, minus the switch sitting in it. */
        $(document).on('click', '.adn .adn-card__head.is-clickable', function (event) {
            if ($(event.target).closest('.toggle-switch, .adn-disclose').length) {
                return;
            }

            adnTogglePanel($(this).find('.adn-disclose'));
        });

        /*
         * .dynamic-checkbox-toggle only flips the box after the confirm modal, so
         * "change" is the point where the admin still has to reach Save.
         */
        $(document).on('change', '.adn .dynamic-checkbox-toggle', function () {
            adnTogglePanel($(this).closest('.adn-card').find('.adn-disclose'), true);
        });
    </script>
@endpush
