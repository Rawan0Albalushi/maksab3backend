{{--
    Sub navigation shared by the AI Configuration and AI Settings screens.

    @param string $current  'config' | 'settings'
    @param bool   $isOn     whether AI is switched on, drives the dot on the config tab
--}}
<nav class="tps-nav" aria-label="{{ translate('AI setup sections') }}">
    <div class="tps-nav__scroll">
        <a href="{{ route('admin.business-settings.openAI') }}"
           class="tps-nav__item {{ ($current ?? '') === 'config' ? 'is-active' : '' }}"
           @if (($current ?? '') === 'config') aria-current="page" @endif>
            <i class="tio-key"></i>
            <span>{{ translate('AI configuration') }}</span>
            @if ($isOn ?? false)
                <span class="tps-nav__dot"></span>
            @endif
        </a>
        <a href="{{ route('admin.business-settings.openAISettings') }}"
           class="tps-nav__item {{ ($current ?? '') === 'settings' ? 'is-active' : '' }}"
           @if (($current ?? '') === 'settings') aria-current="page" @endif>
            <i class="tio-tune-horizontal"></i>
            <span>{{ translate('AI Settings') }}</span>
        </a>
    </div>
</nav>
