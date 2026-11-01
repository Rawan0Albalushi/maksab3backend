@extends('layouts.admin.app')

@section('title', translate('WebSocket configuration'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/websocket.css') }}">
@endpush

@section('content')
    @php
        /*
         * One read for the three keys the page owns, instead of a ->first() per
         * field scattered through the markup.
         */
        $websocket_settings = collect(\App\CentralLogics\Helpers::get_business_settings_many([
            'websocket_status',
            'websocket_url',
            'websocket_port',
        ]));

        $is_demo = getEnvMode() == 'demo';

        $websocket_status = (int) ($websocket_settings->get('websocket_status') ?? 0) === 1;
        $websocket_url = (string) ($websocket_settings->get('websocket_url') ?? '');
        $websocket_port = (string) ($websocket_settings->get('websocket_port') ?? '');

        $is_configured = filled($websocket_url) && filled($websocket_port);
        $is_secure = \Illuminate\Support\Str::startsWith(strtolower($websocket_url), 'wss://');

        /* The one string the apps dial. Shown back so the admin can check the two
           fields read as the endpoint they meant, and copy it into a client. */
        $endpoint = $is_configured
            ? rtrim($websocket_url, '/') . ':' . $websocket_port
            : '';

        $uses = [
            [
                'icon' => 'tio-shop-outlined',
                'title' => translate('Live business details'),
                'text' => translate('Setting changes reach open sessions without anyone reloading.'),
            ],
            [
                'icon' => 'tio-notifications-on-outlined',
                'title' => translate('Instant notifications'),
                'text' => translate('Order and configuration alerts land the moment they happen.'),
            ],
            [
                'icon' => 'tio-refresh',
                'title' => translate('Subscription updates'),
                'text' => translate('Vendors see subscription status changes as they are applied.'),
            ],
        ];
    @endphp

    <div class="content container-fluid tps wsk">
        <div class="tps-head">
            <div class="tps-head__title">
                <span class="tps-head__icon"><i class="tio-wifi"></i></span>
                <span class="tps-head__text">
                    <h1>{{ translate('WebSocket configuration') }}</h1>
                    <p>{{ translate('Point the apps at your WebSocket server so updates arrive without a refresh.') }}</p>
                </span>
            </div>

            <div class="wsk-head__aside">
                <span class="wsk-status {{ $websocket_status && $is_configured ? 'is-live' : '' }}"
                      id="websocket_status_chip"
                      data-label-on="{{ translate('Connection on') }}"
                      data-label-off="{{ translate('Connection off') }}">
                    <span class="wsk-status__dot"></span>
                    <span class="wsk-status__text">
                        {{ $websocket_status ? translate('Connection on') : translate('Connection off') }}
                    </span>
                </span>
                <button type="button" class="tps-help" data-toggle="modal" data-target="#websocket-help-modal">
                    <i class="tio-help-outlined"></i>
                    <span>{{ translate('How it works') }}</span>
                </button>
            </div>
        </div>

        <div class="tps-note tps-note--info mb-3">
            <i class="tio-info-outined"></i>
            <div>
                {{ translate('The WebSocket server has to be running and reachable on this address before the switch does anything. Restart the app after saving.') }}
                <a target="_blank" rel="noopener"
                   href="https://6ammart.app/documentation/admin-application-configuration/3rd-party-setup/">{{ translate('messages.Get Credential Setup') }}</a>
            </div>
        </div>

        <form action="{{ ! $is_demo ? route('admin.business-settings.update-websocket') : 'javascript:' }}" method="post">
            @csrf

            <div class="tps-card mb-3">
                <div class="tps-card__head">
                    <span class="tps-card__brand"><i class="tio-router"></i></span>
                    <div class="tps-card__titles">
                        <h2 class="tps-card__title">{{ translate('Real-time connection') }}</h2>
                        <p class="tps-card__subtitle">
                            {{ translate('Enable real-time updates by configuring your WebSocket connection') }}
                        </p>
                    </div>
                    <div class="tps-card__aside">
                        <span class="tps-pill {{ $websocket_status ? 'tps-pill--on' : 'tps-pill--off' }}"
                              id="websocket_pill"
                              data-label-on="{{ translate('messages.Active') }}"
                              data-label-off="{{ translate('messages.Inactive') }}">
                            {{ $websocket_status ? translate('messages.Active') : translate('messages.Inactive') }}
                        </span>
                        <label class="toggle-switch toggle-switch-sm p-0 m-0" for="websocket">
                            <input type="checkbox"
                                   id="websocket"
                                   data-id="websocket"
                                   data-type="toggle"
                                   data-image-on="{{ asset('/public/assets/admin/img/modal/schedule-on.png') }}"
                                   data-image-off="{{ asset('/public/assets/admin/img/modal/schedule-off.png') }}"
                                   data-title-on="{{ translate('messages.Want to enable') }} <strong>{{ translate('WebSocket configuration') }}</strong>"
                                   data-title-off="{{ translate('messages.Want to disable') }} <strong>{{ translate('WebSocket configuration') }}</strong>"
                                   data-text-on="<p>{{ translate('If you enable this, the deliveryman\'s last location will be recorded by websocket.') }}</p>"
                                   data-text-off="<p>{{ translate('If you disable this, deliveryman last location will be recorded by default method.') }}</p>"
                                   class="status toggle-switch-input dynamic-checkbox-toggle"
                                   name="websocket_status"
                                   aria-label="{{ translate('WebSocket configuration') }}"
                                   value="1"
                                   {{ $websocket_status ? 'checked' : '' }}>
                            <span class="toggle-switch-label text p-0">
                                <span class="toggle-switch-indicator"></span>
                            </span>
                        </label>
                    </div>
                </div>

                <div class="tps-card__body">
                    <div class="tps-group">
                        <p class="tps-group__label">{{ translate('Server address') }}</p>

                        <div class="row g-3">
                            <div class="col-md-7">
                                <div class="tps-field">
                                    <label for="websocket_url" class="tps-field__label">
                                        {{ translate('messages.Websocket url') }} <span class="tps-req">*</span>
                                    </label>
                                    <div class="tps-input-wrap">
                                        <input type="text" id="websocket_url" name="websocket_url"
                                               class="form-control" autocomplete="off" spellcheck="false"
                                               value="{{ $websocket_url }}"
                                               placeholder="{{ translate('messages.Ex') . ' : wss://socket.example.com' }}"
                                               required>
                                        <button type="button" class="tps-input-action tps-copy"
                                                data-target="#websocket_url" aria-label="{{ translate('messages.Copy') }}">
                                            <i class="tio-copy"></i>
                                        </button>
                                    </div>
                                    <small class="tps-field__hint">
                                        {{ translate('Must start with ws:// or wss://. Leave the port out of this field.') }}
                                    </small>
                                </div>
                            </div>

                            <div class="col-md-5">
                                <div class="tps-field">
                                    <label for="websocket_port" class="tps-field__label">
                                        {{ translate('messages.Websocket port') }} <span class="tps-req">*</span>
                                    </label>
                                    <input type="number" id="websocket_port" name="websocket_port"
                                           class="form-control" min="1" max="65535" autocomplete="off"
                                           value="{{ $websocket_port }}"
                                           placeholder="{{ translate('messages.Ex') . ': 6001' }}"
                                           required>
                                    <small class="tps-field__hint">
                                        {{ translate('The port your WebSocket server listens on.') }} {{ translate('Default port for Reverb and Pusher') }}: 6001
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tps-group">
                        <p class="tps-group__label">{{ translate('What the apps will dial') }}</p>

                        <div class="wsk-endpoint">
                            <span class="wsk-endpoint__label">{{ translate('Endpoint') }}</span>
                            <div class="wsk-endpoint__box">
                                <div class="tps-readonly">
                                    <span class="tps-readonly__value wsk-endpoint__value {{ $endpoint ? '' : 'is-empty' }}"
                                          id="websocket_endpoint"
                                          data-empty="{{ translate('Fill both fields to see the endpoint') }}">
                                        {{ $endpoint ?: translate('Fill both fields to see the endpoint') }}
                                    </span>
                                    <button type="button" class="tps-readonly__copy tps-copy"
                                            id="websocket_endpoint_copy"
                                            data-target="#websocket_endpoint"
                                            {{ $endpoint ? '' : 'disabled' }}>
                                        <i class="tio-copy"></i> {{ translate('messages.Copy') }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="tps-note tps-note--warn mt-3 {{ $is_secure || ! $is_configured ? 'd-none' : '' }}"
                             id="websocket_insecure_note">
                            <i class="tio-warning"></i>
                            <div>
                                {{ translate('This address is unencrypted. A site served over HTTPS blocks a ws:// connection, so use wss:// in production.') }}
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tps-card__foot">
                    <span class="tps-foot-note">
                        {{ translate('The switch is only staged — press save to apply it.') }}
                    </span>
                    <button type="reset" id="reset_btn" class="btn btn--reset">
                        <i class="tio-refresh"></i> {{ translate('messages.Reset') }}
                    </button>
                    <button type="{{ ! $is_demo ? 'submit' : 'button' }}" id="submit" class="btn btn--primary call-demo">
                        <i class="tio-save"></i> {{ translate('messages.Save') }}
                    </button>
                </div>
            </div>
        </form>

        <div class="tps-card">
            <div class="tps-card__head">
                <span class="tps-card__brand"><i class="tio-flash"></i></span>
                <div class="tps-card__titles">
                    <h2 class="tps-card__title">{{ translate('What this connection powers') }}</h2>
                    <p class="tps-card__subtitle">
                        {{ translate('messages.WebSockets enable real-time communication between the server and your app.') }}
                    </p>
                </div>
            </div>
            <div class="tps-card__body">
                <div class="wsk-uses">
                    @foreach ($uses as $use)
                        <div class="wsk-use">
                            <span class="wsk-use__icon"><i class="{{ $use['icon'] }}"></i></span>
                            <div class="wsk-use__text">
                                <h3>{{ $use['title'] }}</h3>
                                <p>{{ $use['text'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="websocket-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            {{-- The modal sits outside the page's .tps wrapper, so it carries the
                 root class itself or .tps-note has no tokens to draw with. --}}
            <div class="modal-content tps">
                <div class="modal-header">
                    <h5 class="modal-title">{{ translate('How the WebSocket connection works') }}</h5>
                    <button type="button" class="close btn btn--reset btn-circle" data-dismiss="modal"
                            aria-label="{{ translate('messages.Close') }}">
                        <span aria-hidden="true" class="tio-clear fs-20 opacity-70"></span>
                    </button>
                </div>
                <div class="modal-body">
                    <ol class="tps-steps mb-3">
                        <li>{{ translate('Run a WebSocket server — Reverb ships with the system, Pusher is the hosted alternative.') }}</li>
                        <li>{{ translate('Open its port on your firewall and point a domain at it.') }}</li>
                        <li>{{ translate('Enter that address and port here, then switch the connection on and press save.') }}</li>
                        <li>{{ translate('Restart the queue worker and the WebSocket server so they pick up the new values.') }}</li>
                    </ol>
                    <div class="tps-note tps-note--muted">
                        <i class="tio-info-outined"></i>
                        <div>
                            {{ translate('With the connection off, the apps fall back to polling. Nothing breaks — updates just arrive later.') }}
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

        $(function () {
            const $url = $('#websocket_url');
            const $port = $('#websocket_port');
            const $endpoint = $('#websocket_endpoint');
            const $insecure = $('#websocket_insecure_note');
            const $copy = $('#websocket_endpoint_copy');
            const emptyText = $endpoint.data('empty');

            /* The endpoint is built from two fields, so it is worth showing back
               joined — a port typed into the URL field is otherwise invisible
               until an app fails to connect. */
            const syncEndpoint = function () {
                const url = $url.val().trim().replace(/\/+$/, '');
                const port = $port.val().trim();
                const ready = url !== '' && port !== '';

                $endpoint.text(ready ? url + ':' + port : emptyText)
                    .toggleClass('is-empty', !ready);

                /* .tps-copy reads the node's text, so an empty endpoint would
                   put the placeholder sentence on the clipboard. */
                $copy.prop('disabled', !ready);

                $insecure.toggleClass('d-none', !ready || /^wss:\/\//i.test(url));
            };

            $url.add($port).on('input change', syncEndpoint);

            /* The switch only stages its change, so the pill and the header chip
               have to follow the checkbox rather than the saved value. */
            const $pill = $('#websocket_pill');
            const $chip = $('#websocket_status_chip');

            $('#websocket').on('change', function () {
                const on = this.checked;

                $pill.text(on ? $pill.data('label-on') : $pill.data('label-off'))
                    .toggleClass('tps-pill--on', on)
                    .toggleClass('tps-pill--off', !on);

                $chip.toggleClass('is-live', on)
                    .find('.wsk-status__text')
                    .text(on ? $chip.data('label-on') : $chip.data('label-off'));
            });

            /* A reset puts the saved values back, but the fields it drives are
               repainted on the next tick, not by the reset itself. */
            $('#reset_btn').on('click', function () {
                window.setTimeout(function () {
                    syncEndpoint();
                    $('#websocket').trigger('change');
                }, 0);
            });

            syncEndpoint();
        });
    </script>
@endpush
