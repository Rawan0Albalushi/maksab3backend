{{-- Sub navigation shared by the Mail Config and Send Test Mail screens. @param string $current --}}
<nav class="tps-nav mb-0" aria-label="{{ translate('Mail setup sections') }}">
    <div class="tps-nav__scroll">
        <a href="{{ route('admin.business-settings.third-party.mail-config') }}"
           class="tps-nav__item {{ ($current ?? '') === 'config' ? 'is-active' : '' }}"
           @if (($current ?? '') === 'config') aria-current="page" @endif>
            <i class="tio-settings"></i>
            <span>{{ translate('Mail Config') }}</span>
        </a>
        <a href="{{ route('admin.business-settings.third-party.test') }}"
           class="tps-nav__item {{ ($current ?? '') === 'test' ? 'is-active' : '' }}"
           @if (($current ?? '') === 'test') aria-current="page" @endif>
            <i class="tio-telegram"></i>
            <span>{{ translate('Send Test Mail') }}</span>
        </a>
    </div>
</nav>
