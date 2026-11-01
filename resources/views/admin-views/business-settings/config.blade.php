@extends('layouts.admin.app')

@section('title', translate('messages.Third party apis'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $map_api_key = \App\CentralLogics\Helpers::get_business_settings('map_api_key', false) ?? null;
        $map_api_key_server = \App\CentralLogics\Helpers::get_business_settings('map_api_key_server', false) ?? null;
        $is_demo = getEnvMode() == 'demo';
        $is_configured = ! empty($map_api_key) && ! empty($map_api_key_server);
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-map',
            'title' => translate('messages.Third party apis'),
            'summary' => translate('Connect the Google Maps keys that power addresses, delivery zones and distance based charges.'),
            'helpTarget' => '#map-help-modal',
        ])

        <div class="row g-3">
            <div class="col-lg-8">
                <form action="{{ ! $is_demo ? route('admin.business-settings.third-party.config-update') : 'javascript:' }}"
                      method="post" enctype="multipart/form-data">
                    @csrf
                    <div class="tps-card">
                        <div class="tps-card__head">
                            <span class="tps-card__brand">
                                <img src="{{ asset('public/assets/admin/img/api.png') }}" alt="">
                            </span>
                            <div class="tps-card__titles">
                                <h2 class="tps-card__title">{{ translate('Google Map API Setup') }}</h2>
                                <p class="tps-card__subtitle">
                                    {{ translate('Both keys come from the same Google Cloud project but are restricted differently.') }}
                                </p>
                            </div>
                            <div class="tps-card__aside">
                                <span class="tps-pill {{ $is_configured ? 'tps-pill--on' : 'tps-pill--warn' }}">
                                    {{ $is_configured ? translate('Configured') : translate('Not Configured') }}
                                </span>
                            </div>
                        </div>

                        <div class="tps-card__body">
                            <div class="tps-note tps-note--warn mb-4">
                                <i class="tio-warning"></i>
                                <div>
                                    {{ translate('Without configuring this section map functionality will not work properly. Thus the whole system will not work as it planned') }}
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-12">
                                    <div class="tps-field">
                                        <label for="map_api_key" class="tps-field__label">
                                            {{ translate('messages.Map api key') }} ({{ translate('messages.client') }})
                                            <span class="tps-req">*</span>
                                        </label>
                                        <div class="tps-input-wrap has-two-actions">
                                            <input id="map_api_key" type="password" autocomplete="off"
                                                   placeholder="AIzaSy…" class="form-control" name="map_api_key"
                                                   value="{{ ! $is_demo ? $map_api_key ?? '' : '' }}" required>
                                            <button type="button" class="tps-input-action tps-copy" data-target="#map_api_key"
                                                    aria-label="{{ translate('Copy') }}">
                                                <i class="tio-copy"></i>
                                            </button>
                                            <button type="button" class="tps-input-action tps-toggle-secret" data-target="#map_api_key"
                                                    aria-label="{{ translate('Show value') }}">
                                                <i class="tio-visible"></i>
                                            </button>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Used by the customer apps and websites. Restrict it by HTTP referrer and Android/iOS app.') }}
                                        </small>
                                    </div>
                                </div>

                                <div class="col-md-12">
                                    <div class="tps-field">
                                        <label for="map_api_key_server" class="tps-field__label">
                                            {{ translate('messages.Map api key') }} ({{ translate('messages.Server') }})
                                            <span class="tps-req">*</span>
                                        </label>
                                        <div class="tps-input-wrap has-two-actions">
                                            <input id="map_api_key_server" type="password" autocomplete="off"
                                                   placeholder="AIzaSy…" class="form-control" name="map_api_key_server"
                                                   value="{{ ! $is_demo ? $map_api_key_server ?? '' : '' }}" required>
                                            <button type="button" class="tps-input-action tps-copy" data-target="#map_api_key_server"
                                                    aria-label="{{ translate('Copy') }}">
                                                <i class="tio-copy"></i>
                                            </button>
                                            <button type="button" class="tps-input-action tps-toggle-secret" data-target="#map_api_key_server"
                                                    aria-label="{{ translate('Show value') }}">
                                                <i class="tio-visible"></i>
                                            </button>
                                        </div>
                                        <small class="tps-field__hint">
                                            {{ translate('Used by the backend for geocoding and distance calculation. Restrict it by IP address instead of referrer.') }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="tps-card__foot">
                            <span class="tps-foot-note">{{ translate('Set both the client and the server key. Maps, address search and delivery distance stop working if either key is missing or invalid.') }}</span>
                            <button type="reset" class="btn btn--reset"><i class="tio-refresh"></i> {{ translate('Reset') }}</button>
                            <button type="{{ ! $is_demo ? 'submit' : 'button' }}" class="btn btn--primary call-demo">
                                <i class="tio-save"></i> {{ translate('messages.Save') }}
                            </button>
                        </div>
                    </div>
                </form>
            </div>

            <div class="col-lg-4">
                <div class="tps-card">
                    <div class="tps-card__head">
                        <span class="tps-card__brand"><i class="tio-checkmark-circle-outlined"></i></span>
                        <div class="tps-card__titles">
                            <h2 class="tps-card__title">{{ translate('Required Google APIs') }}</h2>
                            <p class="tps-card__subtitle">
                                {{ translate('Enable each of these in your Google Cloud project, otherwise some map features fail silently.') }}
                            </p>
                        </div>
                    </div>
                    <div class="tps-card__body">
                        <ul class="list-unstyled m-0 d-flex flex-column __gap-12px">
                            @foreach ([
                                'Maps SDK for Android',
                                'Maps SDK for iOS',
                                'Maps JavaScript API',
                                'Places API',
                                'Geocoding API',
                                'Distance Matrix API',
                                'Directions API',
                            ] as $google_api)
                                <li class="d-flex align-items-center gap-2 fs-12">
                                    <i class="tio-checkmark-circle text-success"></i>
                                    <span>{{ $google_api }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <a href="https://console.cloud.google.com/google/maps-apis/credentials" target="_blank" rel="noopener"
                           class="btn btn-outline-primary btn-sm w-100 mt-4">
                            <i class="tio-launch"></i> {{ translate('Open Google Cloud Console') }}
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="map-help-modal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header border-0">
                    <h5 class="modal-title">{{ translate('How to get your Map API keys') }}</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="{{ translate('Close') }}">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body pt-0">
                    <ol class="tps-steps">
                        <li>
                            {{ translate('Open the') }}
                            <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a>
                            {{ translate('and create or select a project.') }}
                        </li>
                        <li>{{ translate('Enable billing for the project — Google Maps APIs will not respond without it.') }}</li>
                        <li>{{ translate('From APIs & Services, enable every API listed on this page.') }}</li>
                        <li>{{ translate('Open Credentials and create two API keys: one for the client apps and one for the server.') }}</li>
                        <li>{{ translate('Restrict the client key by HTTP referrer and app package, and the server key by IP address.') }}</li>
                        <li>{{ translate('Paste both keys into the fields on this page and press Save.') }}</li>
                    </ol>
                </div>
                <div class="modal-footer justify-content-center border-0">
                    <button type="button" class="btn btn--primary w-100" data-dismiss="modal"><i class="tio-checkmark-circle-outlined"></i> {{ translate('Got it') }}</button>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
