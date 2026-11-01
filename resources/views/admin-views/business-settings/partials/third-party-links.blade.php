@php
    use App\CentralLogics\Helpers;
    use App\Models\ErpApiToken;
    use App\Models\Setting;

    /*
     * A small green dot next to a tab means that integration is currently switched on,
     * so an admin can see the state of every third party service without opening each tab.
     */
    $tps_array = fn ($value) => is_array($value) ? $value : [];

    $tps_mail_config = $tps_array(Helpers::get_business_settings('mail_config'));
    $tps_recaptcha = $tps_array(Helpers::get_business_settings('recaptcha'));
    $tps_logins = array_merge(
        $tps_array(Helpers::get_business_settings('social_login')),
        $tps_array(Helpers::get_business_settings('apple_login'))
    );

    $tps_status = [
        'sms' => Setting::where('settings_type', 'sms_config')->whereJsonContains('live_values->status', '1')->exists(),
        'mail' => (int) ($tps_mail_config['status'] ?? 0) === 1,
        'map' => ! empty(Helpers::get_business_settings('map_api_key', false)),
        'social' => collect($tps_logins)->contains(fn ($item) => is_array($item) && (int) ($item['status'] ?? 0) === 1),
        'recaptcha' => (int) ($tps_recaptcha['status'] ?? 0) === 1,
        'firebase' => (int) (Helpers::get_business_settings('firebase_otp_verification', false) ?? 0) === 1,
        'storage' => (int) (Helpers::get_business_settings('3rd_party_storage', false) ?? 0) === 1,
        'integration' => ErpApiToken::active()->exists(),
    ];

    $tps_tabs = [
        [
            'key' => 'sms',
            'icon' => 'tio-message',
            'label' => translate('SMS Module'),
            'url' => route('admin.business-settings.third-party.sms-module'),
            'active' => Request::is('admin/business-settings/third-party/sms-module'),
        ],
        [
            'key' => 'mail',
            'icon' => 'tio-email',
            'label' => translate('Mail Config'),
            'url' => route('admin.business-settings.third-party.mail-config'),
            'active' => Request::is('admin/business-settings/third-party/mail-config') || Request::is('admin/business-settings/third-party/test-mail'),
        ],
        [
            'key' => 'map',
            'icon' => 'tio-map',
            'label' => translate('Map APIs'),
            'url' => route('admin.business-settings.third-party.config-setup'),
            'active' => Request::is('admin/business-settings/third-party/config-setup'),
        ],
        [
            'key' => 'social',
            'icon' => 'tio-users-switch',
            'label' => translate('Social Logins'),
            'url' => route('admin.business-settings.third-party.social-login.view'),
            'active' => Request::is('admin/business-settings/third-party/social-login/view'),
        ],
        [
            'key' => 'recaptcha',
            'icon' => 'tio-robot',
            'label' => 'reCAPTCHA',
            'url' => route('admin.business-settings.third-party.recaptcha_index'),
            'active' => Request::is('admin/business-settings/third-party/recaptcha*'),
        ],
        [
            'key' => 'firebase',
            'icon' => 'tio-fingerprint',
            'label' => translate('Firebase OTP'),
            'url' => route('admin.business-settings.third-party.firebase_otp_index'),
            'active' => Request::is('admin/business-settings/third-party/firebase-otp*'),
        ],
        [
            'key' => 'storage',
            'icon' => 'tio-cloud',
            'label' => translate('Storage Connection'),
            'url' => route('admin.business-settings.third-party.storage_connection_index'),
            'active' => Request::is('admin/business-settings/third-party/storage-connection*'),
        ],
        [
            'key' => 'integration',
            'icon' => 'tio-plug',
            'label' => translate('messages.integration'),
            'url' => route('admin.business-settings.third-party.integration'),
            'active' => Request::is('admin/business-settings/third-party/integration*'),
        ],
    ];
@endphp

<nav class="tps-nav" aria-label="{{ translate('Third party setup sections') }}">
    <div class="tps-nav__scroll">
        @foreach ($tps_tabs as $tps_tab)
            <a href="{{ $tps_tab['url'] }}"
               class="tps-nav__item {{ $tps_tab['active'] ? 'is-active' : '' }}"
               @if ($tps_tab['active']) aria-current="page" @endif>
                <i class="{{ $tps_tab['icon'] }}"></i>
                <span>{{ $tps_tab['label'] }}</span>
                @if ($tps_status[$tps_tab['key']])
                    <span class="tps-nav__dot" title="{{ translate('Active') }}"></span>
                @endif
            </a>
        @endforeach
    </div>
</nav>
