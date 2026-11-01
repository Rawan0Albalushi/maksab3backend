@extends('layouts.admin.app')

@section('title', translate('System module setup'))

@push('css_or_js')
    <link rel="stylesheet" href="{{ asset('public/assets/admin/css/third-party-setup.css') }}">
@endpush

@section('content')
    @php
        $is_demo = getEnvMode() == 'demo' || env('APP_ENV') == 'demo';
        $addon_overrides = $published_status == 1;

        $secret_keys = ['token', 'api_secret', 'auth_key', 'api_key', 'secret'];

        $gateway_meta = [
            'twilio' => ['label' => 'Twilio', 'hint' => translate('Credentials live in the Twilio Console dashboard.'), 'link' => 'https://console.twilio.com/'],
            'nexmo' => ['label' => 'Vonage (Nexmo)', 'hint' => translate('Find the API key and secret on the Vonage dashboard.'), 'link' => 'https://dashboard.nexmo.com/'],
            '2factor' => ['label' => '2Factor', 'hint' => translate('Copy the API key from your provider account.'), 'link' => 'https://2factor.in/'],
            'msg91' => ['label' => 'MSG91', 'hint' => translate('Auth key and template ID come from the provider panel.'), 'link' => 'https://control.msg91.com/'],
            'alphanet_sms' => ['label' => 'AlphaNet SMS', 'hint' => translate('Provided by your AlphaNet SMS account manager.'), 'link' => null],
        ];

        $field_labels = [
            'sid' => 'Account SID',
            'messaging_service_sid' => 'Messaging Service SID',
            'template_id' => 'Template ID',
        ];

        $field_hints = [
            'otp_template' => translate('Use #OTP# where the generated code should appear.'),
            'from' => translate('The sender number or ID recipients will see.'),
            'sender_id' => translate('Approved alphanumeric sender ID, if your account uses one.'),
            'template_id' => translate('ID of the approved DLT template.'),
            'messaging_service_sid' => translate('Optional. Use it when sending through a Twilio Messaging Service.'),
        ];

        $active_gateway = $data_values->first(fn ($item) => (int) ($item->live_values['status'] ?? 0) === 1);
    @endphp

    <div class="content container-fluid tps">
        @include('admin-views.business-settings.partials.third-party-header', [
            'icon' => 'tio-message',
            'title' => translate('messages.Sms gateway setup'),
            'summary' => translate('Choose the gateway that delivers OTP and transactional messages to your users.'),
        ])

        @if ($addon_overrides)
            <div class="tps-note tps-note--warn mb-3 align-items-center">
                <i class="tio-warning"></i>
                <div class="flex-grow-1">
                    {{ translate('The SMS gateway add-on is on, so these settings are disabled. Follow the link to the active ones.') }}
                </div>
                <a href="{{ ! empty($payment_url) ? $payment_url : '#' }}" class="btn btn-outline-primary btn-sm flex-shrink-0">
                    <i class="tio-settings"></i> {{ translate('Settings') }}
                </a>
            </div>
        @else
            <div class="tps-note {{ $active_gateway ? 'tps-note--info' : 'tps-note--muted' }} mb-3">
                <i class="{{ $active_gateway ? 'tio-info' : 'tio-warning-outlined' }}"></i>
                <div>
                    @if ($active_gateway)
                        <strong>{{ $gateway_meta[$active_gateway->key_name]['label'] ?? ucfirst(str_replace('_', ' ', $active_gateway->key_name)) }}</strong>
                        {{ translate('is currently sending your messages. Activating another gateway switches this one off.') }}
                    @else
                        {{ translate('No SMS gateway is active yet. Until one is switched on, users cannot receive OTP or transactional messages by SMS.') }}
                    @endif
                </div>
            </div>
        @endif

        <div class="tps-masonry">
            @foreach ($data_values as $gateway)
                @php
                    $values = $gateway->live_values;
                    $is_active = (int) ($values['status'] ?? 0) === 1;
                    $meta = $gateway_meta[$gateway->key_name] ?? ['label' => ucfirst(str_replace('_', ' ', $gateway->key_name)), 'hint' => null, 'link' => null];
                    $fields = collect($values)->except(['gateway', 'mode', 'status']);
                @endphp

                <div class="digital_payment_methods {{ $addon_overrides ? 'inactive' : '' }}">
                    <form action="{{ route('admin.business-settings.third-party.sms-module-update', [$gateway->key_name]) }}"
                          method="POST" id="{{ $gateway->key_name }}-form" enctype="multipart/form-data">
                        @csrf
                        @method('post')

                        <div class="tps-card">
                            <div class="tps-card__head">
                                <span class="tps-card__brand">{{ mb_substr($meta['label'], 0, 1) }}</span>
                                <div class="tps-card__titles">
                                    <h2 class="tps-card__title">{{ $meta['label'] }}</h2>
                                    @if (! empty($meta['hint']))
                                        <p class="tps-card__subtitle">{{ $meta['hint'] }}</p>
                                    @endif
                                </div>
                                <div class="tps-card__aside">
                                    <div class="tps-seg">
                                        <input class="{{ \App\CentralLogics\Helpers::get_business_settings('firebase_otp_verification', false) == 1 ? 'firebase-check' : '' }}"
                                               type="radio" id="{{ $gateway->key_name }}-active" name="status" value="1"
                                               {{ $is_active ? 'checked' : '' }}>
                                        <label for="{{ $gateway->key_name }}-active">{{ translate('messages.Active') }}</label>

                                        <input type="radio" id="{{ $gateway->key_name }}-inactive" name="status" value="0"
                                               {{ $is_active ? '' : 'checked' }}>
                                        <label for="{{ $gateway->key_name }}-inactive">{{ translate('messages.Inactive') }}</label>
                                    </div>
                                </div>
                            </div>

                            <div class="tps-card__body">
                                <input type="hidden" name="gateway" value="{{ $gateway->key_name }}">
                                <input type="hidden" name="mode" value="live">

                                <div class="row g-3">
                                    @foreach ($fields as $key => $value)
                                        @php
                                            $is_secret = in_array($key, $secret_keys, true);
                                            $is_optional = $gateway->key_name == 'alphanet_sms' && $key == 'sender_id';
                                            $field_id = $gateway->key_name . '_' . $key;
                                        @endphp

                                        <div class="col-md-6">
                                            <div class="tps-field">
                                                <label for="{{ $field_id }}" class="tps-field__label text-capitalize">
                                                    {{ $field_labels[$key] ?? translate($key) }}
                                                    @if ($is_optional)
                                                        <span class="tps-opt">({{ translate('Optional') }})</span>
                                                    @else
                                                        <span class="tps-req">*</span>
                                                    @endif
                                                </label>

                                                <div class="tps-input-wrap {{ $is_secret ? 'has-two-actions' : '' }}">
                                                    <input id="{{ $field_id }}" name="{{ $key }}" class="form-control"
                                                           type="{{ $is_secret ? 'password' : 'text' }}" autocomplete="off"
                                                           placeholder="{{ $key == 'otp_template' ? translate('Your Security Pin is') . ' #OTP#' : ($field_labels[$key] ?? translate($key)) }}"
                                                           value="{{ $is_demo ? '' : $value }}">
                                                    <button type="button" class="tps-input-action tps-copy"
                                                            data-target="#{{ $field_id }}" aria-label="{{ translate('Copy') }}">
                                                        <i class="tio-copy"></i>
                                                    </button>
                                                    @if ($is_secret)
                                                        <button type="button" class="tps-input-action tps-toggle-secret"
                                                                data-target="#{{ $field_id }}" aria-label="{{ translate('Show value') }}">
                                                            <i class="tio-visible"></i>
                                                        </button>
                                                    @endif
                                                </div>

                                                @if (! empty($field_hints[$key]))
                                                    <small class="tps-field__hint">{{ $field_hints[$key] }}</small>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            <div class="tps-card__foot">
                                @if (! empty($meta['link']))
                                    <a href="{{ $meta['link'] }}" target="_blank" rel="noopener" class="tps-foot-note text--primary">
                                        <i class="tio-launch"></i> {{ translate('Open dashboard') }}
                                    </a>
                                @endif
                                <button type="submit" class="btn btn--primary demo_check">
                                    <i class="tio-save"></i> {{ translate('Update') }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endsection

@push('script_2')
    @include('admin-views.business-settings.partials.third-party-scripts')
@endpush
